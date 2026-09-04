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
            return const ListSkeleton(rows: 4);
          }
          if (snapshot.hasError) {
            return MiddoEmptyState(
              icon: Icons.cloud_off_rounded,
              title: 'Couldn’t load history',
              message: snapshot.error.toString(),
              actionLabel: 'Retry',
              onAction: _reload,
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
                  MiddoEmptyState(
                    icon: Icons.history_rounded,
                    title: 'No lunch history yet',
                    message:
                        'Past office lunches will show up here after your first delivery.',
                    actionLabel: 'Go home',
                    onAction: () => context.go('/home'),
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
