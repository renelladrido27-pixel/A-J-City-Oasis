class MaintenanceRequest {
  final int id;
  final String issueType;
  final String description;
  final String status;
  final DateTime submittedAt;

  const MaintenanceRequest({
    required this.id,
    required this.issueType,
    required this.description,
    required this.status,
    required this.submittedAt,
  });

  factory MaintenanceRequest.fromJson(Map<String, dynamic> json) {
    return MaintenanceRequest(
      id: json['id'] as int,
      issueType: json['category'] as String,
      description: json['description'] as String,
      status: _titleCase((json['status'] as String).replaceAll('_', ' ')),
      submittedAt:
          DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.now(),
    );
  }

  static String _titleCase(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}
