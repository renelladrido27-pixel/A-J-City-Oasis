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
      fromRoomLabel: 'Rm ${json['from_room']}',
      toRoomLabel: 'Rm ${json['to_room']}',
      status: _titleCase(json['status'] as String),
      reason: json['reason'] as String?,
      requestedAt:
          DateTime.tryParse(json['requested_at']?.toString() ?? '') ??
          DateTime.now(),
    );
  }

  static String _titleCase(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}
