import 'package:flutter_test/flutter_test.dart';
import 'package:middo_operation/main.dart';
import 'package:middo_operation/data/operation_repository.dart';
import 'package:shared_preferences/shared_preferences.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
  });

  testWidgets('Splash shows Operation branding then login', (tester) async {
    await tester.pumpWidget(
      MiddoOperationApp(repository: createOperationRepository()),
    );
    await tester.pump();

    expect(find.text('MIDDO'), findsOneWidget);
    expect(find.text('Operation'), findsOneWidget);

    await tester.pump(const Duration(milliseconds: 1200));
    await tester.pumpAndSettle();

    expect(find.text('Sign in'), findsOneWidget);
    expect(find.text('Mobile'), findsOneWidget);
  });
}
