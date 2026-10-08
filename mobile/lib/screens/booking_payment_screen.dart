import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_button.dart';
import 'root_shell.dart';

/// C4 - Cost breakdown, then straight to the checkout page (where the tenant
/// picks GCash, Maya, card or bank). No bottom nav.
class BookingPaymentScreen extends StatefulWidget {
  const BookingPaymentScreen({super.key});

  @override
  State<BookingPaymentScreen> createState() => _BookingPaymentScreenState();
}

class _BookingPaymentScreenState extends State<BookingPaymentScreen>
    with WidgetsBindingObserver {
  bool _submitting = false;
  bool _awaitingCheckout = false;
  String? _error;

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
    setState(() {
      _submitting = true;
      _error = null;
    });
    final app = AppStateScope.of(context);
    try {
      final result = await app.completeBookingPayment();
      if (!mounted) return;
      if (result['status'] == 'paid') {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const RootShell()),
          (route) => false,
        );
      } else if (result['status'] == 'redirect') {
        final url = result['invoice_url'] as String?;
        if (url != null) {
          await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
        }
        setState(() => _awaitingCheckout = true);
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _checkStatus() async {
    setState(() {
      _submitting = true;
      _error = null;
    });
    final app = AppStateScope.of(context);
    try {
      final result = await app.checkBookingPaymentStatus();
      if (!mounted) return;
      if (result['status'] == 'paid') {
        Navigator.of(context).pushAndRemoveUntil(
          MaterialPageRoute(builder: (_) => const RootShell()),
          (route) => false,
        );
      } else {
        setState(
          () => _error = 'Not paid yet. Complete checkout, then check again.',
        );
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final booking = app.currentBooking;
    return Scaffold(
      appBar: AppBar(
        leading: const BackButton(),
        title: const Text('Cost breakdown'),
      ),
      body: booking == null
          ? const Center(child: Text('No booking in progress'))
          : Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
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
                        _CostLine('1 mo advance', booking.advanceAmount),
                        const SizedBox(height: 8),
                        _CostLine('1 mo deposit', booking.depositAmount),
                        const SizedBox(height: 8),
                        _CostLine('1 mo security', booking.securityAmount),
                        const Padding(
                          padding: EdgeInsets.symmetric(vertical: 10),
                          child: Divider(height: 1),
                        ),
                        _CostLine('Total', booking.totalAmount, bold: true),
                      ],
                    ),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      vertical: 12,
                      horizontal: 14,
                    ),
                    decoration: BoxDecoration(
                      color: OasisColors.green,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Column(
                      children: [
                        const Text(
                          'PAY TODAY TO BOOK',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w600,
                            letterSpacing: 0.6,
                          ),
                        ),
                        Text(
                          formatPeso(booking.totalAmount),
                          style: const TextStyle(
                            color: OasisColors.gold,
                            fontSize: 28,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          'Then ${formatPeso(booking.advanceAmount)} rent every month',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: 24),
                  if (_error != null) ...[
                    Text(
                      _error!,
                      style: const TextStyle(
                        color: Colors.redAccent,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                  const Text(
                    'You will choose GCash, Maya, card or bank on the next page.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontSize: 12, color: OasisColors.muted),
                  ),
                  const SizedBox(height: 10),
                  OasisButton(
                    label: 'Proceed to payment',
                    onPressed: _submitting ? null : _pay,
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
                  const SizedBox(height: 12),
                  const Text(
                    '*full refund if cancelled before move-in',
                    style: TextStyle(fontSize: 11, color: OasisColors.muted),
                  ),
                ],
              ),
            ),
    );
  }
}

class _CostLine extends StatelessWidget {
  final String label;
  final double amount;
  final bool bold;
  const _CostLine(this.label, this.amount, {this.bold = false});

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      fontWeight: bold ? FontWeight.w800 : FontWeight.w500,
      fontSize: bold ? 16 : 14,
    );
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: style),
        Text(formatPeso(amount), style: style),
      ],
    );
  }
}
