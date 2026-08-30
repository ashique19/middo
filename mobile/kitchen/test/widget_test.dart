import 'package:flutter_test/flutter_test.dart';
import 'package:middo_kitchen/data/kitchen_repository.dart';
import 'package:middo_kitchen/main.dart';

void main() {
  testWidgets('Splash shows kitchen branding then login', (tester) async {
    await tester.pumpWidget(
      MiddoKitchenApp(repository: MockKitchenRepository()),
    );
    await tester.pump();

    expect(find.text('Middo'), findsOneWidget);
    expect(find.text('Kitchen'), findsOneWidget);

    await tester.pumpAndSettle(const Duration(seconds: 3));

    expect(find.textContaining('Cook, pack'), findsOneWidget);
    expect(find.text('Sign In'), findsOneWidget);
    expect(find.text('MOBILE'), findsOneWidget);
  });
}
