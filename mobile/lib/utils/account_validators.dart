/// Client-side copies of the server's account rules (App\Support\AccountRules
/// and the Password::defaults() policy) so mistakes are caught before a
/// round-trip. The server remains the authority.
library;

final _namePattern = RegExp(r"^[\p{L}][\p{L}\s.'\-]*$", unicode: true);
final _phonePattern = RegExp(r'^(09|\+639)\d{9}$');

/// "0917 123-4567" / "+63 917…" → digits only, as the server stores it.
String normalizePhone(String phone) =>
    phone.replaceAll(RegExp(r'[\s\-().]'), '');

/// Returns an error message, or null when the value is acceptable.
String? validateName(String value, String label, {bool required = true}) {
  final v = value.trim();
  if (v.isEmpty) return required ? 'Enter your $label.' : null;
  if (!_namePattern.hasMatch(v)) {
    return 'The $label may only contain letters, spaces, periods, apostrophes and hyphens.';
  }
  return null;
}

String? validatePhone(String value) {
  final v = normalizePhone(value);
  if (v.isEmpty) return 'Enter your mobile number.';
  if (!_phonePattern.hasMatch(v)) {
    return 'Enter a valid PH mobile number, e.g. 09171234567.';
  }
  return null;
}

/// One entry per password requirement, with whether [password] meets it.
List<({String label, bool ok})> passwordChecks(String password) => [
  (label: 'At least 8 characters', ok: password.length >= 8),
  (
    label: 'Upper- and lower-case letters',
    ok:
        RegExp(r'\p{Ll}', unicode: true).hasMatch(password) &&
        RegExp(r'\p{Lu}', unicode: true).hasMatch(password),
  ),
  (label: 'A number', ok: RegExp(r'\d').hasMatch(password)),
  (
    label: 'A symbol (e.g. ! @ # \$)',
    ok: RegExp(r'[^\p{L}\p{N}\s]', unicode: true).hasMatch(password),
  ),
];

String? validatePassword(String password) =>
    passwordChecks(password).every((c) => c.ok)
    ? null
    : 'Your password doesn\'t meet all the requirements below.';
