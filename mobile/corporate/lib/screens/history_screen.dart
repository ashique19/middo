import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';
import 'payment_webview_screen.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  Future<List<CorporateOrder>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).history();
  }

  Future<void> _reload() async {
    final next = AppScope.of(context).history();
    setState(() => _future = next);
    await next;
  }

  void _goBack() {
    if (context.canPop()) {
      context.pop();
    } else {
      context.go('/schedule');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          tooltip: 'Back',
          onPressed: _goBack,
        ),
        title: const Text('Order history'),
        actions: [
          IconButton(
            tooltip: 'Schedule',
            onPressed: () => context.go('/schedule'),
            icon: const Icon(Icons.calendar_month_outlined),
          ),
          IconButton(
            tooltip: 'Home',
            onPressed: () => context.go('/home'),
            icon: const Icon(Icons.home_outlined),
          ),
        ],
      ),
      body: FutureBuilder<List<CorporateOrder>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const MiddoPageLoader(message: 'Loading history…');
          }
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(snapshot.error.toString()),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: _reload,
                      child: const Text('Retry'),
                    ),
                    TextButton(
                      onPressed: () => context.go('/home'),
                      child: const Text('Go home'),
                    ),
                  ],
                ),
              ),
            );
          }
          final orders = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 24),
              children: [
                const Text(
                  'Past office lunches. Reorder a favourite or leave feedback.',
                  style: TextStyle(
                    color: MiddoColors.inkSoft,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => context.go('/schedule'),
                        icon: const Icon(Icons.calendar_month_outlined, size: 18),
                        label: const Text('Schedule'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => context.go('/menu'),
                        icon: const Icon(Icons.restaurant_menu_outlined, size: 18),
                        label: const Text('Menu'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                if (orders.isEmpty)
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: MiddoColors.white,
                      borderRadius: BorderRadius.circular(18),
                      border: Border.all(color: MiddoColors.creamBorder),
                    ),
                    child: Column(
                      children: [
                        const Text(
                          'No previous lunch history yet.',
                          style: TextStyle(
                            color: MiddoColors.inkSoft,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        const SizedBox(height: 10),
                        TextButton(
                          onPressed: () => context.go('/home'),
                          child: const Text('Go home'),
                        ),
                      ],
                    ),
                  )
                else
                  ...orders.map(
                    (order) => Opacity(
                      opacity: 0.92,
                      child: MealOrderCard(
                        order: order,
                        onTrack: () =>
                            context.push('/checkout/${order.menuItem.id}'),
                        onSecondary: () =>
                            context.push('/support/${order.id}'),
                        secondaryLabel: 'Feedback',
                        onPay: order.canPayOnline &&
                                order.onlinePaymentUrl != null
                            ? () {
                                PaymentWebViewScreen.open(
                                  context,
                                  paymentUrl: order.onlinePaymentUrl!,
                                  title: 'Make payment',
                                ).then((_) {
                                  if (mounted) _reload();
                                });
                              }
                            : null,
                      ),
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}
