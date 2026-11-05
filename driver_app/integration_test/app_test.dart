import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:integration_test/integration_test.dart';
import 'package:og_driver/main.dart' as app;

/// End-to-end smoke test against a running backend (`php artisan serve --host=0.0.0.0` with
/// `php artisan db:seed --class=DriverAppDemoSeeder`): log in, check in, open a task and the delivery form.
void main() {
  IntegrationTestWidgetsFlutterBinding.ensureInitialized();

  Future<void> settle(WidgetTester tester, {int seconds = 3}) async {
    for (var i = 0; i < seconds * 4; i++) {
      await tester.pump(const Duration(milliseconds: 250));
      await Future<void>.delayed(const Duration(milliseconds: 250));
    }
  }

  Future<void> pause() => Future<void>.delayed(const Duration(seconds: 4));

  testWidgets('driver logs in, checks in and opens a delivery', (tester) async {
    await app.main();
    await settle(tester);

    if (find.text('Log in').evaluate().isNotEmpty) {
      await tester.enterText(find.byType(TextFormField).at(0), 'driver.kl@og.local');
      await tester.enterText(find.byType(TextFormField).at(1), 'password');
      await pause();
      await tester.tap(find.text('Log in'));
      await settle(tester, seconds: 6);
    }

    expect(find.text('My Job Sheet'), findsWidgets);
    await pause();

    if (find.text('Check in now').evaluate().isNotEmpty) {
      await tester.tap(find.text('Check in now'));
      await settle(tester, seconds: 2);
      await pause();
      await tester.tap(find.widgetWithText(FilledButton, 'Check in'));
      await settle(tester, seconds: 8);
    }

    expect(find.textContaining('Route: '), findsWidgets);
    await pause();

    await tester.tap(find.text('Klang Furniture Mart').first);
    await settle(tester, seconds: 2);
    expect(find.text('Deliver'), findsWidgets);
    await pause();

    await tester.tap(find.widgetWithText(FilledButton, 'Deliver'));
    await settle(tester, seconds: 4);
    await pause();

    await tester.scrollUntilVisible(find.text('COD collection'), 300, scrollable: find.byType(Scrollable).first);
    await settle(tester, seconds: 1);
    expect(find.text('Amount due'), findsOneWidget);
    await pause();

    await tester.scrollUntilVisible(
      find.text('GPS & time (automatic)'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await settle(tester, seconds: 2);
    await pause();

    // mandatory items are enforced before the DO can be marked delivered
    await tester.tap(find.text('Mark as delivered'));
    await settle(tester, seconds: 1);
    expect(find.text('Enter the recipient name'), findsOneWidget);
    await pause();
  });
}
