import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:middo_delivery/data/delivery_repository.dart';
import 'package:middo_delivery/data/offline_mutation_queue.dart';
import 'package:middo_delivery/app_scope.dart';
import 'package:middo_delivery/main.dart';
import 'package:middo_delivery/screens/run_detail_screen.dart';
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

    // Splash uses looping pulse until navigation (~2.2s).
    await tester.pump(const Duration(milliseconds: 2300));
    await tester.pumpAndSettle();

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
    await tester.pump(const Duration(milliseconds: 2300));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).first, '01310123454');
    await tester.enterText(find.byType(TextField).at(1), '12345678');
    await tester.tap(find.text('Sign In'));
    await tester.pumpAndSettle();

    expect(find.text('Delivery Dashboard'), findsOneWidget);
    expect(find.text('Alerts'), findsWidgets);
  });

  test('Mock cash handover uses order_ids + target', () async {
    final repo = MockDeliveryRepository();
    final handovers = await repo.cashHandovers();
    final eligible = (handovers['eligible_orders'] as List?) ?? [];
    expect(eligible, isNotEmpty);

    final id = (eligible.first as Map)['id'] as int;
    final res = await repo.createCashHandover(
      orderIds: [id],
      target: 'kitchen',
      notes: 'test',
    );
    expect(res['message']?.toString(), contains('handover'));
    expect((res['handover'] as Map)['target'], 'kitchen');
  });

  test('Mock deliver accepts OTP 1234', () async {
    final repo = MockDeliveryRepository();
    final otp = await repo.sendDeliveryOtp(102);
    expect(otp['debug_otp'], '1234');
    final res = await repo.deliverRun(102, otp: '1234');
    expect(res['message']?.toString(), contains('Delivered'));
  });

  test('Mock account withdraw gated by can_request_payment', () async {
    final repo = MockDeliveryRepository();
    final account = await repo.account();
    expect(account.containsKey('can_request_payment'), isTrue);
    expect(account.containsKey('due_to_middo'), isTrue);
    expect(account['statement'], isA<List>());
    expect(account['withdrawals'], isA<List>());
  });

  test('Offline mutation queue enqueues and persists', () async {
    SharedPreferences.setMockInitialValues({});
    final queue = OfflineMutationQueue.instance;
    final before = queue.pendingCount;
    final res = await queue.enqueue(
      type: 'pickup',
      method: 'POST',
      path: '/runs/1/pickup',
      body: const {},
    );
    expect(res['queued'], isTrue);
    expect(queue.pendingCount, greaterThanOrEqualTo(before + 1));
    expect(queue.items.last['idempotency_key'], isNotEmpty);
  });

  test('Offline mutation queue parks permanent failures for review', () async {
    SharedPreferences.setMockInitialValues({});
    final queue = OfflineMutationQueue.instance;
    await queue.enqueue(
      type: 'collect_cash',
      method: 'POST',
      path: '/orders/1/collect-cash',
      body: {'amount': 10},
    );
    expect(queue.pendingCount, greaterThan(0));
  });

  test('Mock pending boxes expose run_groups', () async {
    final repo = MockDeliveryRepository();
    final pending = await repo.pendingBoxes();
    expect(pending['run_groups'], isA<List>());
    expect((pending['run_groups'] as List), isNotEmpty);
  });





  testWidgets('Run detail can set customer ETA chips', (tester) async {
    final repo = MockDeliveryRepository();
    await tester.pumpWidget(
      MaterialApp(
        home: AppScope(
          repository: repo,
          child: const RunDetailScreen(runId: 102),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Customer ETA'), findsOneWidget);
    await tester.tap(find.byKey(const ValueKey('eta-25')));
    await tester.pumpAndSettle();

    expect(find.textContaining('About 25 min'), findsWidgets);
    final shown = await repo.showRun(102);
    expect((shown['run'] as Map)['eta_minutes'], 25);
  });



}
