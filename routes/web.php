<?php

use App\Http\Controllers\Admin\AnnouncementController as AdminAnnouncementController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\OccupancyController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LeaseController;
use App\Http\Controllers\MaintenanceRequestController;
use App\Http\Controllers\MoveOutController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomTransferController;
use App\Http\Controllers\UtilityBillController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

// Guest
Route::get('/rooms', [RoomController::class, 'browse'])->name('rooms.browse');
Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
Route::get('/payments/return-to-app', [PaymentController::class, 'returnToApp'])->name('payments.return-to-app');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/payments/success', [PaymentController::class, 'success'])->name('payments.success');
    Route::get('/payments/failure', [PaymentController::class, 'failure'])->name('payments.failure');
    Route::post('/payments/{payment}/pay', [PaymentController::class, 'pay'])->name('payments.pay');
    Route::post('/payments/{payment}/check-status', [PaymentController::class, 'checkStatus'])->name('payments.check-status');
    Route::get('/payments/{payment}/receipt', [PaymentController::class, 'receipt'])->name('payments.receipt');
    Route::get('/payments/fake-complete/{reference}', [PaymentController::class, 'fakeComplete'])->name('payments.fake-complete');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/{notification}/unread', [NotificationController::class, 'markUnread'])->name('notifications.unread');
    Route::post('/notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.mark-all-read');
    Route::get('/notifications/{notification}/open', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/photo', [ProfileController::class, 'destroyPhoto'])->name('profile.photo.destroy');
});

// Admin
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::get('/occupancy', [OccupancyController::class, 'index'])->name('occupancy.index');
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/export', [ReportController::class, 'export'])->name('reports.export');

    Route::get('/announcements', [AdminAnnouncementController::class, 'index'])->name('announcements.index');
    Route::post('/announcements', [AdminAnnouncementController::class, 'store'])->name('announcements.store');
    Route::delete('/announcements/{announcement}', [AdminAnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::resource('users', UserController::class)->except(['show', 'destroy']);
    Route::post('/users/{user}/toggle-active', [UserController::class, 'toggleActive'])->name('users.toggle-active');

    Route::resource('properties', PropertyController::class)->except(['show']);
    Route::resource('rooms', RoomController::class)->except(['show']);
    Route::delete('/rooms/{room}/images/{image}', [RoomController::class, 'destroyImage'])->name('rooms.images.destroy');

    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('/leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');
    Route::post('/leases/{lease}/generate-rent', [PaymentController::class, 'generateRent'])->name('leases.generate-rent');
    Route::post('/leases/{lease}/document', [LeaseController::class, 'uploadDocument'])->name('leases.document.store');
    Route::delete('/leases/{lease}/document', [LeaseController::class, 'destroyDocument'])->name('leases.document.destroy');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::post('/payments/{payment}/record-manual', [PaymentController::class, 'recordManual'])->name('payments.record-manual');

    Route::get('/utility-bills', [UtilityBillController::class, 'index'])->name('utility-bills.index');
    Route::get('/utility-bills/create', [UtilityBillController::class, 'createAny'])->name('utility-bills.create-any');
    Route::post('/utility-bills', [UtilityBillController::class, 'storeAny'])->name('utility-bills.store-any');
    Route::get('/leases/{lease}/utility-bills/create', [UtilityBillController::class, 'create'])->name('utility-bills.create');
    Route::post('/leases/{lease}/utility-bills', [UtilityBillController::class, 'store'])->name('utility-bills.store');

    Route::get('/maintenance-requests', [MaintenanceRequestController::class, 'index'])->name('maintenance-requests.index');
    Route::put('/maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'update'])->name('maintenance-requests.update');

    Route::get('/room-transfers', [RoomTransferController::class, 'index'])->name('room-transfers.index');
    Route::post('/room-transfers/{roomTransfer}/approve', [RoomTransferController::class, 'approve'])->name('room-transfers.approve');
    Route::post('/room-transfers/{roomTransfer}/reject', [RoomTransferController::class, 'reject'])->name('room-transfers.reject');

    Route::post('/move-outs/{moveOut}/complete', [MoveOutController::class, 'complete'])->name('move-outs.complete');
});

// Staff
Route::middleware(['auth', 'role:admin,staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/maintenance-requests', [MaintenanceRequestController::class, 'index'])->name('maintenance.index');
    Route::put('/maintenance-requests/{maintenanceRequest}', [MaintenanceRequestController::class, 'update'])->name('maintenance.update');
});

// Tenant
Route::middleware(['auth', 'role:tenant'])->prefix('tenant')->name('tenant.')->group(function () {
    Route::get('/dashboard', [LeaseController::class, 'index'])->name('dashboard');

    Route::get('/bookings', [BookingController::class, 'index'])->name('bookings.index');
    Route::get('/rooms/{room}/book', [BookingController::class, 'create'])->name('bookings.create');
    Route::post('/rooms/{room}/book', [BookingController::class, 'store'])->name('bookings.store');
    Route::get('/bookings/{booking}', [BookingController::class, 'show'])->name('bookings.show');
    Route::put('/bookings/{booking}/move-in-date', [BookingController::class, 'selectMoveInDate'])->name('bookings.move-in-date');
    Route::post('/bookings/{booking}/cancel', [BookingController::class, 'cancel'])->name('bookings.cancel');

    Route::get('/leases', [LeaseController::class, 'index'])->name('leases.index');
    Route::get('/leases/{lease}', [LeaseController::class, 'show'])->name('leases.show');

    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');

    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');

    Route::get('/maintenance-requests', [MaintenanceRequestController::class, 'index'])->name('maintenance-requests.index');
    Route::get('/leases/{lease}/maintenance-requests/create', [MaintenanceRequestController::class, 'create'])->name('maintenance-requests.create');
    Route::post('/leases/{lease}/maintenance-requests', [MaintenanceRequestController::class, 'store'])->name('maintenance-requests.store');

    Route::get('/leases/{lease}/transfer', [RoomTransferController::class, 'create'])->name('room-transfers.create');
    Route::post('/leases/{lease}/transfer', [RoomTransferController::class, 'store'])->name('room-transfers.store');

    Route::get('/leases/{lease}/move-out', [MoveOutController::class, 'create'])->name('move-outs.create');
    Route::post('/leases/{lease}/move-out', [MoveOutController::class, 'store'])->name('move-outs.store');
});
