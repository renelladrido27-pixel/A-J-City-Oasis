import '../utils/format.dart';
import 'room.dart';

/// A move-out the tenant has asked for, and where it stands.
class MoveOutInfo {
  final DateTime requestedDate;

  /// awaiting_inspection, then disbursed_manually / balance_due / no_refund.
  final String status;
  final double refundAmount;
  final double balanceDue;
  final int? balancePaymentId;

  const MoveOutInfo({
    required this.requestedDate,
    required this.status,
    this.refundAmount = 0,
    this.balanceDue = 0,
    this.balancePaymentId,
  });

  bool get awaitingInspection => status == 'awaiting_inspection';

  /// One line for My rental.
  String get summary => switch (status) {
    'awaiting_inspection' =>
      'Waiting for the room inspection · estimated refund ${formatPesoExact(refundAmount)}',
    'disbursed_manually' =>
      'Settled · refund of ${formatPesoExact(refundAmount)} to be released by the admin',
    'balance_due' =>
      'Settled · ${formatPesoExact(balanceDue)} to settle with the admin (outside the app)',
    _ => 'Settled · your deposit covered everything',
  };

  static MoveOutInfo? fromJson(Map<String, dynamic>? json) {
    final date = DateTime.tryParse(json?['requested_date']?.toString() ?? '');
    if (json == null || date == null) return null;
    return MoveOutInfo(
      requestedDate: date,
      status: (json['status'] ?? 'awaiting_inspection').toString(),
      refundAmount: (json['refund_amount'] as num?)?.toDouble() ?? 0,
      balanceDue: (json['balance_due'] as num?)?.toDouble() ?? 0,
      balancePaymentId: json['balance_payment_id'] as int?,
    );
  }
}

class Lease {
  final int? id;
  final String roomLabel;

  /// "Main Building" — to tell two leased rooms apart.
  final String building;
  final String property;
  final double monthlyRent;
  final DateTime moveInDate;
  final String leaseStatus;
  final String? documentUrl;

  /// Photos of the leased room (same as on the website).
  final List<String> roomImages;

  /// This room's own bills (null from an older server: use the app-wide ones).
  final double? balance;
  final DateTime? nextDueDate;
  final double? nextDueAmount;
  final int? nextDuePaymentId;

  /// Null until the tenant asks to move out.
  final MoveOutInfo? moveOut;

  const Lease({
    this.moveOut,
    this.id,
    this.building = '',
    this.balance,
    this.nextDueDate,
    this.nextDueAmount,
    this.nextDuePaymentId,
    required this.roomLabel,
    required this.property,
    required this.monthlyRent,
    required this.moveInDate,
    required this.leaseStatus,
    this.documentUrl,
    this.roomImages = const [],
  });

  factory Lease.fromJson(Map<String, dynamic> json) {
    final room = Room.fromJson(json['room'] as Map<String, dynamic>);
    final next = json['next_due'] as Map<String, dynamic>?;
    return Lease(
      id: json['id'] as int?,
      building: room.building,
      balance: (json['balance'] as num?)?.toDouble(),
      nextDueDate: DateTime.tryParse(next?['date']?.toString() ?? ''),
      nextDueAmount: (next?['amount'] as num?)?.toDouble(),
      nextDuePaymentId: next?['payment_id'] as int?,
      moveOut: MoveOutInfo.fromJson(json['move_out'] as Map<String, dynamic>?),
      roomLabel: 'Room ${room.number}',
      property: room.property,
      monthlyRent: room.monthlyRent,
      moveInDate: DateTime.parse(json['start_date'] as String),
      leaseStatus: _titleCase(json['status'] as String),
      documentUrl: json['document_url'] as String?,
      roomImages: room.images,
    );
  }

  static String _titleCase(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}
