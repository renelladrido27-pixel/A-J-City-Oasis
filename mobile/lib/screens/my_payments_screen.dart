import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/payment_record.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_ui.dart';

/// C6 - My Payments: what is owed (each bill with its own Pay button, like
/// the website's payments table) and what has been paid.
class MyPaymentsScreen extends StatefulWidget {
  const MyPaymentsScreen({super.key});

  @override
  State<MyPaymentsScreen> createState() => _MyPaymentsScreenState();
}

class _MyPaymentsScreenState extends State<MyPaymentsScreen>
    with WidgetsBindingObserver {
  /// The bill a request is in flight for.
  int? _busyId;

  /// The bill whose checkout page was opened and hasn't been confirmed yet.
  int? _awaitingId;

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
    final awaiting = _awaitingId;
    if (state == AppLifecycleState.resumed &&
        awaiting != null &&
        _busyId == null) {
      _checkStatus(awaiting);
    }
  }

  void _say(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Future<void> _pay(int id) async {
    setState(() => _busyId = id);
    final app = AppStateScope.of(context);
    try {
      final result = await app.payPayment(id);
      if (!mounted) return;
      if (result['status'] == 'paid') {
        _say('Payment received. Thank you!');
      } else if (result['status'] == 'redirect') {
        final url = result['invoice_url'] as String?;
        if (url != null) {
          await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
        }
        if (mounted) setState(() => _awaitingId = id);
      }
    } on ApiException catch (e) {
      _say(e.message);
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  Future<void> _checkStatus(int id) async {
    setState(() => _busyId = id);
    final app = AppStateScope.of(context);
    try {
      final result = await app.checkPaymentStatus(id);
      if (!mounted) return;
      if (result['status'] == 'paid') {
        setState(() => _awaitingId = null);
        _say('Payment received. Thank you!');
      } else {
        _say('Not paid yet. Complete checkout, then check again.');
      }
    } on ApiException catch (e) {
      _say(e.message);
    } finally {
      if (mounted) setState(() => _busyId = null);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final unpaid = app.unpaidPayments;
    final history = app.paymentHistory;

    return RefreshIndicator(
      onRefresh: app.refreshPayments,
      color: OasisColors.green,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
        children: [
          const Text(
            'Payments',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 14),
          ListEntrance(
            index: 0,
            child: _BalanceCard(
              outstanding: app.outstandingBalance,
              nextDue: app.nextDueDate,
            ),
          ),
          if (unpaid.isNotEmpty) ...[
            const SizedBox(height: 24),
            const SectionLabel('BILLS TO PAY'),
            const SizedBox(height: 10),
            for (final (i, p) in unpaid.indexed)
              Padding(
                key: ValueKey('bill-${p.id}'),
                padding: const EdgeInsets.only(bottom: 12),
                child: ListEntrance(
                  index: i + 1,
                  child: _BillCard(
                    payment: p,
                    busy: _busyId == p.id,
                    // One request at a time.
                    enabled: _busyId == null,
                    awaitingCheckout: _awaitingId == p.id,
                    onPay: () => _pay(p.id),
                    onCheckStatus: () => _checkStatus(p.id),
                  ),
                ),
              ),
          ],
          const SizedBox(height: 16),
          const SectionLabel('HISTORY'),
          const SizedBox(height: 10),
          for (final (i, p) in history.indexed)
            Padding(
              key: ValueKey('paid-${p.id}'),
              padding: const EdgeInsets.only(bottom: 10),
              child: ListEntrance(
                index: i + unpaid.length + 1,
                child: _HistoryRow(payment: p),
              ),
            ),
          if (history.isEmpty)
            const EmptyState(
              icon: Icons.receipt_long_outlined,
              message: 'No payments yet.',
              hint: 'Bills you pay will be listed here.',
            ),
        ],
      ),
    );
  }
}

/// The total owed and when the next bill is due.
class _BalanceCard extends StatelessWidget {
  final double outstanding;
  final DateTime? nextDue;
  const _BalanceCard({required this.outstanding, required this.nextDue});

  @override
  Widget build(BuildContext context) {
    final owes = outstanding > 0;
    return OasisCard(
      highlighted: owes,
      child: Row(
        children: [
          IconBadge(
            owes ? Icons.account_balance_wallet_outlined : Icons.check,
            filled: owes,
            size: 46,
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Outstanding balance',
                  style: TextStyle(color: OasisColors.muted, fontSize: 12),
                ),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text(
                    formatPeso(outstanding),
                    style: TextStyle(
                      fontSize: 28,
                      height: 1.15,
                      fontWeight: FontWeight.w800,
                      color: owes ? OasisColors.green : OasisColors.ink,
                    ),
                  ),
                ),
                Text(
                  owes && nextDue != null
                      ? 'Next due ${formatLongDate(nextDue!)}'
                      : 'You are all paid up.',
                  style: const TextStyle(color: OasisColors.muted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// One bill that is still owed, with its own Pay button.
class _BillCard extends StatelessWidget {
  final PaymentRecord payment;
  final bool busy;
  final bool enabled;
  final bool awaitingCheckout;
  final VoidCallback onPay;
  final VoidCallback onCheckStatus;
  const _BillCard({
    required this.payment,
    required this.busy,
    required this.enabled,
    required this.awaitingCheckout,
    required this.onPay,
    required this.onCheckStatus,
  });

  @override
  Widget build(BuildContext context) {
    final p = payment;
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    );
    return OasisCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const IconBadge(Icons.receipt_long_outlined),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${p.typeLabel} · ${p.monthLabel}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 15,
                      ),
                    ),
                    Text(
                      'Due ${formatLongDate(p.dueDate)}',
                      style: const TextStyle(
                        color: OasisColors.muted,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    formatPeso(p.amount),
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 17,
                    ),
                  ),
                  const SizedBox(height: 4),
                  StatusChip.forStatus(p.status),
                ],
              ),
            ],
          ),
          const SizedBox(height: 14),
          FilledButton(
            onPressed: enabled ? onPay : null,
            style: FilledButton.styleFrom(
              backgroundColor: OasisColors.gold,
              foregroundColor: OasisColors.ink,
              disabledBackgroundColor: OasisColors.gold.withValues(alpha: 0.5),
              minimumSize: const Size.fromHeight(46),
              shape: shape,
            ),
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 180),
              child: busy
                  ? const SizedBox(
                      key: ValueKey('busy'),
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: OasisColors.ink,
                      ),
                    )
                  : Text(
                      awaitingCheckout ? 'Open payment page again' : 'Pay now',
                      key: ValueKey(awaitingCheckout),
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
            ),
          ),
          // Appears once the checkout page has been opened.
          AnimatedSize(
            duration: const Duration(milliseconds: 220),
            curve: Curves.easeOut,
            alignment: Alignment.topCenter,
            child: !awaitingCheckout
                ? const SizedBox(width: double.infinity)
                : Padding(
                    padding: const EdgeInsets.only(top: 8),
                    child: OutlinedButton(
                      onPressed: enabled ? onCheckStatus : null,
                      style: OutlinedButton.styleFrom(
                        foregroundColor: OasisColors.green,
                        side: const BorderSide(color: OasisColors.green),
                        minimumSize: const Size.fromHeight(44),
                        shape: shape,
                      ),
                      child: const Text("I've paid — check status"),
                    ),
                  ),
          ),
        ],
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
    return OasisCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        children: [
          const IconBadge(Icons.check, size: 36),
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
                  'Paid ${formatLongDate(p.paidOn.toLocal())}',
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
