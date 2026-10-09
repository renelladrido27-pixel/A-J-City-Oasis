import 'package:flutter/material.dart';

import '../models/announcement.dart';
import '../models/app_notification.dart';
import '../models/booking_summary.dart';
import '../models/lease.dart';
import '../models/maintenance_request.dart';
import '../models/payment_record.dart';
import '../models/room.dart';
import '../models/tenant_profile.dart';
import '../models/transfer_request.dart';
import '../services/api_client.dart';

/// Draft data collected on the room-booking screen (C3) before the combined
/// account+booking API call fires.
class BookingDraft {
  final Room room;
  BookingDraft(this.room);
}

/// Single in-memory source of truth, now backed by the real Laravel API
/// (routes/api.php) via [ApiClient] instead of mock data. Screens only ever
/// read/call through this class, so none of them needed to change shape —
/// only these method bodies did.
class AppState extends ChangeNotifier {
  final ApiClient _api = ApiClient();

  bool isLoggedIn = false;
  bool isBootstrapping = true;

  TenantProfile? profile;
  Lease? lease;
  BookingDraft? draft;
  BookingSummary? currentBooking;

  List<Room> availableRooms = [];
  List<PaymentRecord> paymentHistory = [];

  /// Bills still owed, soonest due first.
  List<PaymentRecord> unpaidPayments = [];
  double outstandingBalance = 0;
  DateTime? nextDueDate;
  int? nextDuePaymentId;
  List<MaintenanceRequest> maintenanceRequests = [];
  List<TransferRequest> transferRequests = [];
  List<Room> transferableRooms = [];
  List<AppNotification> notifications = [];
  List<Announcement> announcements = [];

  /// Checks for a stored token and restores the session on app start.
  Future<void> bootstrap() async {
    final token = await _api.token;
    if (token != null) {
      try {
        final res = await _api.get('/user');
        profile = TenantProfile.fromJson(res['user']);
        isLoggedIn = true;
        if (!needsEmailVerification) await refreshAll();
      } catch (_) {
        await _api.setToken(null);
        isLoggedIn = false;
      }
    }
    isBootstrapping = false;
    notifyListeners();
  }

  Future<void> loadRooms() async {
    final res = await _api.get('/rooms');
    availableRooms = (res['rooms'] as List)
        .map((e) => Room.fromJson(e as Map<String, dynamic>))
        .toList();
    notifyListeners();
  }

  void startBooking(Room room) {
    draft = BookingDraft(room);
    currentBooking = null;
    notifyListeners();
  }

  Future<void> login(String email, String password) async {
    final res = await _api.post('/login', {
      'email': email,
      'password': password,
    });
    await _api.setToken(res['token'] as String);
    profile = TenantProfile.fromJson(res['user']);
    isLoggedIn = true;
    if (needsEmailVerification) {
      notifyListeners();
    } else {
      await refreshAll();
    }
  }

  /// True while the signed-in account still has to enter its emailed code.
  bool get needsEmailVerification =>
      isLoggedIn && profile != null && !profile!.emailVerified;

  /// Creates a tenant account (C2's Sign up tab, and the first step of the
  /// room-booking form). The server emails a 6-digit code; nothing else is
  /// available until [verifyEmail] succeeds.
  Future<void> signUp({
    required String firstName,
    required String middleName,
    required String lastName,
    required String email,
    required String phone,
    required String password,
  }) async {
    final res = await _api.post('/register', {
      'first_name': firstName,
      'middle_name': middleName,
      'last_name': lastName,
      'email': email,
      'phone': phone,
      'password': password,
      'password_confirmation': password,
    });
    await _api.setToken(res['token'] as String);
    profile = TenantProfile.fromJson(res['user']);
    isLoggedIn = true;
    notifyListeners();
  }

  Future<void> verifyEmail(String code) async {
    await _api.post('/email/verify', {'code': code});
    profile?.emailVerified = true;
    await refreshAll();
  }

  Future<void> resendVerificationCode() => _api.post('/email/resend');

  /// Books the drafted room as the signed-in, verified tenant. Mirrors web's
  /// authenticated BookingController::store.
  Future<void> submitBookingForCurrentUser({
    required DateTime moveInDate,
  }) async {
    final room = draft?.room;
    if (room == null) return;

    final res = await _api.post('/rooms/${room.id}/book', {
      'move_in_date': _isoDate(moveInDate),
    });
    currentBooking = BookingSummary.fromJson(res['booking']);
    notifyListeners();
  }

  /// Returns the raw API result so the screen can react to 'paid' vs.
  /// 'redirect' (real Xendit checkout needs to open invoice_url).
  Future<Map<String, dynamic>> completeBookingPayment() async {
    final booking = currentBooking;
    if (booking == null) return {'status': 'error'};

    final res = await _api.post('/bookings/${booking.id}/pay');
    if (res['status'] == 'paid') {
      isLoggedIn = true;
      draft = null;
      currentBooking = null;
      await refreshAll();
    }
    return res;
  }

  Future<Map<String, dynamic>> checkBookingPaymentStatus() async {
    final booking = currentBooking;
    if (booking == null) return {'status': 'error'};

    final res = await _api.post('/bookings/${booking.id}/check-status');
    if (res['status'] == 'paid') {
      isLoggedIn = true;
      draft = null;
      currentBooking = null;
      await refreshAll();
    }
    return res;
  }

  Future<void> logout() async {
    try {
      await _api.post('/logout');
    } catch (_) {
      // Best-effort — clear local state regardless.
    }
    await _api.setToken(null);
    isLoggedIn = false;
    profile = null;
    lease = null;
    paymentHistory = [];
    unpaidPayments = [];
    outstandingBalance = 0;
    nextDueDate = null;
    nextDuePaymentId = null;
    maintenanceRequests = [];
    transferRequests = [];
    transferableRooms = [];
    notifications = [];
    announcements = [];
    notifyListeners();
  }

  /// Refetches everything the authenticated screens read — called after
  /// login/signup/payment completion so screens keep reading synchronous
  /// fields instead of each needing their own FutureBuilder.
  Future<void> refreshAll() async {
    await Future.wait([
      _loadLease(),
      _loadPayments(),
      _loadMaintenanceRequests(),
      _loadTransfers(),
      _loadNotifications(),
      _loadAnnouncements(),
    ]);
    notifyListeners();
  }

  Future<void> _loadLease() async {
    final res = await _api.get('/lease');
    lease = res['lease'] == null
        ? null
        : Lease.fromJson(res['lease'] as Map<String, dynamic>);
  }

  Future<void> _loadPayments() async {
    final res = await _api.get('/payments');
    outstandingBalance = (res['outstanding_balance'] as num).toDouble();
    nextDueDate = res['next_due_date'] == null
        ? null
        : DateTime.parse(res['next_due_date'] as String);
    nextDuePaymentId = res['next_due_payment_id'] as int?;
    final all = (res['history'] as List)
        .map((e) => PaymentRecord.fromJson(e as Map<String, dynamic>))
        .toList();
    paymentHistory = all.where((p) => p.status == 'Paid').toList();
    unpaidPayments = all.where((p) => p.isUnpaid).toList()
      ..sort((a, b) => a.dueDate.compareTo(b.dueDate));
  }

  /// The bill due soonest, if anything is owed.
  PaymentRecord? get nextDuePayment =>
      unpaidPayments.isEmpty ? null : unpaidPayments.first;

  Future<void> _loadMaintenanceRequests() async {
    final res = await _api.get('/maintenance-requests');
    maintenanceRequests = (res['requests'] as List)
        .map((e) => MaintenanceRequest.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> _loadTransfers() async {
    final res = await _api.get('/room-transfers');
    transferableRooms = (res['available_rooms'] as List)
        .map((e) => Room.fromJson(e as Map<String, dynamic>))
        .toList();
    transferRequests = (res['requests'] as List)
        .map((e) => TransferRequest.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<void> _loadNotifications() async {
    final res = await _api.get('/notifications');
    notifications = (res['notifications'] as List)
        .map((e) => AppNotification.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  int get unreadNotificationCount =>
      notifications.where((n) => !n.isRead).length;

  /// Quiet background check for the Alerts tab (the website gets these pushed
  /// live; the app asks every so often instead). Returns the notifications
  /// that weren't there before, newest first, so the shell can announce them.
  Future<List<AppNotification>> pollNotifications() async {
    if (!isLoggedIn || needsEmailVerification) return const [];
    final known = notifications.map((n) => n.id).toSet();
    try {
      await _loadNotifications();
    } catch (_) {
      return const [];
    }
    notifyListeners();
    return notifications
        .where((n) => !known.contains(n.id) && !n.isRead)
        .toList();
  }

  /// Marks one notification read/unread. The list changes straight away and
  /// is put back if the server refuses.
  Future<void> setNotificationRead(AppNotification n, bool read) async {
    if (n.isRead == read) return;
    _replaceNotification(n.copyWith(isRead: read));
    try {
      await _api.post('/notifications/${n.id}/${read ? 'read' : 'unread'}');
    } catch (_) {
      _replaceNotification(n);
      rethrow;
    }
  }

  Future<void> markAllNotificationsRead() async {
    final before = notifications;
    notifications = [for (final n in before) n.copyWith(isRead: true)];
    notifyListeners();
    try {
      await _api.post('/notifications/mark-all-read');
    } catch (_) {
      notifications = before;
      notifyListeners();
      rethrow;
    }
  }

  void _replaceNotification(AppNotification updated) {
    notifications = [
      for (final n in notifications) n.id == updated.id ? updated : n,
    ];
    notifyListeners();
  }

  Future<void> _loadAnnouncements() async {
    final res = await _api.get('/announcements');
    announcements = (res['announcements'] as List)
        .map((e) => Announcement.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  // Public per-section refreshers for pull-to-refresh — cheaper than refreshAll()
  // since each screen only needs to re-fetch its own data (e.g. an admin might
  // generate a rent payment or update a maintenance request status externally).
  Future<void> refreshLease() async {
    await _loadLease();
    notifyListeners();
  }

  Future<void> refreshPayments() async {
    await _loadPayments();
    notifyListeners();
  }

  Future<void> refreshMaintenanceRequests() async {
    await _loadMaintenanceRequests();
    notifyListeners();
  }

  Future<void> refreshTransfers() async {
    await _loadTransfers();
    notifyListeners();
  }

  /// Pull-to-refresh on the Alerts tab — also refetches announcements, since
  /// its News filter lives on the same screen.
  Future<void> refreshNotifications() async {
    await Future.wait([_loadNotifications(), _loadAnnouncements()]);
    notifyListeners();
  }

  Future<void> refreshProfile() async {
    final res = await _api.get('/profile');
    profile = TenantProfile.fromJson(res['user']);
    notifyListeners();
  }

  /// Starts (or resumes) checkout for one bill. Returns the raw API result so
  /// the screen can react to 'paid' vs. 'redirect' (open invoice_url).
  Future<Map<String, dynamic>> payPayment(int id) async {
    final res = await _api.post('/payments/$id/pay');
    if (res['status'] == 'paid') {
      await _loadPayments();
      notifyListeners();
    }
    return res;
  }

  Future<Map<String, dynamic>> checkPaymentStatus(int id) async {
    final res = await _api.post('/payments/$id/check-status');
    if (res['status'] == 'paid') {
      await _loadPayments();
      notifyListeners();
    }
    return res;
  }

  /// [photoPath] is a local file from the camera/gallery picker; sent as the
  /// same `photo` field the web form uploads.
  Future<void> submitMaintenanceRequest(
    String issueType,
    String description, {
    String? photoPath,
  }) async {
    await _api.postMultipart(
      '/maintenance-requests',
      fields: {'category': issueType, 'description': description},
      files: {'photo': ?photoPath},
    );
    await _loadMaintenanceRequests();
    notifyListeners();
  }

  /// The tenant confirms the work on one of their requests is done.
  Future<void> resolveMaintenanceRequest(int id) async {
    await _api.post('/maintenance-requests/$id/resolve');
    await _loadMaintenanceRequests();
    notifyListeners();
  }

  /// The tenant withdraws a request that nobody has been assigned to yet.
  Future<void> cancelMaintenanceRequest(int id) async {
    await _api.post('/maintenance-requests/$id/cancel');
    await _loadMaintenanceRequests();
    notifyListeners();
  }

  Future<void> requestTransfer(Room toRoom, String reason) async {
    await _api.post('/room-transfers', {
      'to_room_id': int.parse(toRoom.id),
      'reason': reason,
    });
    await _loadTransfers();
    notifyListeners();
  }

  /// Changing the email un-verifies the account until the new address's code
  /// is entered — check [needsEmailVerification] afterwards.
  Future<void> updateProfile({
    required String firstName,
    required String middleName,
    required String lastName,
    required String email,
    required String phone,
  }) async {
    final res = await _api.put('/profile', {
      'first_name': firstName,
      'middle_name': middleName,
      'last_name': lastName,
      'email': email,
      'phone': phone,
    });
    profile = TenantProfile.fromJson(res['user']);
    notifyListeners();
  }

  /// Uploads a new profile picture ([path] is a local file from the picker).
  Future<void> uploadProfilePhoto(String path) async {
    final res = await _api.postMultipart(
      '/profile/photo',
      fields: const {},
      files: {'photo': path},
    );
    profile = TenantProfile.fromJson(res['user']);
    notifyListeners();
  }

  Future<void> removeProfilePhoto() async {
    final res = await _api.delete('/profile/photo');
    profile = TenantProfile.fromJson(res['user']);
    notifyListeners();
  }

  /// Changes the password; the server checks [currentPassword] first.
  Future<void> changePassword({
    required String currentPassword,
    required String newPassword,
  }) async {
    final p = profile!;
    await _api.put('/profile', {
      'first_name': p.firstName,
      'middle_name': p.middleName,
      'last_name': p.lastName,
      'email': p.email,
      'phone': p.phone,
      'current_password': currentPassword,
      'password': newPassword,
      'password_confirmation': newPassword,
    });
  }

  static String _isoDate(DateTime d) =>
      '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}-${d.day.toString().padLeft(2, '0')}';
}

class AppStateScope extends InheritedNotifier<AppState> {
  const AppStateScope({
    super.key,
    required AppState super.notifier,
    required super.child,
  });

  static AppState of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AppStateScope>();
    assert(scope != null, 'AppStateScope not found in context');
    return scope!.notifier!;
  }
}
