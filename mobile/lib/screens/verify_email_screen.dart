import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/oasis_button.dart';

/// Account verification: the tenant types the 6-digit code emailed at sign-up.
/// Pops with `true` once verified, so the caller can continue what the tenant
/// was doing (e.g. finish booking a room).
class VerifyEmailScreen extends StatefulWidget {
  const VerifyEmailScreen({super.key});

  /// Shows the screen if the signed-in account still needs verifying.
  /// Returns true when the account is verified (already, or just now).
  static Future<bool> ensureVerified(BuildContext context) async {
    final app = AppStateScope.of(context);
    if (!app.needsEmailVerification) return true;

    final verified = await Navigator.of(
      context,
    ).push<bool>(MaterialPageRoute(builder: (_) => const VerifyEmailScreen()));
    return verified ?? false;
  }

  @override
  State<VerifyEmailScreen> createState() => _VerifyEmailScreenState();
}

class _VerifyEmailScreenState extends State<VerifyEmailScreen> {
  final _code = TextEditingController();
  String? _error;
  String? _notice;
  bool _busy = false;

  @override
  void dispose() {
    _code.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    if (_code.text.length != 6) {
      setState(() => _error = 'Enter the 6-digit code from the email.');
      return;
    }
    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
    });
    try {
      await AppStateScope.of(context).verifyEmail(_code.text);
      if (mounted) Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _resend() async {
    setState(() {
      _busy = true;
      _error = null;
      _notice = null;
    });
    try {
      await AppStateScope.of(context).resendVerificationCode();
      setState(() => _notice = 'A new code is on its way.');
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final email = AppStateScope.of(context).profile?.email ?? 'your email';
    return Scaffold(
      appBar: AppBar(
        leading: const BackButton(),
        title: const Text('Verify your email'),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 16, 24, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Icon(
                Icons.mark_email_read_outlined,
                size: 56,
                color: OasisColors.green,
              ),
              const SizedBox(height: 16),
              Text(
                'We sent a 6-digit code to\n$email',
                textAlign: TextAlign.center,
                style: const TextStyle(fontSize: 15, height: 1.4),
              ),
              const SizedBox(height: 24),
              TextField(
                controller: _code,
                autofocus: true,
                keyboardType: TextInputType.number,
                textAlign: TextAlign.center,
                maxLength: 6,
                autofillHints: const [AutofillHints.oneTimeCode],
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                style: const TextStyle(
                  fontSize: 26,
                  letterSpacing: 10,
                  fontWeight: FontWeight.w700,
                ),
                decoration: const InputDecoration(
                  hintText: '······',
                  counterText: '',
                ),
                onSubmitted: (_) => _verify(),
              ),
              const SizedBox(height: 12),
              if (_error != null)
                Text(
                  _error!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(color: Colors.redAccent, fontSize: 12),
                ),
              if (_notice != null)
                Text(
                  _notice!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: OasisColors.green,
                    fontSize: 12,
                  ),
                ),
              const SizedBox(height: 12),
              OasisButton(
                label: 'Verify email',
                onPressed: _busy ? null : _verify,
              ),
              if (_busy)
                const Padding(
                  padding: EdgeInsets.only(top: 16),
                  child: Center(child: CircularProgressIndicator()),
                ),
              const SizedBox(height: 16),
              const Text(
                'The code expires in 15 minutes. Check your spam folder if you don\'t see it.',
                textAlign: TextAlign.center,
                style: TextStyle(color: OasisColors.muted, fontSize: 12),
              ),
              TextButton(
                onPressed: _busy ? null : _resend,
                style: TextButton.styleFrom(foregroundColor: OasisColors.green),
                child: const Text('Send a new code'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
