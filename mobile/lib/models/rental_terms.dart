/// The Terms and Conditions (Rental Agreement) for one room — the same text
/// the website shows on its booking page (GET /api/rooms/{id}/terms).
class RentalTerms {
  final String title;
  final List<TermsSection> sections;

  const RentalTerms({required this.title, required this.sections});

  factory RentalTerms.fromJson(Map<String, dynamic> json) => RentalTerms(
    title: json['title'] as String? ?? 'Terms and Conditions',
    sections: (json['sections'] as List)
        .map((s) => TermsSection.fromJson(s as Map<String, dynamic>))
        .toList(),
  );
}

class TermsSection {
  final String heading;
  final String body;

  const TermsSection({required this.heading, required this.body});

  factory TermsSection.fromJson(Map<String, dynamic> json) => TermsSection(
    heading: json['heading'] as String,
    body: json['body'] as String,
  );
}
