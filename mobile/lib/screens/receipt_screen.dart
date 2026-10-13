import 'package:flutter/material.dart';

import '../models/payment_record.dart';
import '../models/receipt.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_ui.dart';

/// The receipt for one paid bill — laid out like the website's printable
/// receipt: who paid, for which room, what for, how much, when, and the
/// payment reference.
class ReceiptScreen extends StatefulWidget {
  final PaymentRecord payment;
  const ReceiptScreen({super.key, required this.payment});

  @override
  State<ReceiptScreen> createState() => _ReceiptScreenState();
}

class _ReceiptScreenState extends State<ReceiptScreen> {
  Receipt? _receipt;
  String? _error;
  bool _started = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started) {
      _started = true;
      _load();
    }
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final receipt = await AppStateScope.of(
        context,
      ).fetchReceipt(widget.payment.id);
      if (mounted) setState(() => _receipt = receipt);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final receipt = _receipt;
    return Scaffold(
      backgroundColor: OasisColors.sand,
      appBar: AppBar(
        backgroundColor: OasisColors.sand,
        leading: const BackButton(),
        title: const Text('Receipt'),
      ),
      body: AnimatedSwitcher(
        duration: const Duration(milliseconds: 260),
        child: receipt != null
            ? _ReceiptBody(key: const ValueKey('receipt'), receipt: receipt)
            : _error != null
            ? Padding(
                key: const ValueKey('error'),
                padding: const EdgeInsets.all(20),
                child: OasisCard(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _error!,
                        style: const TextStyle(color: Colors.redAccent),
                      ),
                      const SizedBox(height: 4),
                      TextButton.icon(
                        onPressed: _load,
                        icon: const Icon(Icons.refresh, size: 18),
                        label: const Text('Try again'),
                      ),
                    ],
                  ),
                ),
              )
            : const Padding(
                key: ValueKey('loading'),
                padding: EdgeInsets.all(20),
                child: Skeleton(height: 420),
              ),
      ),
    );
  }
}

class _ReceiptBody extends StatelessWidget {
  final Receipt receipt;
  const _ReceiptBody({super.key, required this.receipt});

  @override
  Widget build(BuildContext context) {
    final r = receipt;
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
      children: [
        ListEntrance(
          index: 0,
          child: OasisCard(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          FittedBox(
                            fit: BoxFit.scaleDown,
                            alignment: Alignment.centerLeft,
                            child: Text(
                              r.business,
                              style: const TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.w800,
                                color: OasisColors.green,
                              ),
                            ),
                          ),
                          if (r.businessAddress.isNotEmpty)
                            Text(
                              r.businessAddress,
                              style: const TextStyle(
                                color: OasisColors.muted,
                                fontSize: 12,
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(width: 12),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        const Text(
                          'Receipt',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        Text(
                          '#${r.number}',
                          style: const TextStyle(
                            color: OasisColors.muted,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 14),
                  child: Divider(
                    height: 2,
                    thickness: 2,
                    color: OasisColors.green,
                  ),
                ),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: _Field(
                        label: 'BILLED TO',
                        value: r.billedToName,
                        detail: r.billedToEmail,
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: _Field(
                        label: 'ROOM',
                        value: r.roomNumber ?? '—',
                        detail: r.property ?? '',
                        alignEnd: true,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                const Row(
                  children: [
                    Expanded(child: _Caption('DESCRIPTION')),
                    _Caption('AMOUNT'),
                  ],
                ),
                const Divider(height: 14),
                Row(
                  children: [
                    Expanded(child: Text(r.description)),
                    Text(formatPesoExact(r.amount)),
                  ],
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    color: OasisColors.unreadTint,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(
                      color: OasisColors.green.withValues(alpha: 0.25),
                    ),
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Flexible(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          child: Text(
                            'Total Paid',
                            style: TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      // Both sides shrink rather than overflow on a narrow
                      // phone or with a large system font.
                      Flexible(
                        child: FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: Alignment.centerRight,
                          child: Text(
                            formatPesoExact(r.amount),
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.w800,
                              color: OasisColors.green,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                _Field(
                  label: 'DATE PAID',
                  value: r.paidAt == null ? '—' : formatDateTime(r.paidAt!),
                ),
                const SizedBox(height: 12),
                const _Caption('PAYMENT REFERENCE'),
                const SizedBox(height: 2),
                // Long gateway ids: selectable, so it can be copied when asked for.
                SelectableText(
                  r.reference,
                  style: const TextStyle(fontWeight: FontWeight.w600),
                ),
                const Divider(height: 32),
                const Center(
                  child: StatusChip('Paid in Full', tone: ChipTone.success),
                ),
                const SizedBox(height: 10),
                Text(
                  'This is a system-generated receipt from ${r.business}. '
                  'No signature required.',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: OasisColors.muted,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
          ),
        ),
        if (r.billPhotoUrl != null) ...[
          const SizedBox(height: 14),
          ListEntrance(
            index: 1,
            child: OutlinedButton.icon(
              onPressed: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => BillPhotoScreen(url: r.billPhotoUrl!),
                ),
              ),
              icon: const Icon(Icons.image_outlined, size: 18),
              label: const Text('View bill photo'),
              style: OutlinedButton.styleFrom(
                foregroundColor: OasisColors.green,
                backgroundColor: Colors.white,
                side: const BorderSide(color: OasisColors.green),
                minimumSize: const Size.fromHeight(46),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

class _Caption extends StatelessWidget {
  final String text;
  const _Caption(this.text);

  @override
  Widget build(BuildContext context) {
    return Text(
      text,
      style: const TextStyle(
        color: OasisColors.muted,
        fontSize: 11,
        letterSpacing: 0.4,
        fontWeight: FontWeight.w600,
      ),
    );
  }
}

class _Field extends StatelessWidget {
  final String label;
  final String value;
  final String detail;
  final bool alignEnd;
  const _Field({
    required this.label,
    required this.value,
    this.detail = '',
    this.alignEnd = false,
  });

  @override
  Widget build(BuildContext context) {
    final align = alignEnd ? TextAlign.end : TextAlign.start;
    return Column(
      crossAxisAlignment: alignEnd
          ? CrossAxisAlignment.end
          : CrossAxisAlignment.start,
      children: [
        _Caption(label),
        const SizedBox(height: 2),
        Text(
          value,
          textAlign: align,
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        if (detail.isNotEmpty)
          Text(
            detail,
            textAlign: align,
            style: const TextStyle(color: OasisColors.muted, fontSize: 12),
          ),
      ],
    );
  }
}

/// The photo of a utility bill, full screen — pinch to zoom in on the figures.
class BillPhotoScreen extends StatelessWidget {
  final String url;
  const BillPhotoScreen({super.key, required this.url});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        leading: const BackButton(),
        title: const Text('Bill photo'),
      ),
      body: InteractiveViewer(
        maxScale: 5,
        child: Center(
          child: Image.network(
            url,
            fit: BoxFit.contain,
            loadingBuilder: (context, child, progress) => progress == null
                ? child
                : const CircularProgressIndicator(color: Colors.white),
            errorBuilder: (_, _, _) => const Padding(
              padding: EdgeInsets.all(24),
              child: Text(
                'The photo could not be loaded. Check your connection and try again.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.white70),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
