<?php

use App\Http\Controllers\Api\Driver\AuthController;
use App\Http\Controllers\Api\Driver\BreakBulkController;
use App\Http\Controllers\Api\Driver\CsnController;
use App\Http\Controllers\Api\Driver\DeliveryController;
use App\Http\Controllers\Api\Driver\JobSheetController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/driver')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);

        // Trips (section I): current trip for the day + every trip of the day
        Route::get('job-sheet', [JobSheetController::class, 'show']);
        Route::get('job-sheets', [JobSheetController::class, 'index']);
        Route::post('check-in', [JobSheetController::class, 'checkIn']);

        // Scan-to-claim (section H / J)
        Route::get('csn/{qrToken}', [CsnController::class, 'show']);
        Route::post('csn/claim', [CsnController::class, 'claim']);

        Route::post('deliveries/{deliveryOrder}/complete', [DeliveryController::class, 'complete']);
        Route::post('deliveries/{deliveryOrder}/fail', [DeliveryController::class, 'fail']);
        Route::post('deliveries/{deliveryOrder}/break-bulk', [BreakBulkController::class, 'store']);
    });
});
