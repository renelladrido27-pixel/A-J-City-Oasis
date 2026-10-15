class TransferRequest {
  final int id;
  final String fromRoomLabel;
  final String toRoomLabel;
  final String status;
  final String? reason;
  final DateTime requestedAt;

  const TransferRequest({
    required this.id,
    required this.fromRoomLabel,
    required this.toRoomLabel,
    required this.status,
    this.reason,
    required this.requestedAt,
  });

  factory TransferRequest.fromJson(Map<String, dynamic> json) {
    return TransferRequest(
      id: json['id'] as int,
      fromRoomLabel: _label(json['from_room'], json['from_building']),
      toRoomLabel: _label(json['to_room'], json['to_building']),
      status: _titleCase(json['status'] as String),
      reason: json['reason'] as String?,
      requestedAt:
          DateTime.tryParse(json['requested_at']?.toString() ?? '') ??
          DateTime.now(),
    );
  }

  static String _titleCase(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';

  /// "Rm 103 · Main Building" — the same number exists in more than one building.
  static String _label(Object? room, Object? building) =>
      (building ?? '').toString().isEmpty ? 'Rm $room' : 'Rm $room · $building';
}
