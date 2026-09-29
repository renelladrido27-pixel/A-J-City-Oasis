/// An admin announcement visible to this tenant (GET /api/announcements) —
/// the server already applies the audience rules (everyone, the tenant's
/// property or floor, or addressed to them directly).
class Announcement {
  final int id;
  final String title;
  final String body;
  final String audience;
  final String? author;
  final DateTime? postedAt;

  const Announcement({
    required this.id,
    required this.title,
    required this.body,
    required this.audience,
    this.author,
    this.postedAt,
  });

  factory Announcement.fromJson(Map<String, dynamic> json) {
    return Announcement(
      id: json['id'] as int,
      title: json['title'] as String,
      body: json['body'] as String,
      audience: json['audience'] as String,
      author: json['author'] as String?,
      postedAt: DateTime.tryParse(
        json['created_at']?.toString() ?? '',
      )?.toLocal(),
    );
  }
}
