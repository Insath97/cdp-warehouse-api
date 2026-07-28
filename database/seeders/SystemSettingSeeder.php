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
        SystemSetting::where('key', 'sms_notifications_enabled')->delete();

        $settings = [
            'otp_expiry_minutes' => '10',
            'staff_password_change_limit' => '3',
        ];

        foreach ($settings as $key => $value) {
            SystemSetting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
