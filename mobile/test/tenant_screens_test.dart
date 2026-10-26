import 'dart:io';

import 'package:ajoasis_mobile/models/lease.dart';
import 'package:ajoasis_mobile/screens/room_booking_screen.dart';
import 'package:ajoasis_mobile/models/rental_terms.dart';
import 'package:ajoasis_mobile/models/maintenance_request.dart';
import 'package:ajoasis_mobile/models/payment_record.dart';
import 'package:ajoasis_mobile/models/receipt.dart';
import 'package:ajoasis_mobile/models/room.dart';
import 'package:ajoasis_mobile/models/tenant_profile.dart';
import 'package:ajoasis_mobile/models/transfer_request.dart';
import 'package:ajoasis_mobile/screens/landing_screen.dart';
import 'package:ajoasis_mobile/screens/my_payments_screen.dart';
import 'package:ajoasis_mobile/screens/profile_screen.dart';
import 'package:ajoasis_mobile/screens/receipt_screen.dart';
import 'package:ajoasis_mobile/screens/rental_hub_screen.dart';
import 'package:ajoasis_mobile/services/receipt_export.dart';
import 'package:ajoasis_mobile/state/app_state.dart';
import 'package:ajoasis_mobile/widgets/location_map.dart';
import 'package:ajoasis_mobile/theme.dart';
import 'package:ajoasis_mobile/utils/format.dart';
import 'package:ajoasis_mobile/widgets/oasis_ui.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

Room _room(
  String number, {
  String floor = 'First Floor',
  String building = 'Main Building',
}) => Room(
  id: building == 'Main Building' ? number : '$building $number',
  number: number,
  property: 'A&J CITY OASIS - $building',
  building: building,
  type: 'Studio Loft Type',
  address:
      'Maglantay Subd., Bo. 2, Santo Niño, City of Koronadal, South Cotabato',
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

/// Booking a room without the server: canned terms, and a record of what
/// the booking call was sent.
class _BookingApp extends AppState {
  final bookedWithTerms = <bool>[];

  @override
  Future<RentalTerms> fetchRentalTerms(String roomId) async {
    await null;
    return const RentalTerms(
      title: 'Terms and Conditions',
      sections: [
        TermsSection(
          heading: 'Parties',
          body: 'Between A&J CITY OASIS and the Tenant.',
        ),
        TermsSection(
          heading: 'Rent',
          body: 'The monthly rental rate is P3,500.00.',
        ),
      ],
    );
  }

  @override
  Future<void> submitBookingForCurrentUser({
    required DateTime moveInDate,
    required bool agreedToTerms,
  }) async {
    bookedWithTerms.add(agreedToTerms);
  }
}

/// A tenant who can ask to move out without a server.
class _MoveOutApp extends AppState {
  final asked = <(int?, DateTime, String)>[];

  @override
  Future<String> requestMoveOut(
    Lease l, {
    required DateTime date,
    String reason = '',
  }) async {
    asked.add((l.id, date, reason));
    lease = Lease(
      id: l.id,
      roomLabel: l.roomLabel,
      property: l.property,
      monthlyRent: l.monthlyRent,
      moveInDate: l.moveInDate,
      leaseStatus: l.leaseStatus,
      moveOut: MoveOutInfo(
        requestedDate: date,
        status: 'awaiting_inspection',
        refundAmount: 3500,
      ),
    );
    notifyListeners();
    return 'Move-out request submitted. Estimated refund: ₱3,500.00 — final amount after the room inspection.';
  }
}

/// App state that hands back a ready-made receipt instead of asking the server.
class _ReceiptApp extends AppState {
  _ReceiptApp(this.receipt);
  final Receipt receipt;
  final asked = <int>[];

  @override
  Future<Receipt> fetchReceipt(int paymentId) async {
    await null;
    asked.add(paymentId);
    return receipt;
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
    // Bundled photos only decode in real time, so load them before the shot.
    await tester.runAsync(() async {
      for (final element in find.byType(Image).evaluate()) {
        final image = (element.widget as Image).image;
        if (image is AssetImage) await precacheImage(image, element);
      }
    });
    await tester.pumpAndSettle();
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

/// How many "Mark as done" buttons can actually be pressed.
int _enabledMarkAsDone(WidgetTester tester) => tester
    .widgetList<ButtonStyleButton>(
      find.ancestor(
        of: find.text('Mark as done'),
        matching: find.byWidgetPredicate((w) => w is ButtonStyleButton),
      ),
    )
    .where((b) => b.onPressed != null)
    .length;

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
      expect(find.text('Main Building · Second Floor'), findsOneWidget);
      expect(find.textContaining('₱3,500'), findsNWidgets(3));
      expect(
        find.text('3 rooms available now · pay securely online'),
        findsOne,
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('the banner shows the building photo behind the headline', (
      tester,
    ) async {
      await _pump(
        tester,
        _FakeApp(rooms: [_room('101')]),
        const LandingScreen(),
      );

      final photo = tester.widget<Image>(
        find.byWidgetPredicate(
          (w) =>
              w is Image &&
              w.image is AssetImage &&
              (w.image as AssetImage).assetName ==
                  'assets/images/hero-building.jpg',
        ),
      );
      expect(photo.fit, BoxFit.cover);
      // The file is really in the app bundle, not just named in the code.
      final bytes = await rootBundle.load('assets/images/hero-building.jpg');
      expect(bytes.lengthInBytes, greaterThan(10000));
      expect(find.text('Find your next room.\nBook it in minutes.'), findsOne);
      expect(tester.takeException(), isNull);
    });

    testWidgets('Log in and Sign up stay on screen while scrolling', (
      tester,
    ) async {
      final app = _FakeApp(rooms: [for (var i = 1; i <= 8; i++) _room('10$i')]);
      await _pump(tester, app, const LandingScreen());

      final before = tester.getTopLeft(find.text('Log in'));
      await tester.drag(
        find.byType(SingleChildScrollView),
        const Offset(0, -1500),
      );
      await tester.pumpAndSettle();
      expect(tester.getTopLeft(find.text('Log in')), before);
      expect(find.text('Sign up'), findsOneWidget);
      // The page itself did scroll: the banner is gone, the contact card is in view.
      expect(find.text('Find us in Koronadal City'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('the contact card has the same map as the website', (
      tester,
    ) async {
      await _pump(
        tester,
        _FakeApp(rooms: [_room('101')]),
        const LandingScreen(),
      );
      await tester.scrollUntilVisible(
        find.text('Open in Google Maps'),
        300,
        scrollable: find.byType(Scrollable).first,
      );

      expect(find.byType(LocationMap), findsOneWidget);
      expect(
        LocationMap.embedUri.toString(),
        'https://www.google.com/maps?q=Koronadal+City%2C+South+Cotabato&output=embed',
      );
      // Google refuses the embed outside an iframe, so the web view loads it
      // in one instead of opening the embed URL directly.
      expect(
        LocationMap.iframeHtml,
        contains('<iframe src="${LocationMap.embedUri}"'),
      );
      expect(find.text('Open in Google Maps'), findsOneWidget);
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

    testWidgets('each rented room shows its own next due', (tester) async {
      Lease lease(int id, String room, String building, DateTime due) => Lease(
        id: id,
        roomLabel: room,
        building: building,
        property: 'A&J CITY OASIS - $building',
        monthlyRent: 3500,
        moveInDate: DateTime(due.year, due.month - 1, due.day),
        leaseStatus: 'Active',
        balance: 3500,
        nextDueDate: due,
        nextDueAmount: 3500,
        nextDuePaymentId: id,
      );
      final main202 = lease(
        6,
        'Room 202',
        'Main Building',
        DateTime(2026, 11, 27),
      );
      final annex102 = lease(
        3,
        'Room 102',
        'Annex Building',
        DateTime(2026, 11, 8),
      );
      // The app-wide next due is Room 102's Nov 8 bill — My rental for Room
      // 202 must still say Nov 27.
      final app = _tenant()
        ..nextDueDate = DateTime(2026, 11, 8)
        ..leases = [annex102, main202]
        ..lease = main202;
      await _pump(tester, app, const RentalHubScreen());

      expect(find.text('Room 202'), findsOneWidget);
      expect(find.text('Nov 27, 2026'), findsOneWidget);
      expect(find.text('Nov 8, 2026'), findsNothing);

      // Switch rooms.
      await tester.tap(find.text('Room 102 · Annex Building'));
      await tester.pumpAndSettle();
      expect(find.text('Room 102'), findsOneWidget);
      expect(find.text('Nov 8, 2026'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('the tenant can ask to move out from My rental', (
      tester,
    ) async {
      final app = _MoveOutApp()
        ..isLoggedIn = true
        ..lease = Lease(
          id: 6,
          roomLabel: 'Room 202',
          building: 'Main Building',
          property: 'A&J CITY OASIS - Main Building',
          monthlyRent: 3500,
          moveInDate: DateTime(2026, 10, 27),
          leaseStatus: 'Active',
        );
      await _pump(tester, app, const RentalHubScreen());

      await tester.scrollUntilVisible(
        find.text('Move out'),
        200,
        scrollable: find.byType(Scrollable).last,
      );
      await tester.tap(find.text('Move out'));
      await tester.pumpAndSettle();

      // The website's form: date, optional reason, the deposit rules.
      expect(
        find.textContaining('Rent already paid is not refunded'),
        findsOneWidget,
      );
      // The form's own list (the text box has a scrollable of its own).
      await tester.dragUntilVisible(
        find.text('Submit request'),
        find.byType(ListView).last,
        const Offset(0, -200),
      );
      await tester.pumpAndSettle();
      expect(find.text('Submit request'), findsOneWidget);
      await tester.tap(find.text('Submit request'));
      await tester.pumpAndSettle();
      expect(find.text('Please choose your move-out date.'), findsOneWidget);

      await tester.tap(find.text('Move-out date'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await tester.enterText(find.byType(TextField), 'Moving back home');
      await tester.ensureVisible(find.text('Submit request'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Submit request'));
      await tester.pumpAndSettle();
      expect(find.text('Request move-out?'), findsOneWidget);
      await tester.tap(find.text('Request'));
      await tester.pumpAndSettle();

      expect(app.asked.single.$1, 6);
      expect(app.asked.single.$3, 'Moving back home');
      // Back on My rental, the row now says where it stands.
      await tester.scrollUntilVisible(
        find.textContaining('Moving out'),
        200,
        scrollable: find.byType(Scrollable).last,
      );
      expect(
        find.textContaining('Waiting for the room inspection'),
        findsOneWidget,
      );
      expect(find.text('Move out'), findsNothing);
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
        ..transferableRooms = [
          _room('102'),
          _room('102', building: 'Annex Building'),
        ]
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
            canMarkDone: true,
          ),
        ];
      await _pump(tester, app, const RentalHubScreen());

      await tester.tap(find.text('Transfer'));
      await tester.pumpAndSettle();
      expect(find.text('Rm 101'), findsOneWidget);
      expect(find.text('Rm 205'), findsOneWidget);
      expect(find.text('Pending'), findsOneWidget);
      // Room 102 exists in both buildings: each card says which, where, and what type.
      expect(find.text('Room 102'), findsNWidgets(2));
      expect(find.text('Main Building'), findsOneWidget);
      expect(find.text('Annex Building'), findsOneWidget);
      expect(
        find.text('Studio Loft Type · First Floor · ₱3,500 / month'),
        findsNWidgets(2),
      );
      expect(
        find.text(
          'Maglantay Subd., Bo. 2, Santo Niño, City of Koronadal, South Cotabato',
        ),
        findsNWidgets(2),
      );
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
      // Assigned and the day has come: the tenant confirms it's done; too late to delete it.
      expect(find.text('Mark as done'), findsOneWidget);
      // Not done yet, so it can still be removed.
      expect(find.text('Remove'), findsOneWidget);
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
        assignedName: status == 'In progress' ? 'Juan Dela Cruz' : null,
        scheduledDate: status == 'In progress' ? DateTime(2026, 10, 6) : null,
        canMarkDone: status == 'In progress',
      );
      final app = _tenant()
        ..maintenanceRequests = [
          request(1, 'In progress'),
          request(2, 'Pending'),
          request(3, 'Resolved'),
          request(4, 'Cancelled'),
        ];
      await _pump(
        tester,
        app,
        const RentalHubScreen(initialSection: RentalSection.maintenance),
      );
      final list = find.byType(Scrollable).last;

      // Only the assigned request whose day has come can be marked done. A
      // pending or cancelled one can be deleted; a resolved one stays on record.
      await tester.scrollUntilVisible(
        find.text('Request 4'),
        200,
        scrollable: list,
      );
      // Mark as done shows on both open requests but only the assigned,
      // due one can be pressed. Everything not done can be removed.
      expect(find.text('Mark as done'), findsNWidgets(2));
      expect(_enabledMarkAsDone(tester), 1);
      expect(find.text('Remove'), findsNWidgets(3));

      // Remove asks first (and says who won't need to come), and "Keep it"
      // leaves the request alone.
      await tester.ensureVisible(find.text('Remove').first);
      await tester.tap(find.text('Remove').first);
      await tester.pumpAndSettle();
      expect(find.text('Remove this request?'), findsOneWidget);
      expect(
        find.textContaining('Juan Dela Cruz will be told not to come.'),
        findsOneWidget,
      );
      await tester.tap(find.text('Keep it'));
      await tester.pumpAndSettle();
      expect(app.maintenanceRequests.length, 4);

      await tester.ensureVisible(find.text('Mark as done').first);
      await tester.tap(find.text('Mark as done').first);
      await tester.pumpAndSettle();
      expect(find.text('Is it fixed?'), findsOneWidget);

      // "Not yet" leaves it open.
      await tester.tap(find.text('Not yet'));
      await tester.pumpAndSettle();
      expect(find.text('Is it fixed?'), findsNothing);
      expect(app.maintenanceRequests.first.status, 'In progress');
      expect(tester.takeException(), isNull);
    });

    testWidgets('Mark as done waits for someone to be assigned', (
      tester,
    ) async {
      final app = _tenant()
        ..maintenanceRequests = [
          MaintenanceRequest(
            id: 1,
            issueType: 'Electrical',
            description: 'Not assigned yet',
            status: 'Pending',
            submittedAt: DateTime(2026, 10, 14),
          ),
          MaintenanceRequest(
            id: 2,
            issueType: 'Plumbing',
            description: 'Assigned for later',
            status: 'In progress',
            submittedAt: DateTime(2026, 10, 14),
            assignedName: 'Juan Dela Cruz',
            scheduledDate: DateTime(2026, 10, 20),
            canMarkDone: true,
          ),
        ];
      await _pump(
        tester,
        app,
        const RentalHubScreen(initialSection: RentalSection.maintenance),
      );
      await tester.scrollUntilVisible(
        find.text('Assigned for later'),
        200,
        scrollable: find.byType(Scrollable).last,
      );

      // Both show the button; only the assigned one can be pressed, date or not.
      expect(find.text('Mark as done'), findsNWidgets(2));
      expect(_enabledMarkAsDone(tester), 1);
      expect(
        find.text('You can mark it as done once someone is assigned.'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('fits a small phone with a large system font', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = 1.5;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      await _pump(tester, _tenant(), const RentalHubScreen(), width: 320);

      expect(tester.takeException(), isNull);
    });
  });

  group('Booking', () {
    testWidgets('the Terms and Conditions are agreed to before paying', (
      tester,
    ) async {
      final app = _BookingApp()
        ..isLoggedIn = true
        ..draft = BookingDraft(_room('104'));
      await _pump(
        tester,
        app,
        const RoomBookingScreen(),
        shot: 'booking-terms',
      );

      // Pick a move-in date (today), then try to continue without agreeing.
      await tester.tap(find.text('Move-in date'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('OK'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.text('Continue'));
      await tester.tap(find.text('Continue'));
      await tester.pumpAndSettle();
      expect(
        find.text(
          'Please read and agree to the Terms and Conditions before paying.',
        ),
        findsOneWidget,
      );
      expect(app.bookedWithTerms, isEmpty);

      // Ticking the box opens the terms; "I agree" ticks it.
      await tester.ensureVisible(find.byType(Checkbox));
      await tester.tap(find.byType(Checkbox));
      await tester.pumpAndSettle();
      expect(find.text('Terms and Conditions'), findsOneWidget);
      expect(find.textContaining('Parties.'), findsOneWidget);
      await tester.tap(find.text('I agree'));
      await tester.pumpAndSettle();
      expect(tester.widget<Checkbox>(find.byType(Checkbox)).value, isTrue);

      await tester.ensureVisible(find.text('Continue'));
      await tester.tap(find.text('Continue'));
      await tester.pump();
      expect(app.bookedWithTerms, [true]);
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

    testWidgets('has a Check for updates button with the version', (
      tester,
    ) async {
      await _pump(tester, signedIn(), const ProfileScreen());
      await tester.scrollUntilVisible(
        find.text('Check for updates'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('Check for updates'), findsOneWidget);
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

  group('Receipt', () {
    Receipt receipt({String? billPhotoUrl, double amount = 3500}) => Receipt(
      number: '000012',
      business: 'A&J CITY OASIS',
      businessAddress: 'Koronadal City, South Cotabato',
      billedToName: 'Maria Santos Reyes',
      billedToEmail: 'maria@example.com',
      roomNumber: '101',
      property: 'A&J CITY OASIS - Main Building',
      description: 'Rent',
      amount: amount,
      paidAt: DateTime(2026, 10, 2, 15, 15),
      reference: 'pay-abc-123',
      billPhotoUrl: billPhotoUrl,
    );

    const upfrontParts = [
      PaymentLine(
        label: '1 month advance',
        note: "Your first month's rent",
        amount: 3500,
      ),
      PaymentLine(
        label: '1 month deposit',
        note: 'Refunded at move-out if all rent is paid',
        amount: 3500,
      ),
      PaymentLine(
        label: 'Security deposit',
        note: 'Refundable at move-out, less any damages',
        amount: 3500,
      ),
    ];

    testWidgets('the upfront payment is in the history, broken down', (
      tester,
    ) async {
      final app = _tenant(due: 0)
        ..paymentHistory = [
          PaymentRecord(
            id: 1,
            type: 'booking_upfront',
            monthLabel: 'Oct',
            amount: 10500,
            status: 'Paid',
            dueDate: DateTime(2026, 10, 1),
            paidOn: DateTime(2026, 10, 1),
            breakdown: upfrontParts,
          ),
          _bill(2, 'rent', 3500, status: 'Paid', due: DateTime(2026, 11, 1)),
        ];
      await _pump(tester, app, const MyPaymentsScreen(), shot: 'pay-upfront');

      expect(find.text('Booking upfront · Oct'), findsOneWidget);
      expect(find.text('₱10,500'), findsOneWidget);
      expect(find.text('1 month advance'), findsOneWidget);
      expect(find.text('1 month deposit'), findsOneWidget);
      expect(find.text('Security deposit'), findsOneWidget);
      // The three parts, plus the ordinary rent bill's own amount.
      expect(find.text('₱3,500'), findsNWidgets(4));
      expect(tester.takeException(), isNull);
    });

    testWidgets('the upfront receipt lists each part and what it is for', (
      tester,
    ) async {
      final r = receipt(amount: 10500);
      final app = _ReceiptApp(
        Receipt(
          number: r.number,
          business: r.business,
          businessAddress: r.businessAddress,
          billedToName: r.billedToName,
          billedToEmail: r.billedToEmail,
          roomNumber: r.roomNumber,
          property: r.property,
          description: 'Booking upfront',
          lines: upfrontParts,
          amount: 10500,
          paidAt: r.paidAt,
          reference: r.reference,
          dueLabel: 'First rent due',
          dueDate: DateTime(2026, 11, 27),
          refundNote:
              'The full upfront payment is refunded if the booking is cancelled before the move-in date.',
        ),
      );
      final payment = _bill(
        1,
        'booking_upfront',
        10500,
        status: 'Paid',
        due: DateTime(2026, 10, 1),
      );
      await _pump(
        tester,
        app,
        ReceiptScreen(payment: payment),
        shot: 'receipt-upfront',
      );

      expect(find.text('1 month advance'), findsOneWidget);
      expect(find.text("Your first month's rent"), findsOneWidget);
      expect(find.text('1 month deposit'), findsOneWidget);
      expect(find.text('Security deposit'), findsOneWidget);
      expect(
        find.text('Refundable at move-out, less any damages'),
        findsOneWidget,
      );
      expect(find.text('₱3,500.00'), findsNWidgets(3));
      expect(find.text('₱10,500.00'), findsOneWidget);
      // When the first monthly rent is due, and that the upfront is refundable.
      await tester.scrollUntilVisible(
        find.text('FIRST RENT DUE'),
        200,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text('FIRST RENT DUE'), findsOneWidget);
      expect(find.text('Nov 27, 2026'), findsOneWidget);
      expect(
        find.textContaining('refunded if the booking is cancelled'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    });

    testWidgets('the receipt can be saved as a PDF or as an image', (
      tester,
    ) async {
      final saved = <({String fileName, String extension, Uint8List bytes})>[];
      final payment = _bill(
        12,
        'rent',
        3500,
        status: 'Paid',
        due: DateTime(2026, 10, 1),
      );
      await _pump(
        tester,
        _ReceiptApp(receipt()),
        ReceiptScreen(
          payment: payment,
          saver:
              ({
                required String fileName,
                required String extension,
                required Uint8List bytes,
              }) async {
                saved.add((
                  fileName: fileName,
                  extension: extension,
                  bytes: bytes,
                ));
                return true;
              },
        ),
      );

      // Taking the picture and writing the PDF are real work, not frames.
      Future<void> press(String label) async {
        await tester.ensureVisible(find.text(label));
        await tester.runAsync(() async {
          await tester.tap(find.text(label));
          for (var i = 0; i < 100 && saved.length < 2; i++) {
            await Future<void>.delayed(const Duration(milliseconds: 50));
            if (label.contains('PDF') && saved.isNotEmpty) break;
          }
        });
        await tester.pump();
      }

      await press('Save as PDF');
      expect(saved, hasLength(1));
      expect(saved[0].fileName, 'AJ-City-Oasis-receipt-000012.pdf');
      expect(saved[0].extension, 'pdf');
      expect(String.fromCharCodes(saved[0].bytes.take(5)), '%PDF-');
      expect(find.text('Receipt saved as a PDF.'), findsOneWidget);

      await press('Save as image');
      expect(saved, hasLength(2));
      expect(saved[1].fileName, 'AJ-City-Oasis-receipt-000012.png');
      // PNG signature.
      expect(saved[1].bytes.take(4), [0x89, 0x50, 0x4E, 0x47]);
      expect(saved[1].bytes.length, greaterThan(5000));
      expect(tester.takeException(), isNull);
    });

    testWidgets('saving hands the file to the system save screen', (
      tester,
    ) async {
      const channel = MethodChannel('ajoasis/save_file');
      final calls = <MethodCall>[];
      var answer = true;
      tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(channel, (
        call,
      ) async {
        calls.add(call);
        return answer;
      });
      addTearDown(
        () => tester.binding.defaultBinaryMessenger.setMockMethodCallHandler(
          channel,
          null,
        ),
      );
      final bytes = Uint8List.fromList([1, 2, 3]);

      expect(
        await ReceiptExport.saveWithSystemDialog(
          fileName: 'receipt.pdf',
          extension: 'pdf',
          bytes: bytes,
        ),
        isTrue,
      );
      expect(calls.single.method, 'saveFile');
      expect(calls.single.arguments['fileName'], 'receipt.pdf');
      expect(calls.single.arguments['mimeType'], 'application/pdf');
      expect(calls.single.arguments['bytes'], bytes);

      // Backing out of the save screen.
      answer = false;
      expect(
        await ReceiptExport.saveWithSystemDialog(
          fileName: 'receipt.png',
          extension: 'png',
          bytes: bytes,
        ),
        isFalse,
      );
      expect(calls.last.arguments['mimeType'], 'image/png');
    });

    testWidgets('backing out of the save screen says nothing', (tester) async {
      final payment = _bill(
        12,
        'rent',
        3500,
        status: 'Paid',
        due: DateTime(2026, 10, 1),
      );
      var asked = 0;
      await _pump(
        tester,
        _ReceiptApp(receipt()),
        ReceiptScreen(
          payment: payment,
          saver:
              ({
                required String fileName,
                required String extension,
                required Uint8List bytes,
              }) async {
                asked++;
                return false;
              },
        ),
      );

      await tester.ensureVisible(find.text('Save as image'));
      await tester.runAsync(() async {
        await tester.tap(find.text('Save as image'));
        for (var i = 0; i < 100 && asked == 0; i++) {
          await Future<void>.delayed(const Duration(milliseconds: 50));
        }
      });
      await tester.pump();

      expect(asked, 1);
      expect(find.byType(SnackBar), findsNothing);
    });

    test('amounts are shown to the centavo, dates with the time', () {
      expect(formatPesoExact(450.75), '₱450.75');
      expect(formatPesoExact(10500), '₱10,500.00');
      expect(formatPesoExact(0.5), '₱0.50');
      expect(
        formatDateTime(DateTime(2026, 10, 2, 15, 15)),
        'Oct 2, 2026 3:15 PM',
      );
      expect(
        formatDateTime(DateTime(2026, 1, 9, 0, 5)),
        'Jan 9, 2026 12:05 AM',
      );
    });

    testWidgets('a paid bill in the Pay tab opens its receipt', (tester) async {
      final app = _ReceiptApp(receipt())
        ..isLoggedIn = true
        ..paymentHistory = [
          _bill(12, 'rent', 3500, status: 'Paid', due: DateTime(2026, 10, 1)),
        ];
      await _pump(tester, app, const MyPaymentsScreen());

      expect(find.text('View receipt'), findsOneWidget);
      await tester.tap(find.text('View receipt'));
      await tester.pumpAndSettle();

      expect(app.asked, [12]);
      expect(find.byType(ReceiptScreen), findsOneWidget);
      expect(find.text('#000012'), findsOneWidget);
      expect(find.text('A&J CITY OASIS'), findsOneWidget);
      expect(find.text('Maria Santos Reyes'), findsOneWidget);
      expect(find.text('maria@example.com'), findsOneWidget);
      expect(find.text('101'), findsOneWidget);
      expect(find.text('A&J CITY OASIS - Main Building'), findsOneWidget);
      expect(find.text('Rent'), findsOneWidget);
      // Once on the line, once as the total.
      expect(find.text('₱3,500.00'), findsNWidgets(2));
      expect(find.text('Oct 2, 2026 3:15 PM'), findsOneWidget);
      expect(find.text('pay-abc-123'), findsOneWidget);
      expect(find.text('Paid in Full'), findsOneWidget);
      // No bill photo for rent.
      expect(find.text('View bill photo'), findsNothing);
      expect(tester.takeException(), isNull);
      if (_shots) {
        await expectLater(
          find.byType(MaterialApp),
          matchesGoldenFile('zz_shots/receipt.png'),
        );
      }
    });

    testWidgets('a utility receipt also offers the photo of the bill', (
      tester,
    ) async {
      final app = _ReceiptApp(
        receipt(billPhotoUrl: 'https://example.test/bill.jpg', amount: 450.75),
      );
      final payment = _bill(
        5,
        'utility',
        450.75,
        status: 'Paid',
        due: DateTime(2026, 10, 5),
      );
      await _pump(tester, app, ReceiptScreen(payment: payment));

      expect(find.text('₱450.75'), findsNWidgets(2));
      expect(find.text('View bill photo'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });

    testWidgets('an unpaid utility bill shows its photo link before paying', (
      tester,
    ) async {
      final app = _tenant(due: 820)
        ..unpaidPayments = [
          PaymentRecord(
            id: 5,
            type: 'utility',
            monthLabel: 'Nov',
            amount: 820,
            status: 'Pending',
            dueDate: DateTime(2026, 11, 5),
            paidOn: DateTime(2026, 11, 5),
            billPhotoUrl: 'https://example.test/bill.jpg',
          ),
          _bill(4, 'rent', 3500, due: DateTime(2026, 11, 1)),
        ];
      await _pump(tester, app, const MyPaymentsScreen());

      // Only the bill that has a photo gets the link.
      expect(find.text('View bill photo'), findsOneWidget);
      expect(find.text('Pay now'), findsNWidgets(2));
    });

    testWidgets('fits a small phone with a large system font', (tester) async {
      tester.platformDispatcher.textScaleFactorTestValue = 1.5;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      final payment = _bill(
        12,
        'rent',
        3500,
        status: 'Paid',
        due: DateTime(2026, 10, 1),
      );
      await _pump(
        tester,
        _ReceiptApp(receipt(billPhotoUrl: 'https://example.test/bill.jpg')),
        ReceiptScreen(payment: payment),
        width: 320,
      );

      expect(find.text('Paid in Full'), findsOneWidget);
      expect(tester.takeException(), isNull);
    });
  });
}
