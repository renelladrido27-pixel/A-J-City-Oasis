<?php

use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AppVersionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookingController;
use App\Http\Controllers\Api\LeaseController;
use App\Http\Controllers\Api\MaintenanceRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\RoomController;
use App\Http\Controllers\Api\RoomTransferController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Webhooks\XenditWebhookController;
use Illuminate\Support\Facades\Route;
//abiiiiiii 

Route::post('/webhooks/xendit', [XenditWebhookController::class, 'handle'])->name('webhooks.xendit');

// Mobile app API 
Route::get('/rooms', [RoomController::class, 'index']);
Route::get('/rooms/{room}', [RoomController::class, 'show']);
Route::post('/rooms/{room}/book', [BookingController::class, 'store'])->middleware('throttle:signup');

// Latest published Android build, so the app can offer an in-app update.
Route::get('/app/version', AppVersionController::class);

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:signup');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'me']);

    // Email verification by 6-digit code, and the profile (so a mistyped email
    // can be corrected) stay reachable before the account is verified.
    Route::post('/email/verify', [EmailVerificationController::class, 'verify'])->middleware('throttle:verify-email');
    Route::post('/email/resend', [EmailVerificationController::class, 'resend'])->middleware('throttle:verify-email');
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'storePhoto']);
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto']);

    Route::middleware('email.verified')->group(function () {
        Route::get('/bookings/{booking}', [BookingController::class, 'show']);
        Route::post('/bookings/{booking}/pay', [BookingController::class, 'pay']);
        Route::post('/bookings/{booking}/check-status', [BookingController::class, 'checkStatus']);

        Route::get('/lease', [LeaseController::class, 'show']);

        Route::get('/payments', [PaymentController::class, 'index']);
        Route::post('/payments/{payment}/pay', [PaymentController::class, 'pay']);
        Route::post('/payments/{payment}/check-status', [PaymentController::class, 'checkStatus']);

        Route::get('/maintenance-requests', [MaintenanceRequestController::class, 'index']);
        Route::post('/maintenance-requests', [MaintenanceRequestController::class, 'store']);

        Route::get('/room-transfers', [RoomTransferController::class, 'index']);
        Route::post('/room-transfers', [RoomTransferController::class, 'store']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead']);

        Route::get('/announcements', [AnnouncementController::class, 'index']);
    });
});
