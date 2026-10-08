import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../models/payment_record.dart';
import '../widgets/oasis_ui.dart';
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
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const Text(
              'Payments',
              style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
            ),
            const SizedBox(height: 14),
            OasisCard(
              highlighted: hasDue,
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
            const SectionLabel('HISTORY'),
            const SizedBox(height: 10),
            for (final (i, p) in app.paymentHistory.indexed)
              Padding(
                key: ValueKey(p.id),
                padding: const EdgeInsets.only(bottom: 10),
                child: ListEntrance(
                  index: i,
                  child: _HistoryRow(payment: p),
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

/// One paid bill: what it was for, when it was paid, how much.
class _HistoryRow extends StatelessWidget {
  final PaymentRecord payment;
  const _HistoryRow({required this.payment});

  @override
  Widget build(BuildContext context) {
    final p = payment;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: OasisColors.hairline),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: const BoxDecoration(
              color: OasisColors.unreadTint,
              shape: BoxShape.circle,
            ),
            child: const Icon(Icons.check, size: 18, color: OasisColors.green),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${p.typeLabel} · ${p.monthLabel}',
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                Text(
                  'Paid ${formatShortDate(p.paidOn.toLocal())}',
                  style: const TextStyle(
                    color: OasisColors.muted,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
          Text(
            formatPeso(p.amount),
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}
