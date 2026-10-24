import 'room.dart';

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

  const Lease({
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
