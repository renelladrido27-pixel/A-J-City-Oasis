class TenantProfile {
  String fullName;
  String email;
  String phone;
  String? photoUrl;

  TenantProfile({
    required this.fullName,
    required this.email,
    required this.phone,
    this.photoUrl,
  });

  factory TenantProfile.fromJson(Map<String, dynamic> json) {
    return TenantProfile(
      fullName: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String? ?? '',
      photoUrl: json['photo_url'] as String?,
    );
  }
}
