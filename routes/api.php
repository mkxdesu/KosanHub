<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();
        $dbStatus = 'connected';
    } catch (\Exception $e) {
        $dbStatus = 'disconnected';
    }

    return response()->json([
        'status' => 'ok',
        'database' => $dbStatus,
        'timestamp' => now()->toISOString(),
    ], $dbStatus === 'connected' ? 200 : 503);
});