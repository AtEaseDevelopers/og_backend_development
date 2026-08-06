<?php

use App\Http\Controllers\EinvoiceBuyerController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\EnquiryController;
use App\Http\Controllers\Portal\QuotationController;
use App\Http\Controllers\Portal\TrackingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/track/{token}', TrackingController::class)->name('tracking.show');

Route::get('/einvoice-buyer/{token}', [EinvoiceBuyerController::class, 'show'])->name('einvoice.buyer.show');
Route::post('/einvoice-buyer/{token}', [EinvoiceBuyerController::class, 'store'])->name('einvoice.buyer.store');

Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login']);
        Route::get('register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('register', [AuthController::class, 'register']);
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::get('/', DashboardController::class)->name('dashboard');
        Route::get('enquiry/create', [EnquiryController::class, 'create'])->name('enquiry.create');
        Route::post('enquiry', [EnquiryController::class, 'store'])->name('enquiry.store');
        Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
        Route::post('quotations/{quotation}/confirm', [QuotationController::class, 'confirm'])->name('quotations.confirm');
        Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
        Route::post('quotations/{quotation}/amend', [QuotationController::class, 'requestAmendment'])->name('quotations.amend');
    });
});
