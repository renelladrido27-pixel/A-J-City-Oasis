import 'package:flutter/material.dart';

import '../models/room.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_button.dart';

/// C7 - Request Transfer (user-initiated only).
class RequestTransferScreen extends StatefulWidget {
  const RequestTransferScreen({super.key});

  @override
  State<RequestTransferScreen> createState() => _RequestTransferScreenState();
}

class _RequestTransferScreenState extends State<RequestTransferScreen> {
  final _reason = TextEditingController();
  bool _submitting = false;

  @override
  void dispose() {
    _reason.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    return RefreshIndicator(
      onRefresh: app.refreshTransfers,
      color: OasisColors.green,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                border: Border.all(color: OasisColors.border, width: 1.4),
                borderRadius: BorderRadius.circular(6),
              ),
              child: const Text(
                'User-initiated only',
                style: TextStyle(fontWeight: FontWeight.w600),
              ),
            ),
            const SizedBox(height: 16),
            for (final room in app.transferableRooms) ...[
              Container(
                padding: const EdgeInsets.all(14),
                margin: const EdgeInsets.only(bottom: 14),
                decoration: BoxDecoration(
                  border: Border.all(color: OasisColors.placeholderGrey),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      '${room.label} · ${formatPeso(room.monthlyRent)}',
                      style: const TextStyle(fontWeight: FontWeight.w600),
                    ),
                    const SizedBox(height: 10),
                    OasisButton(
                      label: 'Request transfer',
                      outlined: true,
                      onPressed: () => _openReasonSheet(context, room),
                    ),
                  ],
                ),
              ),
            ],
            if (app.transferableRooms.isEmpty)
              const Text(
                'No other rooms available right now.',
                style: TextStyle(color: OasisColors.muted),
              ),
            if (app.transferRequests.isNotEmpty) ...[
              const SizedBox(height: 4),
              for (final t in app.transferRequests)
                Padding(
                  padding: const EdgeInsets.only(bottom: 8),
                  child: Text(
                    '${t.fromRoomLabel} → ${t.toRoomLabel} · ${t.status}',
                    style: const TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
            ],
          ],
        ),
      ),
    );
  }

  void _openReasonSheet(BuildContext context, Room room) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      builder: (sheetContext) {
        return StatefulBuilder(
          builder: (sheetContext, setSheetState) {
            return Padding(
              padding: EdgeInsets.only(
                left: 20,
                right: 20,
                top: 20,
                bottom: MediaQuery.of(sheetContext).viewInsets.bottom + 20,
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Transfer to ${room.label}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 16,
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _reason,
                    decoration: const InputDecoration(
                      hintText: 'Reason + date',
                    ),
                  ),
                  const SizedBox(height: 16),
                  OasisButton(
                    label: 'Submit request',
                    onPressed: _submitting
                        ? null
                        : () async {
                            setSheetState(() => _submitting = true);
                            final app = AppStateScope.of(context);
                            try {
                              await app.requestTransfer(
                                room,
                                _reason.text.trim(),
                              );
                              _reason.clear();
                              if (context.mounted) {
                                Navigator.of(sheetContext).pop();
                              }
                            } on ApiException catch (e) {
                              if (context.mounted) {
                                ScaffoldMessenger.of(context).showSnackBar(
                                  SnackBar(content: Text(e.message)),
                                );
                              }
                            } finally {
                              setSheetState(() => _submitting = false);
                            }
                          },
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}
