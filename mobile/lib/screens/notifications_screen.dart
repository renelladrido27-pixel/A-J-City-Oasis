import 'package:flutter/material.dart';

import '../models/announcement.dart';
import '../models/app_notification.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/notification_card.dart';

/// C9 - Notifications. Works the way the website's notifications page does:
/// a category filter, "Mark all as read", and per notification View / Mark as
/// read / Mark as unread. The extra News filter lists the admin's
/// announcements (a separate page on the website).
class NotificationsScreen extends StatefulWidget {
  /// Called after "View" on a notification that belongs to another part of
  /// the app (payments, the rental, maintenance…) — the shell switches tabs.
  final ValueChanged<AppNotification> onOpen;

  const NotificationsScreen({super.key, required this.onOpen});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  static const _news = 'news';
  static const _filters = [
    (key: 'all', label: 'All'),
    (key: 'booking', label: 'Bookings'),
    (key: 'lease', label: 'Lease'),
    (key: 'payment', label: 'Payments'),
    (key: 'maintenance', label: 'Maintenance'),
    (key: _news, label: 'News'),
  ];

  String _filter = 'all';

  Future<void> _guard(Future<void> Function() action) async {
    try {
      await action();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    }
  }

  void _view(AppNotification n) {
    final app = AppStateScope.of(context);
    _guard(() => app.setNotificationRead(n, true));
    if (n.category == 'announcement') {
      setState(() => _filter = _news);
    } else {
      widget.onOpen(n);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final unread = app.unreadNotificationCount;
    final items = [
      for (final n in app.notifications)
        if (_filter == 'all' || n.category == _filter) n,
    ];

    return RefreshIndicator(
      onRefresh: app.refreshNotifications,
      color: OasisColors.green,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.only(top: 16, bottom: 24),
        children: [
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            // Both halves shrink to fit, so nothing overflows on a narrow
            // phone or with a large system font.
            child: Row(
              children: [
                Expanded(
                  flex: 3,
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.centerLeft,
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Text(
                          'Notifications',
                          style: TextStyle(
                            fontSize: 22,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        const SizedBox(width: 8),
                        AnimatedSwitcher(
                          duration: const Duration(milliseconds: 220),
                          transitionBuilder: (child, animation) =>
                              ScaleTransition(scale: animation, child: child),
                          child: unread == 0
                              ? const SizedBox.shrink()
                              : Container(
                                  key: ValueKey(unread),
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 9,
                                    vertical: 3,
                                  ),
                                  decoration: BoxDecoration(
                                    color: OasisColors.gold,
                                    borderRadius: BorderRadius.circular(20),
                                  ),
                                  child: Text(
                                    '$unread new',
                                    style: const TextStyle(
                                      fontSize: 12,
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                ),
                        ),
                      ],
                    ),
                  ),
                ),
                if (unread > 0)
                  Flexible(
                    flex: 2,
                    child: FittedBox(
                      fit: BoxFit.scaleDown,
                      alignment: Alignment.centerRight,
                      child: TextButton.icon(
                        onPressed: () => _guard(app.markAllNotificationsRead),
                        icon: const Icon(Icons.done_all, size: 18),
                        label: const Text('Mark all read'),
                        style: TextButton.styleFrom(
                          foregroundColor: OasisColors.green,
                          visualDensity: VisualDensity.compact,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              children: [
                for (final (i, f) in _filters.indexed) ...[
                  if (i > 0) const SizedBox(width: 8),
                  _FilterPill(
                    label: f.label,
                    selected: _filter == f.key,
                    onTap: () => setState(() => _filter = f.key),
                  ),
                ],
              ],
            ),
          ),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 220),
              child: Column(
                // A new key per filter, so switching filters cross-fades and
                // the rows play their entrance again.
                key: ValueKey(_filter),
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: _filter == _news
                    ? _announcementRows(app.announcements)
                    : _notificationRows(app, items),
              ),
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _notificationRows(AppState app, List<AppNotification> items) {
    if (items.isEmpty) {
      final label = _filters.firstWhere((f) => f.key == _filter).label;
      return [
        _EmptyState(
          icon: Icons.notifications_none,
          message: _filter == 'all'
              ? 'No notifications yet.'
              : 'No ${label.toLowerCase()} notifications yet.',
        ),
      ];
    }
    return [
      for (final (i, n) in items.indexed)
        Padding(
          key: ValueKey(n.id),
          padding: const EdgeInsets.only(bottom: 12),
          child: ListEntrance(
            index: i,
            child: NotificationCard(
              notification: n,
              onView: () => _view(n),
              onToggleRead: () =>
                  _guard(() => app.setNotificationRead(n, !n.isRead)),
            ),
          ),
        ),
    ];
  }

  List<Widget> _announcementRows(List<Announcement> announcements) {
    if (announcements.isEmpty) {
      return const [
        _EmptyState(
          icon: Icons.campaign_outlined,
          message: 'No announcements yet.',
        ),
      ];
    }
    return [
      for (final (i, a) in announcements.indexed)
        Padding(
          key: ValueKey(a.id),
          padding: const EdgeInsets.only(bottom: 12),
          child: ListEntrance(
            index: i,
            child: _AnnouncementCard(announcement: a),
          ),
        ),
    ];
  }
}

class _AnnouncementCard extends StatelessWidget {
  final Announcement announcement;
  const _AnnouncementCard({required this.announcement});

  @override
  Widget build(BuildContext context) {
    final a = announcement;
    final meta = [
      if (a.postedAt != null) formatShortDate(a.postedAt!),
      a.audience,
    ].join(' · ');

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: OasisColors.hairline),
        borderRadius: BorderRadius.circular(14),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: const BoxDecoration(
              color: OasisColors.sand,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.campaign_outlined,
              size: 20,
              color: OasisColors.green,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  a.title,
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  meta,
                  style: const TextStyle(
                    color: OasisColors.muted,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 8),
                Text(a.body, style: const TextStyle(height: 1.4)),
                if (a.author != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    '— ${a.author}',
                    style: const TextStyle(
                      color: OasisColors.muted,
                      fontSize: 12,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _FilterPill extends StatelessWidget {
  final String label;
  final bool selected;
  final VoidCallback onTap;
  const _FilterPill({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? OasisColors.green : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: selected ? OasisColors.green : OasisColors.placeholderGrey,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: selected ? Colors.white : OasisColors.ink,
          ),
        ),
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  final IconData icon;
  final String message;
  const _EmptyState({required this.icon, required this.message});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 48),
      child: Column(
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: const BoxDecoration(
              color: OasisColors.sand,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 30, color: OasisColors.muted),
          ),
          const SizedBox(height: 12),
          Text(message, style: const TextStyle(color: OasisColors.muted)),
        ],
      ),
    );
  }
}
