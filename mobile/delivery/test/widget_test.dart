import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:middo_delivery/data/delivery_repository.dart';
import 'package:middo_delivery/main.dart';
import 'package:middo_delivery/widgets/empty_state.dart';
import 'package:middo_delivery/widgets/skeleton.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('Splash shows delivery branding then login', (tester) async {
    await tester.pumpWidget(
      MiddoDeliveryApp(repository: MockDeliveryRepository()),
    );
    await tester.pump();

    expect(find.text('Middo'), findsOneWidget);
    expect(find.text('Delivery'), findsOneWidget);

    await tester.pumpAndSettle(const Duration(seconds: 3));

    expect(find.textContaining('Pick up, deliver'), findsOneWidget);
    expect(find.text('Sign In'), findsOneWidget);
    expect(find.text('MOBILE'), findsOneWidget);
  });

  testWidgets('Empty state and skeleton widgets render', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: const [
              Expanded(
                child: MiddoEmptyState(
                  icon: Icons.inbox_outlined,
                  title: 'Nothing here',
                  message: 'Try again later',
                ),
              ),
              SizedBox(height: 120, child: ListSkeleton(rows: 1)),
            ],
          ),
        ),
      ),
    );
    expect(find.text('Nothing here'), findsOneWidget);
    expect(find.byType(SkeletonBox), findsWidgets);
  });

  testWidgets('Mock login reaches home shell', (tester) async {
    final repo = MockDeliveryRepository();
    await tester.pumpWidget(MiddoDeliveryApp(repository: repo));
    await tester.pumpAndSettle(const Duration(seconds: 3));

    await tester.enterText(find.byType(TextField).first, '01310123454');
    await tester.enterText(find.byType(TextField).at(1), '12345678');
    await tester.tap(find.text('Sign In'));
    await tester.pumpAndSettle();

    expect(find.text('Delivery Dashboard'), findsOneWidget);
    expect(find.text('Alerts'), findsWidgets);
  });
}
