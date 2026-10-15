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

  /// Someone is assigned and the scheduled day has come — only then can the
  /// tenant mark the work done (decided by the server, on Manila time).
  final bool canMarkDone;

  const MaintenanceRequest({
    required this.id,
    required this.issueType,
    required this.description,
    required this.status,
    required this.submittedAt,
    this.assignedName,
    this.assignedPhone,
    this.scheduledDate,
    this.canMarkDone = false,
  });

  /// Not yet resolved or cancelled — the tenant can still close it.
  bool get isOpen => status == 'Pending' || status == 'In progress';

  /// The tenant can delete it if they changed their mind — but only before
  /// anyone has been assigned (or once it was cancelled).
  bool get canDelete => status == 'Pending' || status == 'Cancelled';

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
      canMarkDone: json['can_mark_done'] as bool? ?? false,
    );
  }

  static String _titleCase(String s) =>
      s.isEmpty ? s : '${s[0].toUpperCase()}${s.substring(1)}';
}
