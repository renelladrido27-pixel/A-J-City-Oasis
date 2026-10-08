import 'package:flutter/material.dart';

import '../models/app_notification.dart';
import '../theme.dart';

/// Icon and label for each notification category — the same groupings as the
/// website's notification filter (Bookings, Lease, Payments, Maintenance,
/// Announcements).
({IconData icon, String label}) notificationCategoryMeta(String category) {
  return switch (category) {
    'booking' => (icon: Icons.event_available_outlined, label: 'Bookings'),
    'lease' => (icon: Icons.description_outlined, label: 'Lease'),
    'payment' => (icon: Icons.payments_outlined, label: 'Payments'),
    'maintenance' => (icon: Icons.build_outlined, label: 'Maintenance'),
    'announcement' => (icon: Icons.campaign_outlined, label: 'Announcements'),
    _ => (icon: Icons.notifications_outlined, label: 'Notifications'),
  };
}

/// One notification, laid out like a row of the website's notifications page:
/// unread ones are tinted with a dot beside the title, then the message, then
/// View / Mark as read. Tapping the card is the same as View.
class NotificationCard extends StatelessWidget {
  final AppNotification notification;

  /// Null when this notification has nowhere to go.
  final VoidCallback? onView;
  final VoidCallback onToggleRead;

  const NotificationCard({
    super.key,
    required this.notification,
    required this.onView,
    required this.onToggleRead,
  });

  @override
  Widget build(BuildContext context) {
    final n = notification;
    final unread = !n.isRead;
    final meta = notificationCategoryMeta(n.category);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOut,
      decoration: BoxDecoration(
        color: unread ? OasisColors.unreadTint : Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(
          color: unread
              ? OasisColors.green.withValues(alpha: 0.35)
              : OasisColors.hairline,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: unread ? 0.07 : 0.04),
            blurRadius: unread ? 14 : 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          borderRadius: BorderRadius.circular(14),
          onTap: onView,
          child: Padding(
            padding: const EdgeInsets.fromLTRB(14, 14, 14, 10),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 280),
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: unread ? OasisColors.green : OasisColors.sand,
                    shape: BoxShape.circle,
                  ),
                  child: Icon(
                    meta.icon,
                    size: 20,
                    color: unread ? Colors.white : OasisColors.green,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Text(
                              n.title,
                              style: TextStyle(
                                fontSize: 15,
                                height: 1.25,
                                fontWeight: unread
                                    ? FontWeight.w700
                                    : FontWeight.w600,
                              ),
                            ),
                          ),
                          AnimatedScale(
                            scale: unread ? 1 : 0,
                            duration: const Duration(milliseconds: 220),
                            child: Container(
                              key: const Key('unread-dot'),
                              margin: const EdgeInsets.only(left: 8, top: 5),
                              width: 9,
                              height: 9,
                              decoration: const BoxDecoration(
                                color: OasisColors.gold,
                                shape: BoxShape.circle,
                              ),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 2),
                      Text(
                        [
                          meta.label,
                          n.timeAgo,
                        ].where((s) => s.isNotEmpty).join(' · '),
                        style: const TextStyle(
                          color: OasisColors.muted,
                          fontSize: 12,
                        ),
                      ),
                      if (n.message.isNotEmpty) ...[
                        const SizedBox(height: 8),
                        Text(
                          n.message,
                          style: const TextStyle(
                            fontSize: 13.5,
                            height: 1.4,
                            color: OasisColors.ink,
                          ),
                        ),
                      ],
                      const SizedBox(height: 6),
                      Wrap(
                        spacing: 8,
                        children: [
                          if (onView != null)
                            FilledButton(
                              onPressed: onView,
                              style: FilledButton.styleFrom(
                                backgroundColor: OasisColors.green,
                                foregroundColor: Colors.white,
                                visualDensity: VisualDensity.compact,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 18,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(8),
                                ),
                              ),
                              child: const Text('View'),
                            ),
                          OutlinedButton(
                            onPressed: onToggleRead,
                            style: OutlinedButton.styleFrom(
                              foregroundColor: OasisColors.ink,
                              visualDensity: VisualDensity.compact,
                              padding: const EdgeInsets.symmetric(
                                horizontal: 12,
                              ),
                              side: const BorderSide(
                                color: OasisColors.placeholderGrey,
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(8),
                              ),
                            ),
                            child: Text(
                              unread ? 'Mark as read' : 'Mark as unread',
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

/// Fades and slides a list row in; rows further down start a beat later so
/// the list arrives top to bottom.
class ListEntrance extends StatelessWidget {
  final int index;
  final Widget child;
  const ListEntrance({super.key, required this.index, required this.child});

  @override
  Widget build(BuildContext context) {
    final delayMs = 45 * index.clamp(0, 8);
    final totalMs = 320 + delayMs;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: totalMs),
      curve: Interval(delayMs / totalMs, 1, curve: Curves.easeOutCubic),
      builder: (context, t, child) => Opacity(
        opacity: t,
        child: Transform.translate(
          offset: Offset(0, 14 * (1 - t)),
          child: child,
        ),
      ),
      child: child,
    );
  }
}
