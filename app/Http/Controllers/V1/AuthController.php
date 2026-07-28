<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use App\Models\PasswordOtp;
use App\Models\SystemSetting;
use App\Models\User;
use App\Mail\PasswordResetOtpMail;
use App\Services\SmsService;
use App\Traits\ActivityLogTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    use ActivityLogTrait;

      /**
     * Admin Login
     * Only users with user_type = 'admin' can login here
     */
    public function login(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'login' => 'required|string',
                'password' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $loginVal = $request->input('login');
            $passwordVal = $request->input('password');

            // Find user by username or email
            $user = User::where('username', $loginVal)
                ->orWhere('email', $loginVal)
                ->first();

            // Fallback: search employee by id_number
            if (!$user) {
                $employee = \App\Models\Employee::where('id_number', $loginVal)->first();
                if ($employee) {
                    $user = User::where('employee_id', $employee->id)->first();
                }
            }

            if (!$user || !Hash::check($passwordVal, $user->password)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid credentials'
                ], 401);
            }

            /** @var \PHPOpenSourceSaver\JWTAuth\JWTGuard $guard */
            $guard = Auth::guard('api');
            $token = $guard->login($user);
            $user = auth('api')->user();

            if (!$user->canLogin()) {
                Auth::guard('api')->logout();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is deactivated'
                ], 401);
            }

            if (!$user->roles()->exists()) {
                Auth::guard('api')->logout();
                return response()->json([
                    'success' => false,
                    'message' => 'No admin role assigned. Please contact Super Admin.'
                ], 403);
            }

            $user->updateLastLogin($request->ip());

            $this->logActivity(
                'LOGIN',
                'Auth',
                "User {$user->name} ({$user->username}) logged in successfully",
                [
                    'user_id' => $user->id,
                    'username' => $user->username,
                    'email' => $user->email,
                    'ip_address' => $request->ip(),
                ]
            );

            $cookie = cookie(
                'auth_token',
                $token,
                60 * 24 * 7,
                '/',
                null,
                true,  // Secure
                true,  // HttpOnly
                false,
                'lax'
            );

            $user->load([
                'branch.warehouses:id,branch_id,name,code,is_active',
                'warehouse.branch:id,name,code',
                'roles' => function ($query) {
                    $query->select('id', 'name')
                        ->with(['permissions' => function ($query) {
                            $query->select('id', 'name');
                        }]);
                }
            ]);

            if ($user->relationLoaded('roles')) {
                $user->roles->each->makeHidden(['pivot']);
                $user->roles->each(function ($role) {
                    if ($role->relationLoaded('permissions')) {
                        $role->permissions->each->makeHidden(['pivot']);
                    }
                });
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'auth_token' => $token,
                    'token_type' => 'bearer',
                    'expires_in' => config('jwt.ttl') * 60
                ]
            ], 200)->cookie($cookie);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to login',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Admin Logout
     */
    public function logout(Request $request)
    {
        try {
            $user = auth('api')->user();
            $this->logActivity(
                'LOGOUT',
                'Auth',
                $user ? "User {$user->name} ({$user->username}) logged out" : "User logged out",
                [
                    'user_id' => $user?->id,
                    'username' => $user?->username,
                    'ip_address' => $request->ip(),
                ]
            );

            // Logout the user (invalidates the token)
            Auth::guard('api')->logout();

            // Create an expired cookie to remove it from browser
            $cookie = Cookie::forget('auth_token');

            return response()->json([
                'status' => 'success',
                'message' => 'Logout successful'
            ], 200)->withCookie($cookie);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to logout',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated admin user
     */
    public function me()
    {
        try {
            $user = auth('api')->user();

            $user->load([
                'branch.warehouses:id,branch_id,name,code,is_active',
                'warehouse.branch:id,name,code',
                'roles' => function ($query) {
                    $query->select('id', 'name')
                        ->with(['permissions' => function ($query) {
                            $query->select('id', 'name');
                        }]);
                }
            ]);

            if ($user->relationLoaded('roles')) {
                $user->roles->each->makeHidden(['pivot']);
                $user->roles->each(function ($role) {
                    if ($role->relationLoaded('permissions')) {
                        $role->permissions->each->makeHidden(['pivot']);
                    }
                });
            }

            return response()->json([
                'status' => 'success',
                'message' => 'User details fetched successfully',
                'data' => [
                    'user' => $user
                ]
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch user details',
                'error' => $th->getMessage()
            ], 500);
        }
    }

    /**
     * Forgot Password - Generate & Send OTP via Email and/or SMS simultaneously
     */
    public function forgotPassword(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'login' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $loginVal = trim($request->input('login'));

            // Search user by email, phone, or username
            $user = User::where('email', $loginVal)
                ->orWhere('phone', $loginVal)
                ->orWhere('username', $loginVal)
                ->first();

            // Generic response if user not found or deactivated
            if (!$user || !$user->is_active || !$user->can_login) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active user account found with the provided credentials.'
                ], 404);
            }

            // Check if user has reached self-service password change limit
            if ($user->hasReachedPasswordChangeLimit()) {
                $limit = (int) SystemSetting::get('staff_password_change_limit', 3);
                return response()->json([
                    'status' => 'error',
                    'message' => "You have reached the maximum allowed self password change limit ({$limit} times). Please contact a system administrator to reset your account."
                ], 403);
            }

            $hasEmail = !empty($user->email);
            $hasPhone = !empty($user->phone);

            // Validation: User must have at least an email or a phone number registered
            if (!$hasEmail && !$hasPhone) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No email or phone number is registered for this account. Please contact an admin.'
                ], 422);
            }

            // Fetch dynamic OTP expiry time from system settings (default 10 minutes)
            $expiryMinutes = (int) SystemSetting::get('otp_expiry_minutes', 10);
            if ($expiryMinutes <= 0) {
                $expiryMinutes = 10;
            }

            // Generate ONE single 6-digit OTP code and secure reset token
            $otpCode = (string) random_int(100000, 999999);
            $resetToken = Str::random(64);

            $sentChannels = [];
            $maskedDestinations = [];
            $storedChannel = 'email';
            $primaryIdentifier = $hasEmail ? $user->email : $user->phone;

            if ($hasEmail && $hasPhone) {
                $storedChannel = 'both';
                $sentChannels = ['email', 'sms'];
                $maskedDestinations['email'] = $this->maskDestination($user->email, 'email');
                $maskedDestinations['sms'] = $this->maskDestination($user->phone, 'sms');

                // Send same OTP via Email AND SMS simultaneously
                Mail::to($user->email)->send(new PasswordResetOtpMail($user->name, $otpCode, $expiryMinutes));
                $smsService = new SmsService();
                $smsService->sendOtpSms($user->phone, $otpCode);

            } elseif ($hasEmail) {
                $storedChannel = 'email';
                $sentChannels = ['email'];
                $maskedDestinations['email'] = $this->maskDestination($user->email, 'email');

                // Send OTP via Email only
                Mail::to($user->email)->send(new PasswordResetOtpMail($user->name, $otpCode, $expiryMinutes));

            } else {
                $storedChannel = 'sms';
                $sentChannels = ['sms'];
                $maskedDestinations['sms'] = $this->maskDestination($user->phone, 'sms');

                // Send OTP via SMS only
                $smsService = new SmsService();
                $smsService->sendOtpSms($user->phone, $otpCode);
            }

            // Invalidate any previous unverified OTPs for this user
            PasswordOtp::where('user_id', $user->id)->whereNull('verified_at')->delete();

            // Store single OTP record in password_otps table with dynamic expiry
            PasswordOtp::create([
                'user_id' => $user->id,
                'identifier' => $primaryIdentifier,
                'otp_code' => $otpCode,
                'reset_token' => $resetToken,
                'channel' => $storedChannel,
                'expires_at' => now()->addMinutes($expiryMinutes),
            ]);

            $this->logActivity(
                'FORGOT_PASSWORD',
                'Auth',
                "Generated password reset OTP for user {$user->username} via " . implode(' & ', $sentChannels),
                ['user_id' => $user->id, 'channels' => $sentChannels]
            );

            $channelText = count($sentChannels) > 1 ? implode(' and ', $sentChannels) : $sentChannels[0];

            return response()->json([
                'status' => 'success',
                'message' => "Verification OTP code sent successfully to your {$channelText}.",
                'data' => [
                    'channels' => $sentChannels,
                    'masked_destinations' => $maskedDestinations,
                    'reset_token' => $resetToken,
                    'expires_in_minutes' => $expiryMinutes,
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to send password reset OTP code',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Verify OTP Code
     */
    public function verifyOtp(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reset_token' => 'required|string',
                'otp_code' => 'required|string|size:6',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $otpRecord = PasswordOtp::where('reset_token', $request->reset_token)->first();

            if (!$otpRecord || $otpRecord->otp_code !== trim($request->otp_code)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid verification code.'
                ], 400);
            }

            if ($otpRecord->isExpired()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Verification OTP code has expired. Please request a new code.'
                ], 400);
            }

            if ($otpRecord->isUsed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Verification code has already been used.'
                ], 400);
            }

            // Mark OTP as verified
            $otpRecord->update(['verified_at' => now()]);

            $this->logActivity(
                'VERIFY_OTP',
                'Auth',
                "Verified OTP code successfully for user ID {$otpRecord->user_id}"
            );

            return response()->json([
                'status' => 'success',
                'message' => 'OTP verified successfully. You may now reset your password.',
                'data' => [
                    'reset_token' => $otpRecord->reset_token,
                    'identifier' => $otpRecord->identifier,
                ]
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to verify OTP code',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Reset Password using Verified OTP Session Token
     */
    public function resetPassword(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'reset_token' => 'required|string',
                'password' => 'required|string|min:6|confirmed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $otpRecord = PasswordOtp::where('reset_token', $request->reset_token)->first();

            if (!$otpRecord) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid or expired password reset session.'
                ], 400);
            }

            if ($otpRecord->isExpired()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Password reset session has expired. Please request a new code.'
                ], 400);
            }

            if ($otpRecord->isUsed()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Password reset session has already been used.'
                ], 400);
            }

            if (!$otpRecord->isVerified()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'OTP verification code has not been verified yet.'
                ], 400);
            }

            $user = User::find($otpRecord->user_id);

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User account not found.'
                ], 404);
            }

            // Check if user has reached self-service password change limit
            if ($user->hasReachedPasswordChangeLimit()) {
                $limit = (int) SystemSetting::get('staff_password_change_limit', 3);
                return response()->json([
                    'status' => 'error',
                    'message' => "You have reached the maximum allowed self password change limit ({$limit} times). Please contact a system administrator to reset your account."
                ], 403);
            }

            // Reset password
            $user->password = \Illuminate\Support\Facades\Hash::make($request->password);
            $user->increment('password_change_count');
            $user->save();

            // Mark OTP as used
            $otpRecord->update(['used_at' => now()]);

            // Invalidate any other active OTPs for this user
            PasswordOtp::where('user_id', $user->id)->whereNull('used_at')->update(['used_at' => now()]);

            $this->logActivity(
                'RESET_PASSWORD',
                'Auth',
                "Reset password successfully for user {$user->name} ({$user->username})",
                ['user_id' => $user->id]
            );

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Password has been reset successfully. You can now log in with your new password.'
            ], 200);

        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reset password',
                'error' => config('app.debug') ? $th->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Helper to mask email or phone number for user privacy.
     */
    private function maskDestination(string $destination, string $channel): string
    {
        if ($channel === 'email') {
            $parts = explode('@', $destination);
            if (count($parts) === 2) {
                $name = $parts[0];
                $domain = $parts[1];
                $len = strlen($name);
                if ($len <= 2) {
                    $maskedName = substr($name, 0, 1) . '*';
                } else {
                    $maskedName = substr($name, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($name, -1);
                }
                return $maskedName . '@' . $domain;
            }
            return $destination;
        } else {
            $len = strlen($destination);
            if ($len > 4) {
                return substr($destination, 0, 3) . str_repeat('*', $len - 5) . substr($destination, -2);
            }
            return $destination;
        }
    }
}
