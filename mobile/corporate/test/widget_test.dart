import 'package:flutter_test/flutter_test.dart';
import 'package:middo_corporate/main.dart';

void main() {
  testWidgets('Login screen renders Middo corporate branding', (tester) async {
    await tester.pumpWidget(const MiddoCorporateApp());
    await tester.pumpAndSettle();

    expect(find.textContaining('Office lunches'), findsOneWidget);
    expect(find.text('Sign In'), findsOneWidget);
  });
}
