<?php

use App\Http\Controllers\EinvoiceBuyerController;
use App\Http\Controllers\Portal\AccountSelectionController;
use App\Http\Controllers\Portal\AuthController;
use App\Http\Controllers\Portal\ConsignmentController;
use App\Http\Controllers\Portal\DashboardController;
use App\Http\Controllers\Portal\DocumentController;
use App\Http\Controllers\Portal\EnquiryController;
use App\Http\Controllers\Portal\OrderHistoryController;
use App\Http\Controllers\Portal\PasswordResetController;
use App\Http\Controllers\Portal\PaymentSubmissionController;
use App\Http\Controllers\Portal\ProfileController;
use App\Http\Controllers\Portal\QuotationController;
use App\Http\Controllers\Portal\SalespersonLinkController;
use App\Http\Controllers\Portal\TrackingController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::get('/track/{token}', TrackingController::class)->name('tracking.show');

Route::get('/einvoice-buyer/{token}', [EinvoiceBuyerController::class, 'show'])->name('einvoice.buyer.show');
Route::post('/einvoice-buyer/{token}', [EinvoiceBuyerController::class, 'store'])->name('einvoice.buyer.store');

// the portal's own login guard (portal.guard), so that it and the admin panel do not share one login
Route::prefix('portal')->name('portal.')->middleware('portal.guard')->group(function () {
    Route::get('order/{token}', SalespersonLinkController::class)->name('salesperson-link');
    Route::get('customer-order/{customer}', [AccountSelectionController::class, 'orderLink'])->name('customer-order-link');

    Route::get('verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verify-email');

    Route::middleware('guest:portal')->group(function () {
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

    Route::middleware('auth:portal')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('select-company', [AccountSelectionController::class, 'show'])->name('select-company');
        Route::post('select-company', [AccountSelectionController::class, 'store'])->name('select-company.store');
        Route::post('select-company/request', [AccountSelectionController::class, 'requestAccess'])->name('select-company.request');
        Route::redirect('select-branch', '/portal/select-company?switch=1')->name('select-branch');
        Route::redirect('select-branch/reset', '/portal/select-company?switch=1')->name('select-branch.reset');

        Route::middleware('portal.context')->group(function () {
            Route::get('/', DashboardController::class)->name('dashboard');
            Route::get('orders', OrderHistoryController::class)->name('orders');
            Route::get('enquiry/create', [EnquiryController::class, 'create'])->name('enquiry.create');
            Route::get('enquiry/price', [EnquiryController::class, 'price'])->middleware('throttle:120,1')->name('enquiry.price');
            Route::post('enquiry', [EnquiryController::class, 'store'])->name('enquiry.store');
            Route::get('enquiry/{enquiry}/pdf', [EnquiryController::class, 'pdf'])->name('enquiry.pdf');
            Route::get('quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
            Route::post('quotations/{quotation}/confirm', [QuotationController::class, 'confirm'])->name('quotations.confirm');
            Route::post('quotations/{quotation}/reject', [QuotationController::class, 'reject'])->name('quotations.reject');
            Route::post('quotations/{quotation}/amend', [QuotationController::class, 'requestAmendment'])->name('quotations.amend');
            Route::post('quotations/{quotation}/switch-to-cash', [QuotationController::class, 'switchToCash'])->name('quotations.switch-to-cash');
            Route::post('quotations/{quotation}/payments', [PaymentSubmissionController::class, 'store'])->name('quotations.payments.store');
            Route::post('quotations/{quotation}/payments/counter', [PaymentSubmissionController::class, 'counter'])->name('quotations.payments.counter');
            Route::post('quotations/{quotation}/payments/online', [PaymentSubmissionController::class, 'online'])->name('quotations.payments.online');
            Route::get('online-payments/{reference}/gateway', [PaymentSubmissionController::class, 'gateway'])->name('online-payments.gateway');
            Route::post('online-payments/{reference}/result', [PaymentSubmissionController::class, 'gatewayResult'])->name('online-payments.result');

            Route::get('deliveries', [ConsignmentController::class, 'index'])->name('csns.index');
            Route::get('deliveries/{csn}', [ConsignmentController::class, 'show'])->name('csns.show');

            Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
            Route::get('documents/invoices/{invoice}', [DocumentController::class, 'invoice'])->name('documents.invoice');
            Route::get('documents/einvoices/{invoice}', [DocumentController::class, 'einvoice'])->name('documents.einvoice');
            Route::get('documents/receipts/{receipt}', [DocumentController::class, 'receipt'])->name('documents.receipt');
            Route::get('documents/proformas/{proforma}', [DocumentController::class, 'proforma'])->name('documents.proforma');
            Route::get('statement', [DocumentController::class, 'statement'])->name('statement');
            Route::get('statement/pdf', [DocumentController::class, 'statementPdf'])->name('statement.pdf');

            Route::get('profile', [ProfileController::class, 'show'])->name('profile');
            Route::post('profile/company', [ProfileController::class, 'updateCompany'])->name('profile.company');
            Route::post('profile/einvoice', [ProfileController::class, 'updateEinvoice'])->name('profile.einvoice');
            Route::post('profile/notifications', [ProfileController::class, 'updateNotifications'])->name('profile.notifications');
            Route::post('profile/pics', [ProfileController::class, 'storePic'])->name('profile.pics.store');
            Route::put('profile/pics/{pic}', [ProfileController::class, 'updatePic'])->name('profile.pics.update');
            Route::delete('profile/pics/{pic}', [ProfileController::class, 'destroyPic'])->name('profile.pics.destroy');
            Route::post('profile/addresses', [ProfileController::class, 'storeAddress'])->name('profile.addresses.store');
            Route::put('profile/addresses/{address}', [ProfileController::class, 'updateAddress'])->name('profile.addresses.update');
            Route::delete('profile/addresses/{address}', [ProfileController::class, 'destroyAddress'])->name('profile.addresses.destroy');
        });
    });
});
