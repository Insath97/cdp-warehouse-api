<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Traits\ActivityLogTrait;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ActivityLogController extends Controller implements HasMiddleware
{
    use ActivityLogTrait;

    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:ActivityLog Index', ['only' => ['index', 'show']]),
        ];
    }

    /**
     * Display a listing of activity logs (Get All).
     */
    public function index(Request $request)
    {
        try {
            $perPage = (int) $request->get('per_page', 15);
            $sortBy = $request->get('sort_by', 'id');
            $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';

            $query = ActivityLog::with([
                'user:id,name,username,email,user_scope,branch_id,warehouse_id',
                'user.branch:id,name,code',
                'user.warehouse:id,name,code'
            ]);

            // Search Scope
            if ($request->filled('search')) {
                $query->search($request->search);
            }

            // Filters
            if ($request->filled('module')) {
                $query->byModule($request->module);
            }

            if ($request->filled('action')) {
                $query->byAction($request->action);
            }

            if ($request->filled('level')) {
                $query->byLevel($request->level);
            }

            if ($request->filled('user_id')) {
                $query->byUser((int) $request->user_id);
            }

            if ($request->filled('ip_address')) {
                $query->where('ip_address', 'like', '%' . $request->ip_address . '%');
            }

            if ($request->filled('start_date') || $request->filled('end_date')) {
                $query->dateRange($request->get('start_date'), $request->get('end_date'));
            }

            // Allowed sort columns
            $allowedSorts = ['id', 'action', 'module', 'level', 'ip_address', 'created_at'];
            if (!in_array($sortBy, $allowedSorts, true)) {
                $sortBy = 'id';
            }

            $logs = $query->orderBy($sortBy, $sortOrder)->paginate($perPage);

            $this->logActivity('INDEX', 'ActivityLog', 'Retrieved listing of activity logs');

            return response()->json([
                'status' => 'success',
                'message' => 'Activity logs retrieved successfully',
                'data' => $logs,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity logs',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Display the specified activity log (Get By ID).
     */
    public function show(string $id)
    {
        try {
            $log = ActivityLog::with([
                'user:id,name,username,email,user_scope,branch_id,warehouse_id',
                'user.branch:id,name,code',
                'user.warehouse:id,name,code'
            ])->find($id);

            if (!$log) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Activity log not found',
                ], 404);
            }

            $this->logActivity('SHOW', 'ActivityLog', "Retrieved activity log details for ID: {$id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Activity log retrieved successfully',
                'data' => $log,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve activity log',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
