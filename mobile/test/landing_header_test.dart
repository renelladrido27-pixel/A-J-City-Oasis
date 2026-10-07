import 'package:ajoasis_mobile/screens/landing_screen.dart';
import 'package:ajoasis_mobile/widgets/room_photo.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

/// The home header once pushed "Sign up" off the side of the phone and let
/// "Log in" collide with the business name. These render it at real phone
/// widths — including a small phone with a large system font — and fail on
/// any overflow.
void main() {
  Future<void> pumpHeader(
    WidgetTester tester, {
    required double width,
    double textScale = 1.0,
  }) async {
    tester.view.physicalSize = Size(width, 800);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    await tester.pumpWidget(
      MaterialApp(
        home: MediaQuery(
          data: MediaQueryData(
            size: Size(width, 800),
            textScaler: TextScaler.linear(textScale),
          ),
          child: Scaffold(
            body: Padding(
              // Same side padding as the landing screen.
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: LandingHeader(
                showAuthButtons: true,
                onLogIn: () {},
                onSignUp: () {},
              ),
            ),
          ),
        ),
      ),
    );
  }

  for (final (width, scale) in [
    (320.0, 1.0), // small phone
    (360.0, 1.0), // common Android width
    (412.0, 1.0),
    (320.0, 1.3), // small phone + large font setting
    (360.0, 1.5),
  ]) {
    testWidgets('header fits at ${width.toInt()}px, text scale $scale', (
      tester,
    ) async {
      await pumpHeader(tester, width: width, textScale: scale);

      expect(tester.takeException(), isNull, reason: 'layout overflowed');

      final name = tester.getRect(find.text('A&J CITY OASIS'));
      final logIn = tester.getRect(
        find.widgetWithText(OutlinedButton, 'Log in'),
      );
      final signUp = tester.getRect(
        find.widgetWithText(ElevatedButton, 'Sign up'),
      );

      expect(
        name.right,
        lessThanOrEqualTo(logIn.left),
        reason: 'name runs into Log in',
      );
      expect(logIn.right, lessThanOrEqualTo(signUp.left));
      expect(
        signUp.right,
        lessThanOrEqualTo(width - 20),
        reason: 'Sign up leaves the screen',
      );
      expect(name.left, greaterThanOrEqualTo(20));
    });
  }

  testWidgets('header hides the buttons once logged in', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: LandingHeader(
            showAuthButtons: false,
            onLogIn: () {},
            onSignUp: () {},
          ),
        ),
      ),
    );

    expect(find.text('A&J CITY OASIS'), findsOneWidget);
    expect(find.text('Log in'), findsNothing);
    expect(find.text('Sign up'), findsNothing);
  });

  testWidgets('a room with no photo shows the placeholder, not an error', (
    tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: SizedBox(height: 200, child: RoomGallery(images: [])),
        ),
      ),
    );

    expect(find.byType(RoomPhotoPlaceholder), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
