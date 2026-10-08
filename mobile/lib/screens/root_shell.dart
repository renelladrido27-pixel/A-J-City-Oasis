import 'dart:async';

import 'package:flutter/material.dart';

import '../models/app_notification.dart';
import '../services/app_updater.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/notification_card.dart';
import '../widgets/oasis_bottom_nav.dart';
import '../widgets/oasis_ui.dart';
import 'auth_screen.dart';
import 'landing_screen.dart';
import 'my_payments_screen.dart';
import 'notifications_screen.dart';
import 'profile_screen.dart';
import 'rental_hub_screen.dart';
import 'verify_email_screen.dart';

/// Owns the persistent bottom nav (Home/Rental/Pay/Alerts/Profile) shared by
/// C1, C5-C9, C10. Login/Signup (C2) and the booking checkout step (C4) are
/// pushed as full-screen routes without this chrome, matching the wireframes.
class RootShell extends StatefulWidget {
  const RootShell({super.key});

  @override
  State<RootShell> createState() => _RootShellState();
}

class _RootShellState extends State<RootShell> with WidgetsBindingObserver {
  /// How often the app asks the server for new notifications while open.
  static const _pollEvery = Duration(seconds: 30);

  OasisTab _tab = OasisTab.home;
  RentalSection _rentalSection = RentalSection.myRental;
  Timer? _poller;
  bool _polling = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _poller = Timer.periodic(_pollEvery, (_) => _checkForNewNotifications());
    // Once the first screen is up, see if the server has a newer build.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) AppUpdater.checkAndPrompt(context);
    });
  }

  @override
  void dispose() {
    _poller?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  /// Coming back to the app (e.g. from the payment page) — catch up at once
  /// instead of waiting for the next tick.
  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) _checkForNewNotifications();
  }

  /// The website shows a pop-up the moment a notification arrives; the app
  /// does the same within [_pollEvery], with a banner that opens it.
  Future<void> _checkForNewNotifications() async {
    if (_polling || !mounted) return;
    _polling = true;
    try {
      final fresh = await AppStateScope.of(context).pollNotifications();
      if (!mounted || fresh.isEmpty) return;
      _announce(fresh.first, more: fresh.length - 1);
    } finally {
      _polling = false;
    }
  }

  void _announce(AppNotification n, {required int more}) {
    final meta = notificationCategoryMeta(n.category);
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          behavior: SnackBarBehavior.floating,
          backgroundColor: OasisColors.green,
          duration: const Duration(seconds: 6),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          content: Row(
            children: [
              Icon(meta.icon, color: OasisColors.gold, size: 22),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      more > 0 ? '${n.title}  (+$more more)' : n.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    if (n.message.isNotEmpty)
                      Text(
                        n.message,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: Colors.white70,
                          fontSize: 12.5,
                        ),
                      ),
                  ],
                ),
              ),
            ],
          ),
          action: SnackBarAction(
            label: 'View',
            textColor: OasisColors.gold,
            onPressed: () => setState(() => _tab = OasisTab.alerts),
          ),
        ),
      );
  }

  /// "View" on a notification: go to the part of the app it is about — the
  /// same destinations the website sends a tenant to.
  void _openNotification(AppNotification n) {
    setState(() {
      switch (n.category) {
        case 'payment':
          _tab = OasisTab.pay;
        case 'maintenance':
          _rentalSection = RentalSection.maintenance;
          _tab = OasisTab.rental;
        case 'lease' || 'booking':
          _rentalSection = n.type == 'transfer'
              ? RentalSection.transfer
              : RentalSection.myRental;
          _tab = OasisTab.rental;
      }
    });
  }

  Future<void> _selectTab(OasisTab tab) async {
    final app = AppStateScope.of(context);
    if (tab != OasisTab.home && !app.isLoggedIn) {
      Navigator.of(
        context,
      ).push(MaterialPageRoute(builder: (_) => const AuthScreen()));
      return;
    }
    // The tenant tabs' data is only available to verified accounts.
    if (tab != OasisTab.home &&
        tab != OasisTab.profile &&
        !await VerifyEmailScreen.ensureVerified(context)) {
      return;
    }
    if (!mounted) return;
    setState(() {
      _tab = tab;
      _rentalSection = RentalSection.myRental;
    });
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    // Safety net: if a logout happens while on a protected tab, fall back to Home.
    final effectiveTab = (!app.isLoggedIn && _tab != OasisTab.home)
        ? OasisTab.home
        : _tab;

    final body = switch (effectiveTab) {
      OasisTab.home => const LandingScreen(),
      OasisTab.rental => RentalHubScreen(
        initialSection: _rentalSection,
        onPayNow: () => setState(() => _tab = OasisTab.pay),
      ),
      OasisTab.pay => const MyPaymentsScreen(),
      OasisTab.alerts => NotificationsScreen(onOpen: _openNotification),
      OasisTab.profile => const ProfileScreen(),
    };

    return Scaffold(
      body: SafeArea(
        bottom: false,
        // Tabs cross-fade into each other instead of snapping.
        child: FadeThrough(
          child: KeyedSubtree(
            key: ValueKey((effectiveTab, _rentalSection)),
            child: body,
          ),
        ),
      ),
      bottomNavigationBar: OasisBottomNav(
        current: effectiveTab,
        onSelect: _selectTab,
        alertsBadge: app.unreadNotificationCount,
      ),
    );
  }
}
