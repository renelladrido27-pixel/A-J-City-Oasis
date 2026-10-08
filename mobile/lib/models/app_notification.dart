/// One row of GET /api/notifications. [type] is the server's notification
/// type (booking, lease, transfer, move_out, payment, utility, maintenance,
/// announcement); [category] groups those the same way the website's
/// notification filter does (Notification::categories()).
class AppNotification {
  final int id;
  final String title;
  final String message;
  final String type;
  final bool isRead;
  final DateTime? createdAt;

  const AppNotification({
    required this.id,
    required this.title,
    required this.message,
    required this.type,
    required this.isRead,
    this.createdAt,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      id: json['id'] as int,
      title: json['title'] as String,
      message: (json['message'] ?? '').toString(),
      type: json['type'] as String,
      isRead: json['is_read'] == true,
      createdAt: DateTime.tryParse(json['created_at']?.toString() ?? ''),
    );
  }

  AppNotification copyWith({bool? isRead}) => AppNotification(
    id: id,
    title: title,
    message: message,
    type: type,
    isRead: isRead ?? this.isRead,
    createdAt: createdAt,
  );

  String get category => switch (type) {
    'transfer' || 'move_out' => 'lease',
    'utility' => 'payment',
    _ => type,
  };

  /// "5 minutes ago", like the website's diffForHumans().
  String get timeAgo => relativeTime(createdAt, DateTime.now());

  static String relativeTime(DateTime? then, DateTime now) {
    if (then == null) return '';
    final diff = now.toUtc().difference(then.toUtc());
    if (diff.inSeconds < 60) return 'Just now';
    if (diff.inMinutes < 60) return _ago(diff.inMinutes, 'minute');
    if (diff.inHours < 24) return _ago(diff.inHours, 'hour');
    if (diff.inDays < 7) return _ago(diff.inDays, 'day');
    if (diff.inDays < 30) return _ago(diff.inDays ~/ 7, 'week');
    if (diff.inDays < 365) return _ago(diff.inDays ~/ 30, 'month');
    return _ago(diff.inDays ~/ 365, 'year');
  }

  static String _ago(int n, String unit) => '$n $unit${n == 1 ? '' : 's'} ago';
}
