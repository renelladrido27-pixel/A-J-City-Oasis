import 'package:flutter/material.dart';

import '../theme.dart';
import 'maintenance_screen.dart';
import 'my_rental_screen.dart';
import 'request_transfer_screen.dart';

enum _RentalSection { myRental, transfer, maintenance }

/// Groups C5 (My Rental), C7 (Request Transfer), and C8 (Maintenance) under
/// the shared "Rental" bottom-nav tab. The wireframes don't show how a tenant
/// moves between these three screens, so a small segmented control is added
/// here purely for navigation — the screen bodies below are otherwise a
/// direct match of each wireframe.
class RentalHubScreen extends StatefulWidget {
  const RentalHubScreen({super.key});

  @override
  State<RentalHubScreen> createState() => _RentalHubScreenState();
}

class _RentalHubScreenState extends State<RentalHubScreen> {
  _RentalSection _section = _RentalSection.myRental;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 8),
          child: Row(
            children: [
              _SegButton(
                icon: Icons.meeting_room_outlined,
                tooltip: 'My Rental',
                selected: _section == _RentalSection.myRental,
                onTap: () => setState(() => _section = _RentalSection.myRental),
              ),
              const SizedBox(width: 10),
              _SegButton(
                icon: Icons.swap_horiz,
                tooltip: 'Transfer',
                selected: _section == _RentalSection.transfer,
                onTap: () => setState(() => _section = _RentalSection.transfer),
              ),
              const SizedBox(width: 10),
              _SegButton(
                icon: Icons.build_outlined,
                tooltip: 'Maintenance',
                selected: _section == _RentalSection.maintenance,
                onTap: () =>
                    setState(() => _section = _RentalSection.maintenance),
              ),
            ],
          ),
        ),
        Expanded(
          child: switch (_section) {
            _RentalSection.myRental => const MyRentalScreen(),
            _RentalSection.transfer => const RequestTransferScreen(),
            _RentalSection.maintenance => const MaintenanceScreen(),
          },
        ),
      ],
    );
  }
}

class _SegButton extends StatelessWidget {
  final IconData icon;
  final String tooltip;
  final bool selected;
  final VoidCallback onTap;
  const _SegButton({
    required this.icon,
    required this.tooltip,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? OasisColors.green : Colors.transparent,
            border: Border.all(
              color: selected ? OasisColors.green : OasisColors.placeholderGrey,
            ),
            borderRadius: BorderRadius.circular(20),
          ),
          child: Icon(
            icon,
            size: 20,
            color: selected ? Colors.white : OasisColors.muted,
          ),
        ),
      ),
    );
  }
}
