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
        await refreshAll();
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
    await refreshAll();
  }

  /// Standalone sign-up (C2's Sign up tab) — no room attached. Booking a room
  /// creates its own account inline instead (see [submitBookingAndAccount]).
  Future<void> signUp({
    required String name,
    required String email,
    required String phone,
    required String password,
  }) async {
    final res = await _api.post('/register', {
      'name': name,
      'email': email,
      'phone': phone,
      'password': password,
      'password_confirmation': password,
    });
    await _api.setToken(res['token'] as String);
    profile = TenantProfile.fromJson(res['user']);
    isLoggedIn = true;
    await refreshAll();
  }

  Future<void> submitBookingAndAccount({
    required String fullName,
    required String email,
    required String phone,
    required String password,
    required DateTime moveInDate,
  }) async {
    final room = draft?.room;
    if (room == null) return;

    final res = await _api.post('/rooms/${room.id}/book', {
      'name': fullName,
      'email': email,
      'phone': phone,
      'password': password,
      'password_confirmation': password,
      'move_in_date': _isoDate(moveInDate),
    });
    await _api.setToken(res['token'] as String);
    profile = TenantProfile.fromJson(res['user']);
    currentBooking = BookingSummary.fromJson(res['booking']);
    notifyListeners();
  }

  /// Books as the already-signed-in tenant (e.g. via C2's standalone Sign up)
  /// — no account-creation fields needed since the session token already
  /// identifies them. Mirrors web's authenticated BookingController::store.
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
  Future<Map<String, dynamic>> completeBookingPayment(String method) async {
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
    paymentHistory = (res['history'] as List)
        .map((e) => PaymentRecord.fromJson(e as Map<String, dynamic>))
        .where((p) => p.status == 'Paid')
        .toList();
  }

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

  Future<Map<String, dynamic>> payOutstanding() async {
    final id = nextDuePaymentId;
    if (id == null) return {'status': 'error'};

    final res = await _api.post('/payments/$id/pay');
    if (res['status'] == 'paid') {
      await _loadPayments();
      notifyListeners();
    }
    return res;
  }

  Future<Map<String, dynamic>> checkOutstandingPaymentStatus() async {
    final id = nextDuePaymentId;
    if (id == null) return {'status': 'error'};

    final res = await _api.post('/payments/$id/check-status');
    if (res['status'] == 'paid') {
      await _loadPayments();
      notifyListeners();
    }
    return res;
  }

  Future<void> submitMaintenanceRequest(
    String issueType,
    String description,
  ) async {
    await _api.post('/maintenance-requests', {
      'category': issueType,
      'description': description,
    });
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

  Future<void> updateProfile({
    required String fullName,
    required String email,
    required String phone,
  }) async {
    final res = await _api.put('/profile', {
      'name': fullName,
      'email': email,
      'phone': phone,
    });
    profile = TenantProfile.fromJson(res['user']);
    notifyListeners();
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
