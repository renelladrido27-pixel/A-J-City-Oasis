import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../models/app_notification.dart';
import '../services/app_updater.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/notification_card.dart';
import '../widgets/oasis_bottom_nav.dart';
import 'auth_screen.dart';
import 'landing_screen.dart';
import 'my_payments_screen.dart';
import 'notifications_screen.dart';
import 'profile_screen.dart';
import 'rental_hub_screen.dart';
import 'verify_email_screen.dart';

/// Owns the persistent bottom nav (Home/Rental/Pay/Alerts/Profile).
///
/// Per the wireframes the bar is on every screen except Log in / Sign up (C2):
/// room details (C3) and booking payment (C4) open *inside* the Home tab with
/// the bar still showing. Each tab has its own page stack, kept alive, so
/// switching tabs is instant and comes back to where you were; Android Back
/// steps back within the tab, then to Home, then leaves the app.
class RootShell extends StatefulWidget {
  const RootShell({super.key});

  /// Switch tabs from a screen inside the shell (e.g. after paying for a
  /// booking, go to Rental).
  static void showTab(
    BuildContext context,
    OasisTab tab, {
    RentalSection? section,
  }) {
    context.findAncestorStateOfType<_RootShellState>()?._goTo(
      tab,
      section: section,
    );
  }

  @override
  State<RootShell> createState() => _RootShellState();
}

class _RootShellState extends State<RootShell> with WidgetsBindingObserver {
  /// How often the app asks the server for new notifications while open.
  static const _pollEvery = Duration(seconds: 30);

  OasisTab _tab = OasisTab.home;
  RentalSection _rentalSection = RentalSection.myRental;

  /// Bumped when something asks the Rental tab for a particular section
  /// (a notification's View, say) so the hub starts fresh on it.
  int _rentalRequest = 0;

  /// One page stack per tab. Tabs are only built once first opened.
  late Map<OasisTab, GlobalKey<NavigatorState>> _navigators =
      _freshNavigators();
  final Set<OasisTab> _opened = {OasisTab.home};
  bool? _wasLoggedIn;

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
            onPressed: () => _goTo(OasisTab.alerts),
          ),
        ),
      );
  }

  static Map<OasisTab, GlobalKey<NavigatorState>> _freshNavigators() => {
    for (final tab in OasisTab.values) tab: GlobalKey<NavigatorState>(),
  };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // Logged out: throw away the tenant tabs' page stacks and go Home.
    final loggedIn = AppStateScope.of(context).isLoggedIn;
    if (_wasLoggedIn == true && !loggedIn) {
      _navigators = _freshNavigators();
      _opened
        ..clear()
        ..add(OasisTab.home);
      _tab = OasisTab.home;
      _rentalSection = RentalSection.myRental;
    }
    _wasLoggedIn = loggedIn;
  }

  /// "View" on a notification: go to the part of the app it is about — the
  /// same destinations the website sends a tenant to.
  void _openNotification(AppNotification n) {
    switch (n.category) {
      case 'payment':
        _goTo(OasisTab.pay);
      case 'maintenance':
        _goTo(OasisTab.rental, section: RentalSection.maintenance);
      case 'lease' || 'booking':
        _goTo(
          OasisTab.rental,
          section: n.type == 'transfer'
              ? RentalSection.transfer
              : RentalSection.myRental,
        );
    }
  }

  void _goTo(OasisTab tab, {RentalSection? section}) {
    if (!mounted) return;
    setState(() {
      _tab = tab;
      _opened.add(tab);
      if (section != null) {
        _rentalSection = section;
        _rentalRequest++;
        _navigators[OasisTab.rental]?.currentState?.popUntil((r) => r.isFirst);
      }
    });
    _refresh(tab);
  }

  /// Coming back to a tab shows what's on screen at once, and quietly picks
  /// up anything new from the server in the background.
  void _refresh(OasisTab tab) {
    final app = AppStateScope.of(context);
    if (!app.isLoggedIn) return;
    final Future<void> Function()? load = switch (tab) {
      OasisTab.rental => () => Future.wait([
        app.refreshLease(),
        app.refreshMaintenanceRequests(),
        app.refreshTransfers(),
      ]),
      OasisTab.pay => app.refreshPayments,
      OasisTab.alerts => app.refreshNotifications,
      OasisTab.profile => app.refreshProfile,
      OasisTab.home => null,
    };
    load?.call().catchError((_) {});
  }

  Future<void> _selectTab(OasisTab tab) async {
    final app = AppStateScope.of(context);
    if (tab != OasisTab.home && !app.isLoggedIn) {
      Navigator.of(
        context,
        rootNavigator: true,
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
    // Tapping the tab you're on goes back to its first screen.
    if (tab == _tab) {
      _navigators[tab]?.currentState?.popUntil((r) => r.isFirst);
      return;
    }
    _goTo(tab);
  }

  /// Android Back: step back inside the tab, then go Home, then leave.
  void _onBack() {
    final stack = _navigators[_tab]?.currentState;
    if (stack != null && stack.canPop()) {
      stack.pop();
    } else if (_tab != OasisTab.home) {
      _goTo(OasisTab.home);
    } else {
      SystemNavigator.pop();
    }
  }

  Widget _firstScreen(OasisTab tab) => switch (tab) {
    OasisTab.home => const LandingScreen(),
    OasisTab.rental => RentalHubScreen(
      key: ValueKey(_rentalRequest),
      initialSection: _rentalSection,
      onPayNow: () => _goTo(OasisTab.pay),
    ),
    OasisTab.pay => const MyPaymentsScreen(),
    OasisTab.alerts => NotificationsScreen(onOpen: _openNotification),
    OasisTab.profile => const ProfileScreen(),
  };

  Widget _tabStack(OasisTab tab) {
    if (!_opened.contains(tab)) return const SizedBox.shrink();
    return TickerMode(
      // Only the tab on screen runs animations.
      enabled: tab == _tab,
      child: Navigator(
        key: _navigators[tab],
        pages: [
          MaterialPage(
            key: ValueKey(tab),
            child: SafeArea(bottom: false, child: _firstScreen(tab)),
          ),
        ],
        onDidRemovePage: (_) {},
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    // Safety net: if a logout happens while on a protected tab, fall back to Home.
    final current = (!app.isLoggedIn && _tab != OasisTab.home)
        ? OasisTab.home
        : _tab;

    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) _onBack();
      },
      child: Scaffold(
        // Switching tabs is instant: every tab stays built with its own
        // stack, like the original app.
        body: IndexedStack(
          index: current.index,
          children: [for (final tab in OasisTab.values) _tabStack(tab)],
        ),
        bottomNavigationBar: OasisBottomNav(
          current: current,
          onSelect: _selectTab,
          alertsBadge: app.unreadNotificationCount,
        ),
      ),
    );
  }
}
