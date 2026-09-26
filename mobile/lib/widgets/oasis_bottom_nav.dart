import 'package:flutter/material.dart';

import '../theme.dart';

enum OasisTab { home, rental, pay, alerts, profile }

/// The 5-tab bottom nav present on every authenticated / public browse screen
/// in the wireframes (Home · Rental · Pay · Alerts · Profile).
class OasisBottomNav extends StatelessWidget {
  final OasisTab current;
  final ValueChanged<OasisTab> onSelect;

  const OasisBottomNav({
    super.key,
    required this.current,
    required this.onSelect,
  });

  @override
  Widget build(BuildContext context) {
    const items = [
      (OasisTab.home, Icons.home_outlined, 'Home'),
      (OasisTab.rental, Icons.apartment_outlined, 'Rental'),
      (OasisTab.pay, Icons.payments_outlined, 'Pay'),
      (OasisTab.alerts, Icons.notifications_outlined, 'Alerts'),
      (OasisTab.profile, Icons.person_outline, 'Profile'),
    ];
    return DecoratedBox(
      decoration: const BoxDecoration(
        color: Colors.white,
        border: Border(top: BorderSide(color: OasisColors.ink, width: 1.4)),
      ),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: 60,
          child: Row(
            children: [
              for (final item in items)
                Expanded(
                  child: InkWell(
                    onTap: () => onSelect(item.$1),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          item.$2,
                          size: 22,
                          color: current == item.$1
                              ? OasisColors.green
                              : OasisColors.muted,
                        ),
                        const SizedBox(height: 2),
                        Text(
                          item.$3,
                          style: TextStyle(
                            fontSize: 11,
                            color: current == item.$1
                                ? OasisColors.green
                                : OasisColors.muted,
                            fontWeight: current == item.$1
                                ? FontWeight.w700
                                : FontWeight.w400,
                            decoration: current == item.$1
                                ? TextDecoration.underline
                                : null,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
