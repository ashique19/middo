import 'package:flutter_test/flutter_test.dart';
import 'package:middo_corporate/data/corporate_repository.dart';
import 'package:middo_corporate/main.dart';

void main() {
  testWidgets('Splash animates then shows login branding', (tester) async {
    await tester.pumpWidget(
      MiddoCorporateApp(repository: MockCorporateRepository()),
    );
    await tester.pump();

    expect(find.text('Middo'), findsOneWidget);
    expect(find.text('Corporate'), findsOneWidget);

    await tester.pumpAndSettle(const Duration(seconds: 3));

    expect(find.textContaining('Office lunches'), findsOneWidget);
    expect(find.text('Sign In'), findsOneWidget);
    expect(find.text('MOBILE'), findsOneWidget);
  });
}
