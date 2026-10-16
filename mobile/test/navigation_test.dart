import 'package:ajoasis_mobile/models/app_notification.dart';
import 'package:ajoasis_mobile/models/room.dart';
import 'package:ajoasis_mobile/screens/auth_screen.dart';
import 'package:ajoasis_mobile/screens/room_booking_screen.dart';
import 'package:ajoasis_mobile/screens/root_shell.dart';
import 'package:ajoasis_mobile/state/app_state.dart';
import 'package:ajoasis_mobile/theme.dart';
import 'package:ajoasis_mobile/widgets/oasis_bottom_nav.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

Room _room(String number) => Room(
  id: number,
  number: number,
  property: 'A&J CITY OASIS - Main Building',
  building: 'Main Building',
  type: 'Studio Loft Type',
  monthlyRent: 3500,
  upfrontTotal: 10500,
  floorLabel: 'First Floor',
  sizeSqm: 18,
  amenities: const [],
  statusTag: 'Available',
);

/// The whole shell without a server: rooms from memory, refreshes do nothing.
class _ShellApp extends AppState {
  _ShellApp({bool loggedIn = true}) {
    isLoggedIn = loggedIn;
  }

  final refreshed = <String>[];

  @override
  Future<void> loadRooms() async {
    await null;
    availableRooms = [_room('101'), _room('102')];
    notifyListeners();
  }

  @override
  Future<List<AppNotification>> pollNotifications() async => const [];

  @override
  Future<void> refreshLease() async => refreshed.add('lease');
  @override
  Future<void> refreshPayments() async => refreshed.add('payments');
  @override
  Future<void> refreshMaintenanceRequests() async =>
      refreshed.add('maintenance');
  @override
  Future<void> refreshTransfers() async => refreshed.add('transfers');
  @override
  Future<void> refreshNotifications() async => refreshed.add('notifications');
  @override
  Future<void> refreshProfile() async => refreshed.add('profile');
}

Future<void> _pumpShell(WidgetTester tester, AppState app) async {
  tester.view.physicalSize = const Size(360, 780);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    AppStateScope(
      notifier: app,
      child: MaterialApp(theme: buildOasisTheme(), home: const RootShell()),
    ),
  );
  await tester.pumpAndSettle();
}

/// The shell polls every 30 s; unmount it so no timer is left running.
Future<void> _unmount(WidgetTester tester) =>
    tester.pumpWidget(const SizedBox());

Future<void> _tapTab(WidgetTester tester, String label) async {
  await tester.tap(
    find.descendant(
      of: find.byType(OasisBottomNav),
      matching: find.text(label),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  testWidgets('room details open inside Home with the bottom bar still on', (
    tester,
  ) async {
    await _pumpShell(tester, _ShellApp());

    await tester.tap(find.text('Room 101'));
    await tester.pumpAndSettle();

    // Wireframe C3: the room page keeps the bottom bar, Home still selected.
    expect(find.byType(RoomBookingScreen), findsOneWidget);
    expect(find.byType(OasisBottomNav), findsOneWidget);
    expect(
      tester.widget<OasisBottomNav>(find.byType(OasisBottomNav)).current,
      OasisTab.home,
    );

    // Android Back goes back to the room list, not out of the app.
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.byType(RoomBookingScreen), findsNothing);
    expect(find.text('Room 101'), findsOneWidget);
    await _unmount(tester);
  });

  testWidgets('switching tabs is instant and each tab keeps its place', (
    tester,
  ) async {
    final app = _ShellApp();
    await _pumpShell(tester, app);
    await tester.tap(find.text('Room 102'));
    await tester.pumpAndSettle();

    await _tapTab(tester, 'Pay');
    expect(find.byType(RoomBookingScreen), findsNothing);
    // Pay picks up anything new in the background.
    expect(app.refreshed, contains('payments'));

    // Back on Home: still on Room 102's page, exactly where it was left.
    await tester.tap(
      find.descendant(
        of: find.byType(OasisBottomNav),
        matching: find.text('Home'),
      ),
    );
    await tester.pump();
    expect(find.byType(RoomBookingScreen), findsOneWidget);
    expect(find.textContaining('Rm 102'), findsOneWidget);

    // Tapping Home again goes back to the start of the tab.
    await _tapTab(tester, 'Home');
    expect(find.byType(RoomBookingScreen), findsNothing);
    expect(find.text('Room 101'), findsOneWidget);
    await _unmount(tester);
  });

  testWidgets('Back on another tab goes Home first', (tester) async {
    await _pumpShell(tester, _ShellApp());
    await _tapTab(tester, 'Profile');
    expect(
      tester.widget<OasisBottomNav>(find.byType(OasisBottomNav)).current,
      OasisTab.profile,
    );

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(
      tester.widget<OasisBottomNav>(find.byType(OasisBottomNav)).current,
      OasisTab.home,
    );
    expect(tester.takeException(), isNull);
    await _unmount(tester);
  });

  testWidgets('Log in / Sign up opens full screen, without the bottom bar', (
    tester,
  ) async {
    await _pumpShell(tester, _ShellApp(loggedIn: false));

    await tester.tap(find.text('Log in'));
    await tester.pumpAndSettle();

    // Wireframe C2 has no bottom bar.
    expect(find.byType(AuthScreen), findsOneWidget);
    expect(find.byType(OasisBottomNav), findsNothing);
    await _unmount(tester);
  });
}
