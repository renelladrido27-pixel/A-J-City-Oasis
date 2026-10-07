import 'package:flutter/material.dart';

import '../theme.dart';
import '../utils/account_validators.dart';

/// Text controllers for a new account. Shared by the Sign up tab (C2) and the
/// room-booking form (C3), which both create an account.
class AccountFormControllers {
  final firstName = TextEditingController();
  final middleName = TextEditingController();
  final lastName = TextEditingController();
  final email = TextEditingController();
  final phone = TextEditingController();
  final password = TextEditingController();
  final confirmPassword = TextEditingController();

  void dispose() {
    for (final c in [
      firstName,
      middleName,
      lastName,
      email,
      phone,
      password,
      confirmPassword,
    ]) {
      c.dispose();
    }
  }

  /// First problem found, or null when everything can be sent to the server.
  String? validate() {
    return validateName(firstName.text, 'first name') ??
        validateName(middleName.text, 'middle name', required: false) ??
        validateName(lastName.text, 'surname') ??
        (email.text.trim().isEmpty ? 'Enter your email.' : null) ??
        validatePhone(phone.text) ??
        validatePassword(password.text) ??
        (password.text != confirmPassword.text
            ? 'The passwords don\'t match.'
            : null);
  }
}

/// Name (first / middle / surname), email and mobile number inputs.
class AccountIdentityFields extends StatelessWidget {
  final AccountFormControllers controllers;
  const AccountIdentityFields({super.key, required this.controllers});

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        TextField(
          controller: controllers.firstName,
          textCapitalization: TextCapitalization.words,
          autofillHints: const [AutofillHints.givenName],
          decoration: const InputDecoration(hintText: 'First name'),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: controllers.middleName,
          textCapitalization: TextCapitalization.words,
          autofillHints: const [AutofillHints.middleName],
          decoration: const InputDecoration(hintText: 'Middle name (optional)'),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: controllers.lastName,
          textCapitalization: TextCapitalization.words,
          autofillHints: const [AutofillHints.familyName],
          decoration: const InputDecoration(hintText: 'Surname'),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: controllers.email,
          keyboardType: TextInputType.emailAddress,
          autofillHints: const [AutofillHints.email],
          decoration: const InputDecoration(hintText: 'Email'),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: controllers.phone,
          keyboardType: TextInputType.phone,
          maxLength: 13,
          autofillHints: const [AutofillHints.telephoneNumber],
          decoration: const InputDecoration(
            hintText: 'Mobile number (09XXXXXXXXX)',
            counterText: '',
          ),
        ),
      ],
    );
  }
}

/// Password + confirm inputs with a live checklist of the password rules.
class AccountPasswordFields extends StatefulWidget {
  final AccountFormControllers controllers;
  const AccountPasswordFields({super.key, required this.controllers});

  @override
  State<AccountPasswordFields> createState() => _AccountPasswordFieldsState();
}

class _AccountPasswordFieldsState extends State<AccountPasswordFields> {
  bool _obscure = true;

  @override
  void initState() {
    super.initState();
    widget.controllers.password.addListener(_onChanged);
  }

  @override
  void dispose() {
    widget.controllers.password.removeListener(_onChanged);
    super.dispose();
  }

  void _onChanged() => setState(() {});

  @override
  Widget build(BuildContext context) {
    final checks = passwordChecks(widget.controllers.password.text);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        TextField(
          controller: widget.controllers.password,
          obscureText: _obscure,
          autofillHints: const [AutofillHints.newPassword],
          decoration: InputDecoration(
            hintText: 'Password',
            suffixIcon: IconButton(
              tooltip: _obscure ? 'Show password' : 'Hide password',
              icon: Icon(
                _obscure
                    ? Icons.visibility_outlined
                    : Icons.visibility_off_outlined,
              ),
              onPressed: () => setState(() => _obscure = !_obscure),
            ),
          ),
        ),
        const SizedBox(height: 8),
        for (final check in checks)
          Padding(
            padding: const EdgeInsets.only(bottom: 2),
            child: Row(
              children: [
                Icon(
                  check.ok ? Icons.check_circle : Icons.circle_outlined,
                  size: 15,
                  color: check.ok ? OasisColors.green : OasisColors.muted,
                ),
                const SizedBox(width: 6),
                Text(
                  check.label,
                  style: TextStyle(
                    fontSize: 12,
                    color: check.ok ? OasisColors.green : OasisColors.muted,
                  ),
                ),
              ],
            ),
          ),
        const SizedBox(height: 10),
        TextField(
          controller: widget.controllers.confirmPassword,
          obscureText: _obscure,
          decoration: const InputDecoration(hintText: 'Confirm password'),
        ),
      ],
    );
  }
}
