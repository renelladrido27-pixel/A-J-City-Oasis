class Room {
  final String id;
  final String number;
  final String property;
  final double monthlyRent;

  /// Due on booking: advance + deposit + security deposit (3 months' rent).
  final double upfrontTotal;

  /// "First Floor", "Second Floor"… as worded by the server.
  final String floorLabel;
  final double sizeSqm;
  final List<String> amenities;
  final String statusTag;
  final String? description;
  final List<String> images;

  const Room({
    required this.id,
    required this.number,
    required this.property,
    required this.monthlyRent,
    required this.upfrontTotal,
    required this.floorLabel,
    required this.sizeSqm,
    required this.amenities,
    required this.statusTag,
    this.description,
    this.images = const [],
  });

  factory Room.fromJson(Map<String, dynamic> json) {
    return Room(
      id: json['id'].toString(),
      number: json['number'] as String,
      property: json['property'] as String? ?? '',
      monthlyRent: (json['monthly_rate'] as num).toDouble(),
      upfrontTotal:
          (json['upfront_total'] as num?)?.toDouble() ??
          (json['monthly_rate'] as num).toDouble() * 3,
      floorLabel: json['floor_label'] as String? ?? '',
      sizeSqm: (json['size_sqm'] as num?)?.toDouble() ?? 0,
      amenities:
          (json['amenities'] as List?)?.map((e) => e.toString()).toList() ??
          const [],
      statusTag: (json['status'] as String? ?? 'vacant') == 'vacant'
          ? 'Available'
          : (json['status'] as String),
      description: json['description'] as String?,
      images:
          (json['images'] as List?)?.map((e) => e.toString()).toList() ??
          const [],
    );
  }

  String get label => 'Rm $number';
}
