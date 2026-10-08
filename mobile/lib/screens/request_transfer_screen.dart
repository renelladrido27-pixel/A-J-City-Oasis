import 'package:flutter/material.dart';

import '../models/room.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_button.dart';
import '../widgets/oasis_ui.dart';

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
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            const OasisCard(
              child: Row(
                children: [
                  IconBadge(Icons.info_outline),
                  SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Pick a room to move to. The admin reviews every '
                      'request; any difference in deposit is settled on '
                      'approval.',
                      style: TextStyle(fontSize: 13, height: 1.35),
                    ),
                  ),
                ],
              ),
            ),
            if (app.transferRequests.isNotEmpty) ...[
              const SizedBox(height: 20),
              const SectionLabel('YOUR REQUESTS'),
              const SizedBox(height: 10),
              for (final (i, t) in app.transferRequests.indexed)
                Padding(
                  key: ValueKey('request-${t.id}'),
                  padding: const EdgeInsets.only(bottom: 10),
                  child: ListEntrance(
                    index: i,
                    child: OasisCard(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 12,
                      ),
                      child: Row(
                        children: [
                          const IconBadge(Icons.swap_horiz, size: 36),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Row(
                                  children: [
                                    Text(
                                      t.fromRoomLabel,
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                    const Padding(
                                      padding: EdgeInsets.symmetric(
                                        horizontal: 6,
                                      ),
                                      child: Icon(
                                        Icons.arrow_forward,
                                        size: 15,
                                        color: OasisColors.muted,
                                      ),
                                    ),
                                    Flexible(
                                      child: Text(
                                        t.toRoomLabel,
                                        overflow: TextOverflow.ellipsis,
                                        style: const TextStyle(
                                          fontWeight: FontWeight.w700,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                                Text(
                                  'Requested ${formatShortDate(t.requestedAt.toLocal())}',
                                  style: const TextStyle(
                                    color: OasisColors.muted,
                                    fontSize: 12,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          StatusChip.forStatus(t.status),
                        ],
                      ),
                    ),
                  ),
                ),
            ],
            const SizedBox(height: 20),
            const SectionLabel('ROOMS YOU CAN MOVE TO'),
            const SizedBox(height: 10),
            for (final (i, room) in app.transferableRooms.indexed)
              Padding(
                key: ValueKey('room-${room.id}'),
                padding: const EdgeInsets.only(bottom: 10),
                child: ListEntrance(
                  index: i,
                  child: OasisCard(
                    padding: const EdgeInsets.fromLTRB(14, 12, 10, 12),
                    child: Row(
                      children: [
                        const IconBadge(Icons.bed_outlined, size: 36),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Room ${room.number}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              Text(
                                [
                                  if (room.floorLabel.isNotEmpty)
                                    room.floorLabel,
                                  '${formatPeso(room.monthlyRent)} / month',
                                ].join(' · '),
                                style: const TextStyle(
                                  color: OasisColors.muted,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ),
                        ),
                        FilledButton(
                          onPressed: () => _openReasonSheet(context, room),
                          style: FilledButton.styleFrom(
                            backgroundColor: OasisColors.green,
                            foregroundColor: Colors.white,
                            visualDensity: VisualDensity.compact,
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          child: const Text('Request'),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            if (app.transferableRooms.isEmpty)
              const EmptyState(
                icon: Icons.bed_outlined,
                message: 'No other rooms available right now.',
              ),
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
