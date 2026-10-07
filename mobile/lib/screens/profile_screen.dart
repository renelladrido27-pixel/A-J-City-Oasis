import 'package:flutter/material.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/account_validators.dart';
import '../widgets/oasis_button.dart';
import 'verify_email_screen.dart';

/// C10 - Profile.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final _firstName = TextEditingController(
    text: AppStateScope.of(context).profile?.firstName ?? '',
  );
  late final _middleName = TextEditingController(
    text: AppStateScope.of(context).profile?.middleName ?? '',
  );
  late final _lastName = TextEditingController(
    text: AppStateScope.of(context).profile?.lastName ?? '',
  );
  late final _email = TextEditingController(
    text: AppStateScope.of(context).profile?.email ?? '',
  );
  late final _phone = TextEditingController(
    text: AppStateScope.of(context).profile?.phone ?? '',
  );
  final _password = TextEditingController();
  bool _saving = false;

  @override
  void dispose() {
    _firstName.dispose();
    _middleName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final problem =
        validateName(_firstName.text, 'first name') ??
        validateName(_middleName.text, 'middle name', required: false) ??
        validateName(_lastName.text, 'surname') ??
        validatePhone(_phone.text);
    if (problem != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(problem)));
      return;
    }

    setState(() => _saving = true);
    try {
      final app = AppStateScope.of(context);
      await app.updateProfile(
        firstName: _firstName.text.trim(),
        middleName: _middleName.text.trim(),
        lastName: _lastName.text.trim(),
        email: _email.text.trim(),
        phone: normalizePhone(_phone.text),
      );
      // A changed email has to be confirmed with a new code.
      if (mounted && app.needsEmailVerification) {
        await VerifyEmailScreen.ensureVerified(context);
      }
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Profile saved.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Column(
              children: [
                Container(
                  width: 90,
                  height: 90,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    border: Border.all(color: OasisColors.border, width: 1.4),
                  ),
                  child: const Icon(
                    Icons.person_outline,
                    size: 40,
                    color: OasisColors.muted,
                  ),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () => ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text(
                        'Photo upload not available in this preview.',
                      ),
                    ),
                  ),
                  style: TextButton.styleFrom(
                    foregroundColor: OasisColors.muted,
                  ),
                  child: const Text('upload photo'),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _firstName,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(hintText: 'First name'),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _middleName,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(
              hintText: 'Middle name (optional)',
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _lastName,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(hintText: 'Surname'),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _email,
            keyboardType: TextInputType.emailAddress,
            decoration: const InputDecoration(hintText: 'Email'),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _phone,
            keyboardType: TextInputType.phone,
            decoration: const InputDecoration(
              hintText: 'Mobile number (09XXXXXXXXX)',
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _password,
            obscureText: true,
            decoration: const InputDecoration(hintText: 'Change password'),
          ),
          const SizedBox(height: 20),
          OasisButton(label: 'Save', onPressed: _saving ? null : _save),
          if (_saving)
            const Padding(
              padding: EdgeInsets.only(top: 16),
              child: Center(child: CircularProgressIndicator()),
            ),
          const SizedBox(height: 20),
          InkWell(
            onTap: () => app.logout(),
            child: const Text(
              'Log out',
              style: TextStyle(
                fontWeight: FontWeight.w600,
                decoration: TextDecoration.underline,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
