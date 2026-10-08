import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/lease.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_ui.dart';
import '../widgets/room_photo.dart';

/// C5 - My Rental (tenant portal home for the Rental tab).
class MyRentalScreen extends StatelessWidget {
  /// "Pay now" on the next-due card — the shell switches to the Pay tab.
  final VoidCallback? onPayNow;

  const MyRentalScreen({super.key, this.onPayNow});

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final lease = app.lease;

    return RefreshIndicator(
      onRefresh: () => Future.wait([app.refreshLease(), app.refreshPayments()]),
      color: OasisColors.green,
      child: lease == null
          ? ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: const [
                SizedBox(height: 40),
                EmptyState(
                  icon: Icons.apartment_outlined,
                  message: 'No active lease',
                  hint: 'Book a room from the Home tab to get started.',
                ),
              ],
            )
          : _LeaseDetail(
              lease: lease,
              outstandingBalance: app.outstandingBalance,
              nextDueDate: app.nextDueDate,
              onPayNow: onPayNow,
            ),
    );
  }
}

class _LeaseDetail extends StatelessWidget {
  final Lease lease;
  final double outstandingBalance;
  final DateTime? nextDueDate;
  final VoidCallback? onPayNow;
  const _LeaseDetail({
    required this.lease,
    required this.outstandingBalance,
    required this.nextDueDate,
    required this.onPayNow,
  });

  Future<void> _openLeaseCopy(BuildContext context) async {
    if (lease.documentUrl != null) {
      await launchUrl(
        Uri.parse(lease.documentUrl!),
        mode: LaunchMode.externalApplication,
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('No lease document has been uploaded yet.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final hasDue = nextDueDate != null && outstandingBalance > 0;
    final sections = <Widget>[
      // The room: photos, name, and where things stand.
      OasisCard(
        padding: EdgeInsets.zero,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            RoomGallery(images: lease.roomImages, height: 170, radius: 0),
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    lease.roomLabel,
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  if (lease.property.isNotEmpty)
                    Text(
                      lease.property,
                      style: const TextStyle(color: OasisColors.muted),
                    ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 6,
                    children: [
                      StatusChip.forStatus(lease.leaseStatus),
                      StatusChip.forStatus(
                        outstandingBalance > 0 ? 'Payment due' : 'Up to date',
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),

      // Next due — the thing a tenant opens this tab to check.
      OasisCard(
        highlighted: hasDue,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                IconBadge(
                  hasDue ? Icons.event_outlined : Icons.check,
                  filled: hasDue,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        hasDue ? 'Next due' : 'Nothing due right now',
                        style: TextStyle(
                          color: hasDue ? OasisColors.muted : OasisColors.ink,
                          fontWeight: hasDue
                              ? FontWeight.w500
                              : FontWeight.w700,
                          fontSize: hasDue ? 12 : 15,
                        ),
                      ),
                      Text(
                        hasDue
                            ? formatLongDate(nextDueDate!)
                            : 'You are all paid up.',
                        style: TextStyle(
                          fontWeight: hasDue
                              ? FontWeight.w700
                              : FontWeight.w400,
                          fontSize: hasDue ? 15 : 12,
                          color: hasDue ? OasisColors.ink : OasisColors.muted,
                        ),
                      ),
                    ],
                  ),
                ),
                if (hasDue)
                  Text(
                    formatPeso(outstandingBalance),
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w800,
                      color: OasisColors.green,
                    ),
                  ),
              ],
            ),
            if (hasDue && onPayNow != null) ...[
              const SizedBox(height: 14),
              FilledButton(
                onPressed: onPayNow,
                style: FilledButton.styleFrom(
                  backgroundColor: OasisColors.gold,
                  foregroundColor: OasisColors.ink,
                  minimumSize: const Size.fromHeight(46),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
                child: const Text(
                  'Pay now',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ],
          ],
        ),
      ),

      // The lease in figures, and the signed copy.
      OasisCard(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Column(
          children: [
            _DetailRow(
              icon: Icons.payments_outlined,
              label: 'Monthly rent',
              value: formatPeso(lease.monthlyRent),
            ),
            const Divider(height: 1, indent: 68),
            _DetailRow(
              icon: Icons.login_outlined,
              label: 'Moved in',
              value: formatLongDate(lease.moveInDate),
            ),
            const Divider(height: 1, indent: 68),
            InkWell(
              onTap: () => _openLeaseCopy(context),
              child: const _DetailRow(
                icon: Icons.description_outlined,
                label: 'Lease copy',
                value: 'View',
                chevron: true,
              ),
            ),
          ],
        ),
      ),
    ];

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      children: [
        for (final (i, section) in sections.indexed)
          Padding(
            padding: const EdgeInsets.only(bottom: 14),
            child: ListEntrance(index: i, child: section),
          ),
      ],
    );
  }
}

class _DetailRow extends StatelessWidget {
  final IconData icon;
  final String label;
  final String value;
  final bool chevron;
  const _DetailRow({
    required this.icon,
    required this.label,
    required this.value,
    this.chevron = false,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          IconBadge(icon),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              label,
              style: const TextStyle(color: OasisColors.muted),
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontWeight: FontWeight.w700,
              color: chevron ? OasisColors.green : OasisColors.ink,
            ),
          ),
          if (chevron)
            const Icon(Icons.chevron_right, color: OasisColors.green, size: 20),
        ],
      ),
    );
  }
}
