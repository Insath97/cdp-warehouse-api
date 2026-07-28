<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welcome to {{ config('app.name', 'CDP Warehouse') }}</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color: #EAE6DA; color: #111110;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: #EAE6DA; padding: 40px 15px;">
        <tr>
            <td align="center">
                <!-- Main Container Card -->
                <table role="presentation" width="100%" style="max-width: 580px; background-color: #ffffff; border-radius: 16px; border: 1px solid rgba(0,0,0,0.08); overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.06);">
                    
                    <!-- Header with Landing Page Dark Theme -->
                    <tr>
                        <td style="background-color: #111110; padding: 32px 30px; text-align: center;">
                            <h1 style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px;">
                                {{ config('app.name', 'CDP Warehouse') }}
                            </h1>
                            <p style="margin: 6px 0 0 0; font-size: 13px; color: #6B6B65; font-weight: 500;">
                                Account Credentials & Access Information
                            </p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px 32px; background-color: #ffffff;">
                            <h2 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #111110;">
                                Welcome, {{ $user['name'] }}!
                            </h2>
                            <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #4a4a45;">
                                Your account has been successfully created. Below are your login credentials and organization access details:
                            </p>

                            <!-- Credentials Box -->
                            <div style="background-color: #F7F5F0; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid rgba(0,0,0,0.06);">
                                <h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #111110; text-transform: uppercase; letter-spacing: 0.5px;">
                                    🔑 Login Credentials
                                </h3>
                                <table role="presentation" width="100%" style="font-size: 13px; color: #111110;">
                                    <tr>
                                        <td style="padding: 4px 0; font-weight: 600; width: 120px;">Username:</td>
                                        <td style="padding: 4px 0;">{{ $user['username'] }}</td>
                                    </tr>
                                    @if (!empty($user['email']))
                                    <tr>
                                        <td style="padding: 4px 0; font-weight: 600;">Email:</td>
                                        <td style="padding: 4px 0;">{{ $user['email'] }}</td>
                                    </tr>
                                    @endif
                                    <tr>
                                        <td style="padding: 4px 0; font-weight: 600;">Password:</td>
                                        <td style="padding: 4px 0; font-family: monospace; font-weight: 700;">{{ $password }}</td>
                                    </tr>
                                    @if (isset($role) && !empty($role))
                                    <tr>
                                        <td style="padding: 4px 0; font-weight: 600;">Assigned Role:</td>
                                        <td style="padding: 4px 0;">{{ $role }}</td>
                                    </tr>
                                    @endif
                                    @if (isset($user['user_scope']) && !empty($user['user_scope']))
                                    <tr>
                                        <td style="padding: 4px 0; font-weight: 600;">Access Scope:</td>
                                        <td style="padding: 4px 0;">{{ ucfirst($user['user_scope']) }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>

                            @if ((isset($branch_name) && !empty($branch_name)) || (isset($warehouse_name) && !empty($warehouse_name)))
                            <!-- Organization Box -->
                            <div style="background-color: #F7F5F0; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid rgba(0,0,0,0.06);">
                                <h3 style="margin: 0 0 12px 0; font-size: 14px; font-weight: 700; color: #111110; text-transform: uppercase; letter-spacing: 0.5px;">
                                    🏢 Organization Assignment
                                </h3>
                                <table role="presentation" width="100%" style="font-size: 13px; color: #111110;">
                                    @if (isset($branch_name) && !empty($branch_name))
                                    <tr>
                                        <td style="padding: 4px 0; font-weight: 600; width: 120px;">Branch:</td>
                                        <td style="padding: 4px 0;">{{ $branch_name }}</td>
                                    </tr>
                                    @endif
                                    @if (isset($warehouse_name) && !empty($warehouse_name))
                                    <tr>
                                        <td style="padding: 4px 0; font-weight: 600;">Warehouse:</td>
                                        <td style="padding: 4px 0;">{{ $warehouse_name }}</td>
                                    </tr>
                                    @endif
                                </table>
                            </div>
                            @endif

                            @if (isset($login_url) && !empty($login_url))
                            <div style="text-align: center; margin: 30px 0 10px 0;">
                                <a href="{{ $login_url }}" style="display: inline-block; padding: 14px 28px; background-color: #FFF345; color: #111110; text-decoration: none; border-radius: 10px; font-weight: 800; font-size: 14px; border: 1px solid rgba(0,0,0,0.1); box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                    Login to Your Account &rarr;
                                </a>
                            </div>
                            @endif
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #F7F5F0; padding: 20px 32px; text-align: center; border-top: 1px solid rgba(0,0,0,0.06);">
                            <p style="margin: 0 0 6px 0; font-size: 12px; color: #6B6B65;">
                                This is an automated notification from {{ config('app.name', 'CDP Warehouse') }}.
                            </p>
                            @if (isset($created_by) && !empty($created_by))
                            <p style="margin: 0; font-size: 12px; color: #6B6B65;">
                                Created by: {{ $created_by }}
                            </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
