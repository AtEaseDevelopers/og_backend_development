<?php

use App\Http\Controllers\Api\Driver\AuthController;
use App\Http\Controllers\Api\Driver\BreakBulkController;
use App\Http\Controllers\Api\Driver\CsnController;
use App\Http\Controllers\Api\Driver\DeliveryController;
use App\Http\Controllers\Api\Driver\JobSheetController;
use App\Http\Controllers\Api\Driver\MetaController;
use App\Http\Controllers\Api\Driver\ScanValidationController;
use App\Http\Controllers\Api\Driver\UploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/driver')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('meta', MetaController::class);

        // Trips (section I): current trip for the day + every trip of the day
        Route::get('job-sheet', [JobSheetController::class, 'show']);
        Route::get('job-sheets', [JobSheetController::class, 'index']);
        Route::get('job-sheets/{jobSheet}', [JobSheetController::class, 'detail']);
        Route::post('check-in', [JobSheetController::class, 'checkIn']);

        // Consignment barcode validation before delivery (section O)
        Route::post('job-sheets/{jobSheet}/scan-check', [ScanValidationController::class, 'check']);
        Route::post('job-sheets/{jobSheet}/scan-validation', [ScanValidationController::class, 'store']);

        // Scan-to-claim (section H / J)
        Route::get('csn/{qrToken}', [CsnController::class, 'show']);
        Route::post('csn/claim', [CsnController::class, 'claim']);

        Route::post('uploads', [UploadController::class, 'store']);

        Route::post('deliveries/{deliveryOrder}/pickup', [DeliveryController::class, 'pickup']);
        Route::post('deliveries/{deliveryOrder}/handover', [DeliveryController::class, 'handover']);
        Route::post('deliveries/{deliveryOrder}/complete', [DeliveryController::class, 'complete']);
        Route::post('deliveries/{deliveryOrder}/fail', [DeliveryController::class, 'fail']);
        Route::post('deliveries/{deliveryOrder}/break-bulk', [BreakBulkController::class, 'store']);

        Route::get('failed-deliveries', [DeliveryController::class, 'failures']);
        Route::get('break-bulks', [BreakBulkController::class, 'index']);
    });
});
