class TenantProfile {
  /// Combined display name ("First Middle Surname"), kept in sync by the server.
  String fullName;
  String firstName;
  String middleName;
  String lastName;
  String email;
  String phone;
  String? photoUrl;

  /// False until the 6-digit code emailed at sign-up has been entered.
  bool emailVerified;

  TenantProfile({
    required this.fullName,
    required this.firstName,
    required this.middleName,
    required this.lastName,
    required this.email,
    required this.phone,
    required this.emailVerified,
    this.photoUrl,
  });

  factory TenantProfile.fromJson(Map<String, dynamic> json) {
    return TenantProfile(
      fullName: json['name'] as String,
      firstName: json['first_name'] as String? ?? '',
      middleName: json['middle_name'] as String? ?? '',
      lastName: json['last_name'] as String? ?? '',
      email: json['email'] as String,
      phone: json['phone'] as String? ?? '',
      emailVerified: json['email_verified'] as bool? ?? true,
      photoUrl: json['photo_url'] as String?,
    );
  }
}
