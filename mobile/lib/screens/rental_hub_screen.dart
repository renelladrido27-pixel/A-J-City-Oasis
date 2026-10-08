import 'package:flutter/material.dart';

import '../widgets/oasis_ui.dart';
import 'maintenance_screen.dart';
import 'my_rental_screen.dart';
import 'request_transfer_screen.dart';

enum RentalSection { myRental, transfer, maintenance }

/// Groups C5 (My Rental), C7 (Request Transfer), and C8 (Maintenance) under
/// the shared "Rental" bottom-nav tab, with the same pill switcher the Alerts
/// tab uses for its filters.
class RentalHubScreen extends StatefulWidget {
  /// Which of the three to show first (e.g. Maintenance, when arriving from
  /// a maintenance notification).
  final RentalSection initialSection;

  /// "Pay now" on My Rental's next-due card.
  final VoidCallback? onPayNow;

  const RentalHubScreen({
    super.key,
    this.initialSection = RentalSection.myRental,
    this.onPayNow,
  });

  @override
  State<RentalHubScreen> createState() => _RentalHubScreenState();
}

class _RentalHubScreenState extends State<RentalHubScreen> {
  static const _sections = [
    (section: RentalSection.myRental, label: 'My rental'),
    (section: RentalSection.transfer, label: 'Transfer'),
    (section: RentalSection.maintenance, label: 'Maintenance'),
  ];

  late RentalSection _section = widget.initialSection;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        const Padding(
          padding: EdgeInsets.fromLTRB(20, 16, 20, 12),
          child: Text(
            'Rental',
            style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
          ),
        ),
        PillRow(
          fit: true,
          pills: [
            for (final s in _sections)
              OasisPill(
                label: s.label,
                selected: _section == s.section,
                onTap: () => setState(() => _section = s.section),
              ),
          ],
        ),
        const SizedBox(height: 8),
        Expanded(
          child: FadeThrough(
            child: KeyedSubtree(
              key: ValueKey(_section),
              child: switch (_section) {
                RentalSection.myRental => MyRentalScreen(
                  onPayNow: widget.onPayNow,
                ),
                RentalSection.transfer => const RequestTransferScreen(),
                RentalSection.maintenance => const MaintenanceScreen(),
              },
            ),
          ),
        ),
      ],
    );
  }
}
