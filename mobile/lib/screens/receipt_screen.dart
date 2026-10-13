import 'package:flutter/material.dart';

import '../models/payment_record.dart';
import '../models/receipt.dart';
import '../services/api_exception.dart';
import '../services/receipt_export.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_ui.dart';

/// The receipt for one paid bill — laid out like the website's printable
/// receipt: who paid, for which room, what for, how much, when, and the
/// payment reference.
class ReceiptScreen extends StatefulWidget {
  final PaymentRecord payment;

  /// Where a saved PDF or picture goes — the phone's "Save to…" screen.
  final ReceiptSaver saver;

  const ReceiptScreen({
    super.key,
    required this.payment,
    this.saver = ReceiptExport.saveWithSystemDialog,
  });

  @override
  State<ReceiptScreen> createState() => _ReceiptScreenState();
}

class _ReceiptScreenState extends State<ReceiptScreen> {
  /// Wraps exactly what gets saved as a picture or PDF.
  final _paperKey = GlobalKey();

  Receipt? _receipt;
  String? _error;
  bool _started = false;

  /// 'pdf' or 'png' while that file is being made.
  String? _saving;

  /// Saves the receipt as it looks on screen, as a PDF or a picture.
  Future<void> _save(String extension) async {
    final receipt = _receipt;
    if (receipt == null || _saving != null) return;

    final messenger = ScaffoldMessenger.of(context);
    setState(() => _saving = extension);
    try {
      final png = await ReceiptExport.capturePng(_paperKey);
      final bytes = extension == 'pdf'
          ? await ReceiptExport.pdfFromPng(
              png,
              title: 'Receipt #${receipt.number}',
            )
          : png;
      final saved = await widget.saver(
        fileName: 'AJ-City-Oasis-receipt-${receipt.number}.$extension',
        extension: extension,
        bytes: bytes,
      );
      if (saved) {
        messenger.showSnackBar(
          SnackBar(
            content: Text(
              extension == 'pdf'
                  ? 'Receipt saved as a PDF.'
                  : 'Receipt saved as an image.',
            ),
          ),
        );
      }
    } catch (_) {
      messenger.showSnackBar(
        const SnackBar(
          content: Text('The receipt could not be saved. Please try again.'),
        ),
      );
    } finally {
      if (mounted) setState(() => _saving = null);
    }
  }

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
            ? _ReceiptBody(
                key: const ValueKey('receipt'),
                receipt: receipt,
                paperKey: _paperKey,
                saving: _saving,
                onSave: _save,
              )
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
  final GlobalKey paperKey;
  final String? saving;
  final ValueChanged<String> onSave;
  const _ReceiptBody({
    super.key,
    required this.receipt,
    required this.paperKey,
    required this.saving,
    required this.onSave,
  });

  @override
  Widget build(BuildContext context) {
    final r = receipt;
    // Older servers send no lines: fall back to the single description.
    final lines = r.lines.isNotEmpty
        ? r.lines
        : [PaymentLine(label: r.description, amount: r.amount)];
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    );

    Widget saveButton(String extension, IconData icon, String label) {
      return Expanded(
        child: OutlinedButton.icon(
          onPressed: saving == null ? () => onSave(extension) : null,
          icon: saving == extension
              ? const SizedBox(
                  width: 16,
                  height: 16,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Icon(icon, size: 18),
          label: FittedBox(fit: BoxFit.scaleDown, child: Text(label)),
          style: OutlinedButton.styleFrom(
            foregroundColor: OasisColors.green,
            backgroundColor: Colors.white,
            side: const BorderSide(color: OasisColors.green),
            minimumSize: const Size.fromHeight(46),
            padding: const EdgeInsets.symmetric(horizontal: 10),
            shape: shape,
          ),
        ),
      );
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(8, 0, 8, 24),
      children: [
        ListEntrance(
          index: 0,
          // Everything inside this boundary is what "Save" captures: the
          // receipt card on the page's own background, with a little margin.
          child: RepaintBoundary(
            key: paperKey,
            child: ColoredBox(
              color: OasisColors.sand,
              child: Padding(
                padding: const EdgeInsets.fromLTRB(12, 4, 12, 14),
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
                      for (final line in lines)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: Column(
                                  crossAxisAlignment: CrossAxisAlignment.start,
                                  children: [
                                    Text(line.label),
                                    if ((line.note ?? '').isNotEmpty)
                                      Text(
                                        line.note!,
                                        style: const TextStyle(
                                          color: OasisColors.muted,
                                          fontSize: 12,
                                        ),
                                      ),
                                  ],
                                ),
                              ),
                              const SizedBox(width: 12),
                              Text(formatPesoExact(line.amount)),
                            ],
                          ),
                        ),
                      const SizedBox(height: 8),
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
                        value: r.paidAt == null
                            ? '—'
                            : formatDateTime(r.paidAt!),
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
                        child: StatusChip(
                          'Paid in Full',
                          tone: ChipTone.success,
                        ),
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
            ),
          ),
        ),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12),
          child: ListEntrance(
            index: 1,
            child: Row(
              children: [
                saveButton('pdf', Icons.picture_as_pdf_outlined, 'Save as PDF'),
                const SizedBox(width: 10),
                saveButton('png', Icons.image_outlined, 'Save as image'),
              ],
            ),
          ),
        ),
        if (r.billPhotoUrl != null) ...[
          const SizedBox(height: 10),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 12),
            child: ListEntrance(
              index: 2,
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
                  shape: shape,
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
