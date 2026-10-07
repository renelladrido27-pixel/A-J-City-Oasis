import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/lease.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/dashed_divider.dart';
import '../widgets/room_photo.dart';

/// C5 - My Rental (tenant portal home for the Rental tab).
class MyRentalScreen extends StatelessWidget {
  const MyRentalScreen({super.key});

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
                Padding(
                  padding: EdgeInsets.only(top: 80),
                  child: Center(
                    child: Text(
                      'No active lease',
                      style: TextStyle(color: OasisColors.muted),
                    ),
                  ),
                ),
              ],
            )
          : _LeaseDetail(
              lease: lease,
              outstandingBalance: app.outstandingBalance,
              nextDueDate: app.nextDueDate,
            ),
    );
  }
}

class _LeaseDetail extends StatelessWidget {
  final Lease lease;
  final double outstandingBalance;
  final DateTime? nextDueDate;
  const _LeaseDetail({
    required this.lease,
    required this.outstandingBalance,
    required this.nextDueDate,
  });

  @override
  Widget build(BuildContext context) {
    final paymentStatus = outstandingBalance > 0 ? 'Payment due' : 'Up to date';
    return SingleChildScrollView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          RoomGallery(images: lease.roomImages, height: 170),
          const SizedBox(height: 16),
          Text(
            '${lease.roomLabel} · ${lease.property}',
            style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 4),
          Text(
            '${formatPeso(lease.monthlyRent)}/mo · moved in ${formatShortDate(lease.moveInDate)}',
            style: const TextStyle(color: OasisColors.muted),
          ),
          const SizedBox(height: 4),
          Text(
            'Lease: ${lease.leaseStatus} · Payment: $paymentStatus',
            style: const TextStyle(color: OasisColors.muted),
          ),
          const SizedBox(height: 12),
          InkWell(
            onTap: () async {
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
            },
            child: const Row(
              children: [
                Text(
                  'View lease copy',
                  style: TextStyle(
                    fontWeight: FontWeight.w600,
                    decoration: TextDecoration.underline,
                  ),
                ),
                SizedBox(width: 4),
                Icon(Icons.arrow_forward, size: 16),
              ],
            ),
          ),
          const DashedDivider(),
          Text(
            nextDueDate == null
                ? 'Nothing due right now'
                : 'Next due ${formatShortDate(nextDueDate!)} · ${formatPeso(outstandingBalance)}',
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }
}
