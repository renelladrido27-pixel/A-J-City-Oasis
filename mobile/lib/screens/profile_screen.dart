import 'package:flutter/material.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/oasis_button.dart';

/// C10 - Profile.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final _fullName = TextEditingController(
    text: AppStateScope.of(context).profile?.fullName ?? '',
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
    _fullName.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await AppStateScope.of(context).updateProfile(
        fullName: _fullName.text.trim(),
        email: _email.text.trim(),
        phone: _phone.text.trim(),
      );
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
            controller: _fullName,
            decoration: const InputDecoration(hintText: 'Full name'),
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
            decoration: const InputDecoration(hintText: 'Phone'),
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
