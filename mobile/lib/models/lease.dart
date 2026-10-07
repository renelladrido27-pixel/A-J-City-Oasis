import 'room.dart';

class Lease {
  final String roomLabel;
  final String property;
  final double monthlyRent;
  final DateTime moveInDate;
  final String leaseStatus;
  final String? documentUrl;

  /// Photos of the leased room (same as on the website).
  final List<String> roomImages;

  const Lease({
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
    return Lease(
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
