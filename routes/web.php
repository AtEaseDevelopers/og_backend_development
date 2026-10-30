<?php

use App\Http\Controllers\EinvoiceBuyerController;
use App\Http\Controllers\Portal\AccountSelectionController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\EnquiryController;
use App\Http\Controllers\Portal\PasswordResetController;
use App\Http\Controllers\Portal\SalespersonLinkController;
use App\Http\Controllers\Portal\TrackingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/track/{token}', TrackingController::class)->name('tracking.show');

Route::get('/einvoice-buyer/{token}', [EinvoiceBuyerController::class, 'show'])->name('einvoice.buyer.show');
Route::post('/einvoice-buyer/{token}', [EinvoiceBuyerController::class, 'store'])->name('einvoice.buyer.store');

Route::prefix('portal')->name('portal.')->group(function () {
    Route::get('order/{token}', SalespersonLinkController::class)->name('salesperson-link');
    Route::get('customer-order/{customer}', [AccountSelectionController::class, 'orderLink'])->name('customer-order-link');

    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verify-email');

    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
        Route::get('register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('register', [AuthController::class, 'register'])->middleware('throttle:5,1');
        Route::post('verify-email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:3,1')->name('verify-email.resend');
        Route::get('forgot-password', [PasswordResetController::class, 'showRequest'])->name('password.request');
        Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
        Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
        Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('select-company', [AccountSelectionController::class, 'show'])->name('select-company');
        Route::post('select-company', [AccountSelectionController::class, 'store'])->name('select-company.store');
        Route::post('select-company/request', [AccountSelectionController::class, 'requestAccess'])->name('select-company.request');
        Route::redirect('select-branch', '/portal/select-company?switch=1')->name('select-branch');
        Route::redirect('select-branch/reset', '/portal/select-company?switch=1')->name('select-branch.reset');

        Route::middleware('portal.context')->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('enquiry/create', [EnquiryController::class, 'create'])->name('enquiry.create');
            Route::post('enquiry', [EnquiryController::class, 'store'])->name('enquiry.store');
            Route::get('enquiry/{enquiry}/pdf', [EnquiryController::class, 'pdf'])->name('enquiry.pdf');
        });
    });
});
