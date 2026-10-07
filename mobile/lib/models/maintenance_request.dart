class MaintenanceRequest {
  final int id;
  final String issueType;
  final String description;
  final String status;
  final DateTime submittedAt;

  /// Who will do the work, once the admin has assigned someone.
  final String? assignedName;
  final String? assignedPhone;
  final DateTime? scheduledDate;

  const MaintenanceRequest({
    required this.id,
    required this.issueType,
    required this.description,
    required this.status,
    required this.submittedAt,
    this.assignedName,
    this.assignedPhone,
    this.scheduledDate,
  });

  factory MaintenanceRequest.fromJson(Map<String, dynamic> json) {
    final assignee = json['assigned_to'] as Map<String, dynamic>?;
    return MaintenanceRequest(
      id: json['id'] as int,
      issueType: json['category'] as String,
      description: json['description'] as String,
      status: _titleCase((json['status'] as String).replaceAll('_', ' ')),
      submittedAt:
          DateTime.tryParse(json['created_at']?.toString() ?? '') ??
          DateTime.now(),
      assignedName: assignee?['name'] as String?,
      assignedPhone: assignee?['phone'] as String?,
      scheduledDate: DateTime.tryParse(
        json['scheduled_date']?.toString() ?? '',
      ),
    );
  }

  static String _titleCase(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}
