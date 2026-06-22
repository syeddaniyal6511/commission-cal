<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CommissionController;
use App\Http\Controllers\ContractController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FormulaController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/dashboard/stats',  [DashboardController::class, 'stats']);

    Route::post('/logout',          [AuthController::class, 'logout']);
    Route::get('/profile',          [AuthController::class, 'profile']);
    Route::put('/profile',    [AuthController::class, 'updateProfile']);
    Route::put('/password',   [AuthController::class, 'updatePassword']);

    Route::apiResource('contracts', ContractController::class);
    Route::post('/contracts/{contract}/calculate',     [CommissionController::class, 'calculate']);
    Route::get('/contracts/{contract}/calculations',  [CommissionController::class, 'history']);

    Route::get('/formulas',                    [FormulaController::class, 'index']);
    Route::post('/formulas',                   [FormulaController::class, 'store']);
    Route::get('/formulas/{formula}/simulate',  [FormulaController::class, 'simulate']);
    Route::post('/formulas/{formula}/activate', [FormulaController::class, 'activate']);
});
