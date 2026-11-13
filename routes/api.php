<?php

use App\Http\Controllers\Api\ContractController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\PropertyController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - KosanHub
|--------------------------------------------------------------------------
|
| Prefix otomatis: /api
| Contoh: GET /api/properties
|
*/

// ========== Property ==========
Route::apiResource('properties', PropertyController::class);

// ========== Room ==========
Route::apiResource('rooms', RoomController::class);

// ========== Tenant ==========
Route::apiResource('tenants', TenantController::class);

// ========== Contract ==========
Route::get('contracts', [ContractController::class, 'index']);
Route::post('contracts', [ContractController::class, 'store']);
Route::get('contracts/{contract}', [ContractController::class, 'show']);
Route::post('contracts/{contract}/end', [ContractController::class, 'end']); // checkout
Route::delete('contracts/{contract}', [ContractController::class, 'destroy']);

// ========== Invoice ==========
Route::get('invoices', [InvoiceController::class, 'index']);
Route::get('invoices/overdue', [InvoiceController::class, 'overdue']);
Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
Route::post('invoices/generate', [InvoiceController::class, 'generate']);
Route::post('invoices/generate-monthly', [InvoiceController::class, 'generateMonthly']);
Route::post('invoices/{invoice}/pay', [InvoiceController::class, 'pay']);