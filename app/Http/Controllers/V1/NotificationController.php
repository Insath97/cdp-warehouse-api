<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NotificationController extends Controller implements HasMiddleware
{
    /**
     * Define the middleware for this controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:Notification Index', ['only' => ['index', 'markAsRead', 'markAllAsRead']]),
        ];
    }

    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request)
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $perPage = $request->get('per_page', 15);
            $filter = $request->get('filter', 'all'); // 'all', 'unread', 'read'

            $query = $user->notifications();

            if ($filter === 'unread') {
                $query = $user->unreadNotifications();
            } elseif ($filter === 'read') {
                $query = $user->readNotifications();
            }

            $notifications = $query->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'message' => 'Notifications retrieved successfully',
                'data' => $notifications,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve notifications',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(string $id)
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $notification = $user->notifications()->find($id);

            if (!$notification) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Notification not found',
                ], 404);
            }

            $notification->markAsRead();

            return response()->json([
                'status' => 'success',
                'message' => 'Notification marked as read successfully',
                'data' => $notification,
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark notification as read',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Mark all unread notifications as read.
     */
    public function markAllAsRead()
    {
        try {
            $user = auth('api')->user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not found',
                ], 404);
            }

            $user->unreadNotifications->markAsRead();

            return response()->json([
                'status' => 'success',
                'message' => 'All notifications marked as read successfully',
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to mark all notifications as read',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error',
            ], 500);
        }
    }
}
