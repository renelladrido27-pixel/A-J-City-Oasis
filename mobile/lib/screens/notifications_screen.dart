import 'package:flutter/material.dart';

import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/dashed_divider.dart';

/// C9 - Notifications (All / Pay / Maint. filter).
class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  String _filter = 'All';

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final items = app.notifications.where((n) {
      if (_filter == 'All') return true;
      if (_filter == 'Pay') {
        return n.category == 'payment' || n.category == 'utility';
      }
      return n.category == 'maintenance';
    }).toList();

    return RefreshIndicator(
      onRefresh: app.refreshNotifications,
      color: OasisColors.green,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                for (final f in const [
                  (
                    key: 'All',
                    tooltip: 'All',
                    icon: Icons.notifications_outlined,
                  ),
                  (
                    key: 'Pay',
                    tooltip: 'Payments',
                    icon: Icons.payments_outlined,
                  ),
                  (
                    key: 'Maint.',
                    tooltip: 'Maintenance',
                    icon: Icons.build_outlined,
                  ),
                ]) ...[
                  _FilterChip(
                    tooltip: f.tooltip,
                    icon: f.icon,
                    selected: _filter == f.key,
                    onTap: () => setState(() => _filter = f.key),
                  ),
                  const SizedBox(width: 8),
                ],
              ],
            ),
            const SizedBox(height: 20),
            for (final n in items) ...[
              Text(
                '${n.title} · ${n.timeAgo}',
                style: const TextStyle(fontWeight: FontWeight.w500),
              ),
              const DashedDivider(verticalGap: 14),
            ],
            if (items.isEmpty)
              const Padding(
                padding: EdgeInsets.only(top: 20),
                child: Text(
                  'No notifications',
                  style: TextStyle(color: OasisColors.muted),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

class _FilterChip extends StatelessWidget {
  final String tooltip;
  final IconData icon;
  final bool selected;
  final VoidCallback onTap;
  const _FilterChip({
    required this.tooltip,
    required this.icon,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Tooltip(
      message: tooltip,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(6),
        child: Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: selected ? const Color(0xFFE9E7DE) : Colors.transparent,
            border: Border.all(color: OasisColors.border, width: 1.4),
            borderRadius: BorderRadius.circular(6),
          ),
          child: Icon(icon, size: 20, color: OasisColors.ink),
        ),
      ),
    );
  }
}
