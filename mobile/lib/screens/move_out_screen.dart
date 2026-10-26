import 'package:flutter/material.dart';

import '../models/lease.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_button.dart';
import '../widgets/oasis_ui.dart';

/// "Move out" — the website's Request Move-Out form: pick the day, say why
/// (optional), and the admin inspects the room before settling the deposit.
class MoveOutScreen extends StatefulWidget {
  final Lease lease;
  const MoveOutScreen({super.key, required this.lease});

  @override
  State<MoveOutScreen> createState() => _MoveOutScreenState();
}

class _MoveOutScreenState extends State<MoveOutScreen> {
  final _reason = TextEditingController();
  DateTime? _date;
  String? _error;
  bool _submitting = false;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  Future<void> _pickDate() async {
    final today = DateUtils.dateOnly(DateTime.now());
    final picked = await showDatePicker(
      context: context,
      initialDate: _date ?? today,
      firstDate: today,
      lastDate: today.add(const Duration(days: 365)),
    );
    if (picked != null) setState(() => _date = picked);
  }

  Future<void> _submit() async {
    final date = _date;
    if (date == null) {
      setState(() => _error = 'Please choose your move-out date.');
      return;
    }
    final yes = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Request move-out?'),
        content: Text(
          'You are asking to move out of ${widget.lease.roomLabel} on '
          '${formatLongDate(date)}. The admin will inspect the room before '
          'your deposit is settled.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Not yet'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(backgroundColor: OasisColors.green),
            child: const Text('Request'),
          ),
        ],
      ),
    );
    if (yes != true || !mounted) return;

    setState(() {
      _submitting = true;
      _error = null;
    });
    final app = AppStateScope.of(context);
    try {
      final message = await app.requestMoveOut(
        widget.lease,
        date: date,
        reason: _reason.text.trim(),
      );
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(message)));
      Navigator.of(context).pop();
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: const BackButton(),
        title: const Text('Move out'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
        children: [
          const OasisCard(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                IconBadge(Icons.info_outline),
                SizedBox(width: 12),
                Expanded(
                  child: Text(
                    'Rent already paid is not refunded, even if you leave '
                    'before the month ends. Your 1-month deposit is refunded if '
                    'all your rent is paid (otherwise it pays the unpaid rent), '
                    'and your security deposit is refunded less the cost of any '
                    'damage found when the admin inspects the room. The final '
                    'amount is set after the inspection and '
                    'released by the admin. If deductions exceed the deposit, '
                    'you settle the difference directly with the admin, '
                    'outside the app.',
                    style: TextStyle(fontSize: 13, height: 1.4),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 18),
          SectionLabel(
            '${widget.lease.roomLabel}'
                    '${widget.lease.building.isEmpty ? '' : ' · ${widget.lease.building}'}'
                .toUpperCase(),
          ),
          const SizedBox(height: 10),
          InkWell(
            onTap: _pickDate,
            child: InputDecorator(
              decoration: const InputDecoration(
                hintText: 'Move-out date',
                suffixIcon: Icon(Icons.event_outlined),
              ),
              isEmpty: _date == null,
              child: _date == null
                  ? null
                  : Text(
                      formatLongDate(_date!),
                      style: const TextStyle(fontSize: 15),
                    ),
            ),
          ),
          const SizedBox(height: 12),
          TextField(
            controller: _reason,
            minLines: 3,
            maxLines: 5,
            maxLength: 2000,
            decoration: const InputDecoration(hintText: 'Reason (optional)'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 4),
            Text(
              _error!,
              style: const TextStyle(color: Colors.redAccent, fontSize: 12),
            ),
          ],
          const SizedBox(height: 16),
          OasisButton(
            label: 'Submit request',
            onPressed: _submitting ? null : _submit,
          ),
          if (_submitting)
            const Padding(
              padding: EdgeInsets.only(top: 16),
              child: Center(child: CircularProgressIndicator()),
            ),
        ],
      ),
    );
  }
}
