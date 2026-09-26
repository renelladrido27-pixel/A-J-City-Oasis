/// Mirrors the API's Notification::categories() keys (booking, lease, payment,
/// maintenance, announcement) rather than a closed Dart enum, since the server
/// is the source of truth for what categories exist.
class AppNotification {
  final String title;
  final String category;
  final String timeAgo;

  const AppNotification({
    required this.title,
    required this.category,
    required this.timeAgo,
  });

  factory AppNotification.fromJson(Map<String, dynamic> json) {
    return AppNotification(
      title: json['title'] as String,
      category: json['type'] as String,
      timeAgo: _relative(
        DateTime.tryParse(json['created_at']?.toString() ?? ''),
      ),
    );
  }

  static String _relative(DateTime? dt) {
    if (dt == null) return '';
    final diff = DateTime.now().toUtc().difference(dt.toUtc());
    if (diff.inMinutes < 60) return '${diff.inMinutes.clamp(0, 59)}m';
    if (diff.inHours < 24) return '${diff.inHours}h';
    return '${diff.inDays}d';
  }
}
