import 'package:flutter/material.dart';

import '../services/app_updater.dart';
import '../state/app_state.dart';
import '../widgets/oasis_bottom_nav.dart';
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

class _RootShellState extends State<RootShell> {
  OasisTab _tab = OasisTab.home;

  @override
  void initState() {
    super.initState();
    // Once the first screen is up, see if the server has a newer build.
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted) AppUpdater.checkAndPrompt(context);
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
    if (mounted) setState(() => _tab = tab);
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
      OasisTab.rental => const RentalHubScreen(),
      OasisTab.pay => const MyPaymentsScreen(),
      OasisTab.alerts => const NotificationsScreen(),
      OasisTab.profile => const ProfileScreen(),
    };

    return Scaffold(
      body: SafeArea(bottom: false, child: body),
      bottomNavigationBar: OasisBottomNav(
        current: effectiveTab,
        onSelect: _selectTab,
      ),
    );
  }
}
