<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('invoices');
});

Route::get('/health', function () {
    try {
        DB::connection()->getPdo();

        return response()->json([
            'status' => 'ok',
            'database' => 'connected',
        ]);
    } catch (PDOException $exception) {
        return response()->json([
            'status' => 'ok',
            'database' => 'disconnected',
        ]);
    }
});
