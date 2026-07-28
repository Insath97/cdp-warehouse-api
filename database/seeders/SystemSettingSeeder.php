<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;

class SystemSettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            'otp_expiry_minutes' => '10',
            'staff_password_change_limit' => '3',
            'company_name' => 'CDP Warehouse Empire',
            'currency' => 'LKR',
            'low_stock_threshold_bags' => '50',
            'sms_notifications_enabled' => '1',
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
