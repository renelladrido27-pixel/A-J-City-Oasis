import 'package:flutter/material.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/account_validators.dart';
import '../widgets/account_fields.dart';
import '../widgets/oasis_button.dart';
import 'verify_email_screen.dart';

/// C2 - Log in / Sign up. Full-screen, no bottom nav (matches wireframe).
class AuthScreen extends StatefulWidget {
  final bool initialTabLogin;
  const AuthScreen({super.key, this.initialTabLogin = true});

  @override
  State<AuthScreen> createState() => _AuthScreenState();
}

class _AuthScreenState extends State<AuthScreen> {
  late bool _isLogin = widget.initialTabLogin;
  // Log in uses its own two fields; Sign up uses the shared account form.
  final _email = TextEditingController();
  final _password = TextEditingController();
  final _account = AccountFormControllers();
  String? _error;
  bool _submitting = false;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _account.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final problem = _isLogin
        ? (_email.text.trim().isEmpty || _password.text.isEmpty
              ? 'Enter your email and password.'
              : null)
        : _account.validate();
    if (problem != null) {
      setState(() => _error = problem);
      return;
    }
    setState(() {
      _submitting = true;
      _error = null;
    });
    final app = AppStateScope.of(context);
    try {
      if (_isLogin) {
        await app.login(_email.text.trim(), _password.text);
      } else {
        await app.signUp(
          firstName: _account.firstName.text.trim(),
          middleName: _account.middleName.text.trim(),
          lastName: _account.lastName.text.trim(),
          email: _account.email.text.trim(),
          phone: normalizePhone(_account.phone.text),
          password: _account.password.text,
        );
      }
      // New accounts (and unverified ones logging back in) enter their emailed code first.
      if (mounted) await VerifyEmailScreen.ensureVerified(context);
      if (mounted) Navigator.of(context).pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 48),
              const Text(
                'A & J Oasis',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 40),
              Container(
                decoration: BoxDecoration(
                  border: Border.all(color: OasisColors.border, width: 1.4),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: _TabButton(
                        label: 'Log in',
                        selected: _isLogin,
                        onTap: () => setState(() => _isLogin = true),
                      ),
                    ),
                    Container(
                      width: 1.4,
                      height: 44,
                      color: OasisColors.border,
                    ),
                    Expanded(
                      child: _TabButton(
                        label: 'Sign up',
                        selected: !_isLogin,
                        onTap: () => setState(() => _isLogin = false),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 20),
              if (_isLogin) ...[
                TextField(
                  controller: _email,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(hintText: 'Email'),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _password,
                  obscureText: true,
                  decoration: const InputDecoration(hintText: 'Password'),
                ),
              ] else ...[
                AccountIdentityFields(controllers: _account),
                const SizedBox(height: 14),
                AccountPasswordFields(controllers: _account),
              ],
              if (_isLogin) ...[
                const SizedBox(height: 10),
                Align(
                  alignment: Alignment.centerLeft,
                  child: TextButton(
                    style: TextButton.styleFrom(
                      padding: EdgeInsets.zero,
                      foregroundColor: OasisColors.green,
                    ),
                    onPressed: () {},
                    child: const Text('Forgot password?'),
                  ),
                ),
              ] else
                const SizedBox(height: 16),
              if (_error != null) ...[
                Text(
                  _error!,
                  style: const TextStyle(color: Colors.redAccent, fontSize: 12),
                ),
                const SizedBox(height: 8),
              ],
              const SizedBox(height: 6),
              OasisButton(
                label: _isLogin ? 'Log in' : 'Sign up',
                onPressed: _submitting ? null : _submit,
              ),
              if (_submitting)
                const Padding(
                  padding: EdgeInsets.only(top: 16),
                  child: Center(child: CircularProgressIndicator()),
                ),
              const SizedBox(height: 24),
            ],
          ),
        ),
      ),
    );
  }
}

class _TabButton extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _TabButton({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      child: Container(
        height: 44,
        alignment: Alignment.center,
        color: selected ? const Color(0xFFE9E7DE) : Colors.transparent,
        child: Text(
          label,
          style: TextStyle(
            fontWeight: selected ? FontWeight.w700 : FontWeight.w400,
          ),
        ),
      ),
    );
  }
}
