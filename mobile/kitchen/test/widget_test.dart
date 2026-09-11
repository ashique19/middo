import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:middo_kitchen/data/kitchen_repository.dart';
import 'package:middo_kitchen/main.dart';
import 'package:middo_kitchen/widgets/empty_state.dart';
import 'package:middo_kitchen/widgets/skeleton.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('Splash shows kitchen branding then login', (tester) async {
    await tester.pumpWidget(
      MiddoKitchenApp(repository: MockKitchenRepository()),
    );
    await tester.pump();

    expect(find.text('Middo'), findsOneWidget);
    expect(find.text('Kitchen'), findsOneWidget);

    // Splash uses looping pulse/steam until navigation (~2.2s).
    await tester.pump(const Duration(milliseconds: 2300));
    await tester.pumpAndSettle();

    expect(find.textContaining('Cook, pack'), findsOneWidget);
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
    final repo = MockKitchenRepository();
    await tester.pumpWidget(MiddoKitchenApp(repository: repo));
    await tester.pump(const Duration(milliseconds: 2300));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).first, '01310123453');
    await tester.enterText(find.byType(TextField).at(1), '12345678');
    await tester.tap(find.text('Sign In'));
    await tester.pumpAndSettle();

    expect(find.text('Kitchen Dashboard'), findsOneWidget);
    expect(find.text('Alerts'), findsWidgets);
  });
}
