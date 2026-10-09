import 'dart:io';

import 'package:ajoasis_mobile/models/lease.dart';
import 'package:ajoasis_mobile/models/maintenance_request.dart';
import 'package:ajoasis_mobile/models/payment_record.dart';
import 'package:ajoasis_mobile/models/room.dart';
import 'package:ajoasis_mobile/models/tenant_profile.dart';
import 'package:ajoasis_mobile/models/transfer_request.dart';
import 'package:ajoasis_mobile/screens/landing_screen.dart';
import 'package:ajoasis_mobile/screens/my_payments_screen.dart';
import 'package:ajoasis_mobile/screens/profile_screen.dart';
import 'package:ajoasis_mobile/screens/rental_hub_screen.dart';
import 'package:ajoasis_mobile/state/app_state.dart';
import 'package:ajoasis_mobile/theme.dart';
import 'package:ajoasis_mobile/widgets/oasis_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

Room _room(String number, {String floor = 'First Floor'}) => Room(
  id: number,
  number: number,
  property: 'A&J CITY OASIS Main',
  monthlyRent: 3500,
  upfrontTotal: 10500,
  floorLabel: floor,
  sizeSqm: 18,
  amenities: const [],
  statusTag: 'Available',
);

/// App state whose room list comes from memory instead of the server.
class _FakeApp extends AppState {
  _FakeApp({this.rooms = const []});
  final List<Room> rooms;

  @override
  Future<void> loadRooms() async {
    // Like the real call, answer after the current frame.
    await null;
    availableRooms = rooms;
    notifyListeners();
  }
}

/// Set SHOTS=1 to also write screenshots to test/zz_shots/ (needs the
/// Flutter SDK's bundled Roboto; only for eyeballing the layout locally).
final _shots = Platform.environment['SHOTS'] == '1';

Future<void> _loadRealFonts() async {
  // Walk up from the test runner to the SDK cache's bundled fonts.
  var at = File(Platform.resolvedExecutable).parent;
  while (!Directory('${at.path}/material_fonts').existsSync()) {
    at = at.parent;
  }
  final dir = '${at.path}/material_fonts';
  ByteData bytes(String f) =>
      ByteData.view(File('$dir/$f').readAsBytesSync().buffer);
  final roboto = FontLoader('Roboto');
  for (final f in ['regular', 'medium', 'bold', 'black']) {
    roboto.addFont(Future.value(bytes('roboto-$f.ttf')));
  }
  await roboto.load();
  await (FontLoader(
    'MaterialIcons',
  )..addFont(Future.value(bytes('materialicons-regular.otf')))).load();
}

Future<void> _pump(
  WidgetTester tester,
  AppState app,
  Widget child, {
  double width = 360,
  String? shot,
}) async {
  if (_shots) await _loadRealFonts();
  tester.view.physicalSize = Size(width, 780);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
  final theme = buildOasisTheme();
  await tester.pumpWidget(
    AppStateScope(
      notifier: app,
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        theme: _shots
            ? theme.copyWith(
                textTheme: theme.textTheme.apply(fontFamily: 'Roboto'),
              )
            : theme,
        home: Scaffold(body: SafeArea(child: child)),
      ),
    ),
  );
  await tester.pumpAndSettle();
  if (_shots && shot != null) {
    await expectLater(
      find.byType(MaterialApp),
      matchesGoldenFile('zz_shots/$shot.png'),
    );
  }
}

AppState _tenant({double due = 3500}) => AppState()
  ..isLoggedIn = true
  ..lease = Lease(
    roomLabel: 'Room 101',
    property: 'A&J CITY OASIS Main',
    monthlyRent: 3500,
    moveInDate: DateTime(2026, 10, 1),
    leaseStatus: 'Active',
  )
  ..outstandingBalance = due
  ..nextDueDate = due > 0 ? DateTime(2026, 11, 1) : null
  ..nextDuePaymentId = due > 0 ? 4 : null;

PaymentRecord _bill(
  int id,
  String type,
  double amount, {
  String status = 'Pending',
  required DateTime due,
}) => PaymentRecord(
  id: id,
  type: type,
  monthLabel: const [
    '',
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ][due.month],
  amount: amount,
  status: status,
  dueDate: due,
  paidOn: due,
);

void main() {
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  group('Home', () {
    testWidgets('lists the open rooms as cards', (tester) async {
      final app = _FakeApp(
        rooms: [
          _room('101'),
          _room('102'),
          _room('201', floor: 'Second Floor'),
        ],
      );
      await _pump(tester, app, const LandingScreen(), shot: 'home');

      expect(find.text('3 open'), findsOneWidget);
      expect(find.text('Room 101'), findsOneWidget);
      expect(find.text('Second Floor'), findsOneWidget);
      expect(find.textContaining('₱3,500'), findsNWidgets(3));
      expect(
        find.text('3 rooms available now · pay securely online'),
        findsOne,
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('says so when every room is taken', (tester) async {
      await _pump(tester, _FakeApp(), const LandingScreen());

      expect(find.text('All rooms are taken right now.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('fits a small phone with a large system font', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = 1.5;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      final app = _FakeApp(rooms: [_room('101'), _room('102')]);
      await _pump(tester, app, const LandingScreen(), width: 320);

      expect(tester.takeException(), isNull);
    });
  });

  group('Rental tab', () {
    testWidgets('shows the next due as a card with the amount and Pay now', (
      tester,
    ) async {
      var paid = 0;
      await _pump(
        tester,
        _tenant(),
        RentalHubScreen(onPayNow: () => paid++),
        shot: 'rental-due',
      );

      expect(find.text('Room 101'), findsOneWidget);
      expect(find.text('Next due'), findsOneWidget);
      expect(find.text('Nov 1, 2026'), findsOneWidget);
      expect(find.text('Payment due'), findsOneWidget);
      expect(find.text('Active'), findsOneWidget);
      expect(find.byType(StatusChip), findsNWidgets(2));

      await tester.ensureVisible(find.text('Pay now'));
      await tester.tap(find.text('Pay now'));
      expect(paid, 1);
      expect(tester.takeException(), isNull);
    });

    testWidgets('says nothing is due once everything is paid', (tester) async {
      await _pump(
        tester,
        _tenant(due: 0),
        const RentalHubScreen(),
        shot: 'rental-clear',
      );

      expect(find.text('Nothing due right now'), findsOneWidget);
      expect(find.text('Up to date'), findsOneWidget);
      expect(find.text('Pay now'), findsNothing);
    });

    testWidgets('transfer and maintenance requests are cards with a status', (
      tester,
    ) async {
      final app = _tenant()
        ..transferableRooms = [_room('102')]
        ..transferRequests = [
          TransferRequest(
            id: 1,
            fromRoomLabel: 'Rm 101',
            toRoomLabel: 'Rm 205',
            status: 'Pending',
            requestedAt: DateTime(2026, 10, 6),
          ),
        ]
        ..maintenanceRequests = [
          MaintenanceRequest(
            id: 1,
            issueType: 'Plumbing',
            description: 'The kitchen sink is leaking.',
            status: 'In progress',
            submittedAt: DateTime(2026, 10, 5),
            assignedName: 'Juan Dela Cruz',
            scheduledDate: DateTime(2026, 10, 10),
          ),
        ];
      await _pump(tester, app, const RentalHubScreen());

      await tester.tap(find.text('Transfer'));
      await tester.pumpAndSettle();
      expect(find.text('Rm 101'), findsOneWidget);
      expect(find.text('Rm 205'), findsOneWidget);
      expect(find.text('Pending'), findsOneWidget);
      expect(find.text('Room 102'), findsOneWidget);
      if (_shots) {
        await expectLater(
          find.byType(MaterialApp),
          matchesGoldenFile('zz_shots/transfer.png'),
        );
      }

      await tester.ensureVisible(find.text('Maintenance'));
      await tester.tap(find.text('Maintenance'));
      await tester.pumpAndSettle();
      await tester.scrollUntilVisible(
        find.text('In progress'),
        200,
        scrollable: find.byType(Scrollable).last,
      );
      expect(find.text('The kitchen sink is leaking.'), findsOneWidget);
      expect(
        find.text('Assigned to Juan Dela Cruz · Scheduled Oct 10'),
        findsOneWidget,
      );
      // In progress: the tenant confirms it's fixed; too late to cancel.
      expect(find.text('Mark as resolved'), findsOneWidget);
      expect(find.text('Cancel'), findsNothing);
      if (_shots) {
        await expectLater(
          find.byType(MaterialApp),
          matchesGoldenFile('zz_shots/maintenance.png'),
        );
      }
      expect(tester.takeException(), isNull);
    });

    testWidgets('the tenant closes a request, after being asked to confirm', (
      tester,
    ) async {
      MaintenanceRequest request(int id, String status) => MaintenanceRequest(
        id: id,
        issueType: 'Plumbing',
        description: 'Request $id',
        status: status,
        submittedAt: DateTime(2026, 10, 5),
      );
      final app = _tenant()
        ..maintenanceRequests = [
          request(1, 'Pending'),
          request(2, 'Resolved'),
          request(3, 'Cancelled'),
        ];
      await _pump(
        tester,
        app,
        const RentalHubScreen(initialSection: RentalSection.maintenance),
      );
      final list = find.byType(Scrollable).last;

      // Only the open request has buttons; a pending one can also be cancelled.
      await tester.scrollUntilVisible(
        find.text('Request 3'),
        200,
        scrollable: list,
      );
      expect(find.text('Mark as resolved'), findsOneWidget);
      expect(find.text('Cancel'), findsOneWidget);

      await tester.ensureVisible(find.text('Mark as resolved'));
      await tester.tap(find.text('Mark as resolved'));
      await tester.pumpAndSettle();
      expect(find.text('Is it fixed?'), findsOneWidget);

      // "Not yet" leaves it open.
      await tester.tap(find.text('Not yet'));
      await tester.pumpAndSettle();
      expect(find.text('Is it fixed?'), findsNothing);
      expect(app.maintenanceRequests.first.status, 'Pending');
      expect(tester.takeException(), isNull);
    });

    testWidgets('fits a small phone with a large system font', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = 1.5;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      await _pump(tester, _tenant(), const RentalHubScreen(), width: 320);

      expect(tester.takeException(), isNull);
    });
  });

  group('Pay tab', () {
    testWidgets('lists every bill still owed, each with its own Pay button', (
      tester,
    ) async {
      final app = _tenant(due: 4320)
        ..unpaidPayments = [
          _bill(4, 'rent', 3500, due: DateTime(2026, 11, 1)),
          _bill(5, 'utility', 820, due: DateTime(2026, 11, 5)),
        ]
        ..paymentHistory = [
          _bill(2, 'rent', 3500, status: 'Paid', due: DateTime(2026, 10, 1)),
        ];
      await _pump(tester, app, const MyPaymentsScreen(), shot: 'pay');

      expect(find.text('Outstanding balance'), findsOneWidget);
      expect(find.text('₱4,320'), findsOneWidget);
      expect(find.text('Next due Nov 1, 2026'), findsOneWidget);
      expect(find.text('BILLS TO PAY'), findsOneWidget);
      expect(find.text('Rent · Nov'), findsOneWidget);
      expect(find.text('Utility · Nov'), findsOneWidget);
      expect(find.text('Due Nov 5, 2026'), findsOneWidget);
      expect(find.text('Pay now'), findsNWidgets(2));
      expect(find.text('Rent · Oct'), findsOneWidget);
      expect(find.text('Paid Oct 1, 2026'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('says so when nothing is owed', (tester) async {
      await _pump(tester, _tenant(due: 0), const MyPaymentsScreen());

      expect(find.text('You are all paid up.'), findsOneWidget);
      expect(find.text('BILLS TO PAY'), findsNothing);
      expect(find.text('Pay now'), findsNothing);
      expect(find.text('No payments yet.'), findsOneWidget);
    });

    testWidgets('fits a small phone with a large system font', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = 1.5;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      final app = _tenant()
        ..unpaidPayments = [
          _bill(
            4,
            'transfer_adjustment',
            12500,
            status: 'Overdue',
            due: DateTime(2026, 9, 1),
          ),
        ];
      await _pump(tester, app, const MyPaymentsScreen(), width: 320);

      expect(find.text('Overdue'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });

  group('Profile tab', () {
    AppState signedIn({bool verified = true}) =>
        _tenant()
          ..profile = TenantProfile(
            fullName: 'Maria Santos Reyes',
            firstName: 'Maria',
            middleName: 'Santos',
            lastName: 'Reyes',
            email: 'maria@example.com',
            phone: '09171234567',
            emailVerified: verified,
          );

    testWidgets('shows who is signed in and their details in cards', (
      tester,
    ) async {
      await _pump(tester, signedIn(), const ProfileScreen(), shot: 'profile');

      expect(find.text('Maria Santos Reyes'), findsOneWidget);
      expect(find.text('maria@example.com'), findsWidgets);
      expect(find.text('Email verified'), findsOneWidget);
      expect(find.text('PERSONAL DETAILS'), findsOneWidget);
      expect(find.widgetWithText(TextField, 'Maria'), findsOneWidget);
      expect(find.widgetWithText(TextField, '09171234567'), findsOneWidget);
      expect(find.text('Save changes'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('asks before logging out', (tester) async {
      final app = signedIn();
      await _pump(tester, app, const ProfileScreen());

      await tester.scrollUntilVisible(
        find.text('Log out'),
        300,
        scrollable: find.byType(Scrollable).first,
      );
      await tester.tap(find.text('Log out'));
      await tester.pumpAndSettle();

      expect(find.text('Log out?'), findsOneWidget);
      await tester.tap(find.text('Cancel'));
      await tester.pumpAndSettle();
      expect(app.isLoggedIn, isTrue);
    });

    testWidgets('fits a small phone with a large system font', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = 1.5;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      await _pump(
        tester,
        signedIn(verified: false),
        const ProfileScreen(),
        width: 320,
      );

      expect(find.text('Email not verified'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}
