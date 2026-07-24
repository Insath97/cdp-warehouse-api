<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

// Root endpoint — API Landing Page
Route::get('/', function () {
    return view('landing', [
        'app_name'    => config('app.name'),
        'app_version' => '1.0.0',
        'app_url'     => config('app.url'),
        'app_env'     => config('app.env'),
    ]);
});

// Comprehensive health check
Route::get('/health', function () {
    $currentDateTime = now();
    $status = [
        'status' => 'healthy',
        'date' => $currentDateTime->toDateString(),
        'time' => $currentDateTime->toTimeString(),
        'service' => 'CDP Web Application System API',
        'components' => []
    ];

    // Check database
    try {
        DB::select('SELECT 1');
        $status['components']['database'] = 'healthy';
    } catch (\Exception $e) {
        $status['components']['database'] = 'unhealthy';
        $status['status'] = 'degraded';
    }

    return response()->json($status);
});
