<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title }}</title>
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
                            <p style="margin: 6px 0 0 0; font-size: 13px; color: #FFF345; font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                                Purchase Order Notification
                            </p>
                        </td>
                    </tr>

                    <!-- Body Content -->
                    <tr>
                        <td style="padding: 36px 32px; background-color: #ffffff;">
                            <h2 style="margin: 0 0 12px 0; font-size: 18px; font-weight: 700; color: #111110;">
                                {{ $title }}
                            </h2>
                            <p style="margin: 0 0 24px 0; font-size: 14px; line-height: 1.6; color: #4a4a45;">
                                {{ $messageText }}
                            </p>

                            <!-- PO Details Box -->
                            <div style="background-color: #F7F5F0; border-radius: 12px; padding: 20px; margin-bottom: 24px; border: 1px solid rgba(0,0,0,0.06);">
                                <h3 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #111110; text-transform: uppercase; letter-spacing: 0.5px;">
                                    📄 Purchase Order Details
                                </h3>
                                <table role="presentation" width="100%" style="font-size: 13px; color: #111110;">
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600; width: 150px;">PO Number:</td>
                                        <td style="padding: 6px 0; font-weight: 700; color: #111110;">{{ $po->po_number }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600;">Supplier:</td>
                                        <td style="padding: 6px 0;">{{ $po->supplier->name }} ({{ $po->supplier->code }})</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600;">Warehouse:</td>
                                        <td style="padding: 6px 0;">{{ $po->warehouse->name }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600;">Variety Type:</td>
                                        <td style="padding: 6px 0;">{{ ucfirst($po->variety_type) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600;">Number of Bags:</td>
                                        <td style="padding: 6px 0;">{{ number_format($po->number_of_bags) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600;">Total Weight:</td>
                                        <td style="padding: 6px 0;">{{ number_format($po->total_weights, 2) }} kg</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600;">Price per kg:</td>
                                        <td style="padding: 6px 0; font-weight: 700;">LKR {{ number_format($po->purchase_price_per_kg, 2) }}</td>
                                    </tr>
                                    <tr style="border-top: 1px solid rgba(0,0,0,0.06);">
                                        <td style="padding: 10px 0 6px 0; font-weight: 700; font-size: 14px;">Total Sales Price:</td>
                                        <td style="padding: 10px 0 6px 0; font-weight: 800; font-size: 15px; color: #111110;">LKR {{ number_format($po->total_sales_price, 2) }}</td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600;">PO Status:</td>
                                        <td style="padding: 6px 0;">
                                            <span style="display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 4px; background-color: #111110; color: #ffffff;">
                                                {{ str_replace('_', ' ', $po->status) }}
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td style="padding: 6px 0; font-weight: 600;">Payment Status:</td>
                                        <td style="padding: 6px 0;">
                                            <span style="display: inline-block; padding: 2px 8px; font-size: 11px; font-weight: 700; text-transform: uppercase; border-radius: 4px; background-color: #FFF345; color: #111110;">
                                                {{ $po->payment_status }}
                                            </span>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            @if (!empty($notes))
                            <!-- Note Section -->
                            <div style="background-color: #FFFDE7; border-left: 4px solid #FFF345; border-radius: 4px; padding: 15px; margin-bottom: 24px; font-size: 13px; line-height: 1.5; color: #5d4037;">
                                <strong>💬 Last Bargain Note:</strong> {{ $notes }}
                            </div>
                            @endif

                            @if (isset($bargains) && count($bargains) > 1)
                            <!-- Negotiation Log History -->
                            <div style="margin-bottom: 24px;">
                                <h3 style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #111110; text-transform: uppercase; letter-spacing: 0.5px;">
                                    ⏳ Negotiation History
                                </h3>
                                <table role="presentation" width="100%" style="font-size: 12px; border-collapse: collapse; color: #4a4a45;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid rgba(0,0,0,0.08); font-weight: 700; color: #111110;">
                                            <th align="left" style="padding: 6px 0;">Date</th>
                                            <th align="left" style="padding: 6px 0;">User</th>
                                            <th align="left" style="padding: 6px 0;">Action</th>
                                            <th align="right" style="padding: 6px 0;">Price/kg</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($bargains as $b)
                                        <tr style="border-bottom: 1px solid rgba(0,0,0,0.04);">
                                            <td style="padding: 6px 0;">{{ $b->created_at->format('M d, H:i') }}</td>
                                            <td style="padding: 6px 0;">{{ $b->user->name ?? 'System' }}</td>
                                            <td style="padding: 6px 0; text-transform: capitalize;">{{ str_replace('_', ' ', $b->action) }}</td>
                                            <td align="right" style="padding: 6px 0; font-weight: 600;">LKR {{ number_format($b->purchase_price_per_kg, 2) }}</td>
                                        </tr>
                                        @if($b->note)
                                        <tr>
                                            <td colspan="4" style="padding: 2px 0 8px 10px; font-style: italic; color: #7f7f75;">
                                                "{{ $b->note }}"
                                            </td>
                                        </tr>
                                        @endif
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            @endif

                            @if (!empty($actionUrl))
                            <!-- Call to Action Button -->
                            <div style="text-align: center; margin: 30px 0 10px 0;">
                                <a href="{{ $actionUrl }}" style="display: inline-block; padding: 14px 28px; background-color: #FFF345; color: #111110; text-decoration: none; border-radius: 10px; font-weight: 800; font-size: 14px; border: 1px solid rgba(0,0,0,0.1); box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
                                    {{ $actionText }} &rarr;
                                </a>
                            </div>
                            @endif

                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #F7F5F0; padding: 24px 30px; text-align: center; font-size: 11px; color: #7f7f75; border-top: 1px solid rgba(0,0,0,0.06);">
                            <p style="margin: 0 0 6px 0;">
                                This is an automated notification from {{ config('app.name', 'CDP Warehouse') }}.
                            </p>
                            <p style="margin: 0;">
                                &copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
