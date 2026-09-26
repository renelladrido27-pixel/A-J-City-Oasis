import 'package:flutter_test/flutter_test.dart';

import 'package:ajoasis_mobile/main.dart';
import 'package:ajoasis_mobile/state/app_state.dart';

void main() {
  testWidgets('App boots to the splash screen without crashing', (WidgetTester tester) async {
    await tester.pumpWidget(AJOasisApp(appState: AppState()));
    await tester.pump();

    // Deliberately doesn't pumpAndSettle or assert on the room list — this
    // app now makes a real network call on the Home tab (see mobile/README.md),
    // which a plain widget test shouldn't depend on.
    expect(find.text('A & J OASIS'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
