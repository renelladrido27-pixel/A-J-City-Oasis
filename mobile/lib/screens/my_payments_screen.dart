import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_button.dart';

/// C6 - My Payments.
class MyPaymentsScreen extends StatefulWidget {
  const MyPaymentsScreen({super.key});

  @override
  State<MyPaymentsScreen> createState() => _MyPaymentsScreenState();
}

class _MyPaymentsScreenState extends State<MyPaymentsScreen>
    with WidgetsBindingObserver {
  bool _submitting = false;
  bool _awaitingCheckout = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Xendit checkout runs in the external browser — when the tenant switches
  /// back to the app, confirm the payment without making them tap anything.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed &&
        _awaitingCheckout &&
        !_submitting) {
      _checkStatus();
    }
  }

  Future<void> _pay() async {
    setState(() => _submitting = true);
    final app = AppStateScope.of(context);
    try {
      final result = await app.payOutstanding();
      if (!mounted) return;
      if (result['status'] == 'paid') {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Payment received. Thank you!')),
        );
      } else if (result['status'] == 'redirect') {
        final url = result['invoice_url'] as String?;
        if (url != null) {
          await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
        }
        setState(() => _awaitingCheckout = true);
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _checkStatus() async {
    setState(() => _submitting = true);
    final app = AppStateScope.of(context);
    try {
      final result = await app.checkOutstandingPaymentStatus();
      if (!mounted) return;
      if (result['status'] == 'paid') {
        setState(() => _awaitingCheckout = false);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Payment received. Thank you!')),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Not paid yet. Complete checkout, then check again.'),
          ),
        );
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final hasDue = app.nextDuePaymentId != null;
    return RefreshIndicator(
      onRefresh: app.refreshPayments,
      color: OasisColors.green,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                border: Border.all(color: OasisColors.placeholderGrey),
                borderRadius: BorderRadius.circular(6),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Outstanding',
                    style: TextStyle(color: OasisColors.muted),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    formatPeso(app.outstandingBalance),
                    style: const TextStyle(
                      fontSize: 28,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    app.nextDueDate == null
                        ? 'Nothing due'
                        : 'Due ${formatShortDate(app.nextDueDate!)}',
                    style: const TextStyle(color: OasisColors.muted),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            OasisButton(
              label: 'Pay now',
              onPressed: (!hasDue || _submitting) ? null : _pay,
            ),
            if (_awaitingCheckout) ...[
              const SizedBox(height: 12),
              OasisButton(
                label: "I've paid — check status",
                outlined: true,
                onPressed: _submitting ? null : _checkStatus,
              ),
            ],
            if (_submitting)
              const Padding(
                padding: EdgeInsets.only(top: 16),
                child: Center(child: CircularProgressIndicator()),
              ),
            const SizedBox(height: 28),
            const Text(
              'History',
              style: TextStyle(
                color: OasisColors.muted,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 10),
            for (final p in app.paymentHistory)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Text(
                  '· ${p.monthLabel} — ${p.status}',
                  style: const TextStyle(fontWeight: FontWeight.w500),
                ),
              ),
            if (app.paymentHistory.isEmpty)
              const Text(
                'No payment history yet.',
                style: TextStyle(color: OasisColors.muted),
              ),
          ],
        ),
      ),
    );
  }
}
