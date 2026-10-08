import 'package:ajoasis_mobile/models/app_notification.dart';
import 'package:ajoasis_mobile/models/booking_summary.dart';
import 'package:ajoasis_mobile/screens/booking_payment_screen.dart';
import 'package:ajoasis_mobile/screens/notifications_screen.dart';
import 'package:ajoasis_mobile/state/app_state.dart';
import 'package:ajoasis_mobile/widgets/notification_card.dart';
import 'package:ajoasis_mobile/widgets/oasis_bottom_nav.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';

AppNotification _notification({
  int id = 1,
  String type = 'payment',
  bool isRead = false,
  String title = 'Rent due soon',
}) => AppNotification(
  id: id,
  title: title,
  message: 'Your rent for November is due on Nov 1.',
  type: type,
  isRead: isRead,
  createdAt: DateTime.now().subtract(const Duration(minutes: 5)),
);

/// A phone-sized screen with [app] as the app state.
Future<void> _pump(
  WidgetTester tester,
  AppState app,
  Widget child, {
  double width = 360,
}) async {
  tester.view.physicalSize = Size(width, 780);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);
  await tester.pumpWidget(
    AppStateScope(
      notifier: app,
      child: MaterialApp(home: Scaffold(body: child)),
    ),
  );
  await tester.pumpAndSettle();
}

void main() {
  // The API client keeps its login token in secure storage.
  setUp(() => FlutterSecureStorage.setMockInitialValues({}));

  group('AppNotification', () {
    test('groups server types the way the website filter does', () {
      expect(_notification(type: 'utility').category, 'payment');
      expect(_notification(type: 'transfer').category, 'lease');
      expect(_notification(type: 'move_out').category, 'lease');
      expect(_notification(type: 'maintenance').category, 'maintenance');
    });

    test('says how long ago in words', () {
      final now = DateTime.utc(2026, 10, 8, 12);
      String ago(Duration d) =>
          AppNotification.relativeTime(now.subtract(d), now);

      expect(ago(const Duration(seconds: 20)), 'Just now');
      expect(ago(const Duration(minutes: 1)), '1 minute ago');
      expect(ago(const Duration(minutes: 5)), '5 minutes ago');
      expect(ago(const Duration(hours: 3)), '3 hours ago');
      expect(ago(const Duration(days: 1)), '1 day ago');
      expect(ago(const Duration(days: 15)), '2 weeks ago');
      expect(AppNotification.relativeTime(null, now), '');
    });
  });

  group('Notifications screen', () {
    testWidgets('shows each notification as a card with its message and '
        'actions, not a line of text', (tester) async {
      final app = AppState()
        ..notifications = [
          _notification(id: 1),
          _notification(
            id: 2,
            type: 'maintenance',
            isRead: true,
            title: 'Request resolved',
          ),
        ];
      await _pump(tester, app, NotificationsScreen(onOpen: (_) {}));

      expect(find.byType(NotificationCard), findsNWidgets(2));
      expect(find.text('Rent due soon'), findsOneWidget);
      expect(
        find.text('Your rent for November is due on Nov 1.'),
        findsNWidgets(2),
      );
      expect(find.text('Payments · 5 minutes ago'), findsOneWidget);
      expect(find.text('1 new'), findsOneWidget);
      expect(find.text('Mark as read'), findsOneWidget);
      expect(find.text('Mark as unread'), findsOneWidget);
      expect(find.text('View'), findsNWidgets(2));
      expect(tester.takeException(), isNull);
    });

    testWidgets('the category filter narrows the list', (tester) async {
      final app = AppState()
        ..notifications = [
          _notification(id: 1),
          _notification(id: 2, type: 'maintenance', title: 'Request resolved'),
        ];
      await _pump(tester, app, NotificationsScreen(onOpen: (_) {}));

      await tester.ensureVisible(find.text('Maintenance'));
      await tester.tap(find.text('Maintenance'));
      await tester.pumpAndSettle();

      expect(find.text('Request resolved'), findsOneWidget);
      expect(find.text('Rent due soon'), findsNothing);

      await tester.ensureVisible(find.text('Bookings'));
      await tester.tap(find.text('Bookings'));
      await tester.pumpAndSettle();

      expect(find.byType(NotificationCard), findsNothing);
      expect(find.text('No bookings notifications yet.'), findsOneWidget);
    });

    testWidgets('View marks it read and hands it to the shell', (tester) async {
      final app = AppState()..notifications = [_notification()];
      final opened = <AppNotification>[];
      await _pump(tester, app, NotificationsScreen(onOpen: opened.add));

      await tester.tap(find.text('View'));
      await tester.pump();

      expect(opened.single.id, 1);
      // Read straight away, before the server has answered.
      expect(app.notifications.single.isRead, isTrue);
      expect(app.unreadNotificationCount, 0);
      await tester.pumpAndSettle();
    });

    testWidgets('fits a small phone with a large system font', (tester) async {
      final app = AppState()
        ..notifications = [
          _notification(
            title: 'Your maintenance request has been scheduled for tomorrow',
          ),
        ];
      tester.platformDispatcher.textScaleFactorTestValue = 1.5;
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      await _pump(tester, app, NotificationsScreen(onOpen: (_) {}), width: 320);

      expect(tester.takeException(), isNull);
    });
  });

  testWidgets('the Alerts tab shows how many notifications are unread', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          bottomNavigationBar: OasisBottomNav(
            current: OasisTab.home,
            onSelect: (_) {},
            alertsBadge: 3,
          ),
        ),
      ),
    );

    expect(find.text('3'), findsOneWidget);
  });

  testWidgets('the booking payment step has no method picker, just '
      '"Proceed to payment"', (tester) async {
    final app = AppState()
      ..currentBooking = const BookingSummary(
        id: 7,
        status: 'pending',
        advanceAmount: 3500,
        depositAmount: 3500,
        securityAmount: 3500,
        totalAmount: 10500,
      );
    await _pump(tester, app, const BookingPaymentScreen());

    expect(find.text('Proceed to payment'), findsOneWidget);
    expect(find.text('Payment method'), findsNothing);
    for (final method in ['GCash', 'Maya', 'Card', 'Bank']) {
      expect(find.text(method), findsNothing);
    }
    expect(find.text('₱10,500'), findsWidgets);
    expect(tester.takeException(), isNull);
  });
}
