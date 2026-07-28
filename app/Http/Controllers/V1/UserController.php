<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CreateUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Models\Warehouse;
use App\Traits\ActivityLogTrait;
use App\Mail\UserCreateMail;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class UserController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:User Index', ['only' => ['index', 'show']]),
            new Middleware('permission:User List', ['only' => ['getActiveList']]),
            new Middleware('permission:User Create', ['only' => ['store']]),
            new Middleware('permission:User Update', ['only' => ['update']]),
            new Middleware('permission:User Delete', ['only' => ['destroy']]),
            new Middleware('permission:User Toggle Status', ['only' => ['toggleStatus']]),
        ];
    }

    /**
     * Display a listing of users, scoped to the current user's authorization level.
     */
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 15);
            $authUser = auth()->user();

            $query = User::with(['branch', 'warehouse', 'roles'])
                ->accessibleBy($authUser);

            // Apply Search Scope if search parameter is present
            if ($request->has('search') && $request->search != '') {
                $query->search($request->search);
            }

            if ($request->has('user_scope') && $request->user_scope != '') {
                $query->where('user_scope', $request->user_scope);
            }

            if ($request->has('branch_id') && $request->branch_id != '') {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('warehouse_id') && $request->warehouse_id != '') {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $users = $query->orderBy('name', 'asc')->paginate($perPage);

            $this->logActivity('INDEX', 'User', 'Retrieved users listing');

            return response()->json([
                'status' => 'success',
                'message' => 'Users retrieved successfully',
                'data' => $users,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve users',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Store a newly created user with automatic scope locking based on creator's access level.
     */
    public function store(CreateUserRequest $request)
    {
        try {
            $authUser = auth()->user();
            $data = $request->validated();

            // Enforce Scope Locking according to logged-in user's role/level
            if ($authUser->isBranchScoped()) {
                // Branch admin can only create branch or warehouse users under their branch
                $data['branch_id'] = $authUser->branch_id;
                if (($data['user_scope'] ?? '') === 'global') {
                    $data['user_scope'] = 'branch';
                }
            } elseif ($authUser->isWarehouseScoped()) {
                // Warehouse admin can only create warehouse users under their warehouse
                $data['user_scope'] = 'warehouse';
                $data['branch_id'] = $authUser->branch_id;
                $data['warehouse_id'] = $authUser->warehouse_id;
            }

            // Derive branch_id from warehouse if warehouse_id is specified without explicit branch_id
            if (!empty($data['warehouse_id']) && empty($data['branch_id'])) {
                $warehouse = Warehouse::find($data['warehouse_id']);
                if ($warehouse) {
                    $data['branch_id'] = $warehouse->branch_id;
                }
            }

            $rawPassword = $data['password'];

            // Hash password
            $data['password'] = Hash::make($data['password']);

            $roles = $data['roles'] ?? [];
            unset($data['roles']);

            $user = User::create($data);

            if (!empty($roles)) {
                $user->syncRoles($roles);
            }

            $user->load(['branch', 'warehouse', 'roles']);

            // Send Welcome Email if email is provided
            if (!empty($user->email)) {
                try {
                    $mailData = [
                        'user' => $user->toArray(),
                        'password' => $rawPassword,
                        'role' => $user->roles->pluck('name')->implode(', '),
                        'user_scope' => $user->user_scope,
                        'branch_name' => $user->branch?->name,
                        'warehouse_name' => $user->warehouse?->name,
                        'login_url' => config('app.url') . '/login',
                        'created_by' => $authUser->name ?? 'System',
                    ];

                    Mail::to($user->email)->send(new UserCreateMail($mailData));
                } catch (\Throwable $mailEx) {
                    Log::warning("Failed to send welcome email to user {$user->username}: " . $mailEx->getMessage());
                }
            }

            $this->logActivity('CREATE', 'User', "Created user: {$user->name} ({$user->username}) with scope {$user->user_scope}", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'User created successfully',
                'data' => $user,
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create user',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified user.
     */
    public function show(string $id)
    {
        try {
            $authUser = auth()->user();
            $user = User::with(['branch', 'warehouse', 'roles'])
                ->accessibleBy($authUser)
                ->find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found or access unauthorized',
                ], 404);
            }

            $this->logActivity('SHOW', 'User', "Retrieved user details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'User retrieved successfully',
                'data' => $user,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve user',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update the specified user in storage.
     */
    public function update(UpdateUserRequest $request, string $id)
    {
        try {
            $authUser = auth()->user();
            $user = User::accessibleBy($authUser)->find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found or access unauthorized',
                ], 404);
            }

            $data = $request->validated();

            // Enforce Scope Locking on update
            if ($authUser->isBranchScoped()) {
                $data['branch_id'] = $authUser->branch_id;
                if (isset($data['user_scope']) && $data['user_scope'] === 'global') {
                    unset($data['user_scope']);
                }
            } elseif ($authUser->isWarehouseScoped()) {
                $data['branch_id'] = $authUser->branch_id;
                $data['warehouse_id'] = $authUser->warehouse_id;
                $data['user_scope'] = 'warehouse';
            }

            // Handle password update if provided by admin
            if (!empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
                $data['password_change_count'] = 0; // Reset self-change count when admin changes password
            } else {
                unset($data['password']);
            }

            if (isset($data['password_change_count'])) {
                $data['password_change_count'] = (int) $data['password_change_count'];
            }

            $roles = $data['roles'] ?? null;
            unset($data['roles']);

            $user->update($data);

            if ($roles !== null) {
                $user->syncRoles($roles);
            }

            $user->load(['branch', 'warehouse', 'roles']);

            $this->logActivity('UPDATE', 'User', "Updated user: {$user->name} ({$user->username})", $data);

            return response()->json([
                'status' => 'success',
                'message' => 'User updated successfully',
                'data' => $user,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update user',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Remove the specified user from storage.
     */
    public function destroy(string $id)
    {
        try {
            $authUser = auth()->user();

            if ($authUser->id == $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot delete your own user account',
                ], 422);
            }

            $user = User::accessibleBy($authUser)->find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found or access unauthorized',
                ], 404);
            }

            $username = $user->username;
            $name = $user->name;
            $user->delete();

            $this->logActivity('DELETE', 'User', "Deleted user: {$name} ({$username})");

            return response()->json([
                'status' => 'success',
                'message' => 'User deleted successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete user',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get a lightweight list of active users (for dropdowns).
     */
    public function getActiveList(Request $request)
    {
        try {
            $authUser = auth()->user();

            $query = User::where('is_active', true)
                ->where('can_login', true)
                ->accessibleBy($authUser);

            if ($request->has('branch_id') && $request->branch_id != '') {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('warehouse_id') && $request->warehouse_id != '') {
                $query->where('warehouse_id', $request->warehouse_id);
            }

            $users = $query->orderBy('name', 'asc')->get(['id', 'name', 'username', 'email', 'user_scope', 'branch_id', 'warehouse_id']);

            return response()->json([
                'status' => 'success',
                'message' => 'Active users retrieved successfully',
                'data' => $users,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve active users',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Toggle the active status of a user.
     */
    public function toggleStatus(string $id)
    {
        try {
            $authUser = auth()->user();

            if ($authUser->id == $id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You cannot toggle your own active status',
                ], 422);
            }

            $user = User::accessibleBy($authUser)->find($id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found or access unauthorized',
                ], 404);
            }

            $user->is_active = !$user->is_active;
            $user->save();

            $this->logActivity('TOGGLE_STATUS', 'User', "Toggled active status for user: {$user->username} (" . ($user->is_active ? 'Active' : 'Inactive') . ")");

            return response()->json([
                'status' => 'success',
                'message' => 'User active status updated successfully',
                'data' => [
                    'id' => $user->id,
                    'is_active' => $user->is_active,
                ],
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to toggle user status',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
