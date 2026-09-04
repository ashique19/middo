import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/tab_scroll_bus.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';
import 'payment_webview_screen.dart';

class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  static const _tabIndex = 2;

  Future<List<CorporateOrder>>? _future;
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    TabScrollBus.instance.register(_tabIndex, _scrollController);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).scheduled();
  }

  @override
  void dispose() {
    TabScrollBus.instance.unregister(_tabIndex, _scrollController);
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final next = AppScope.of(context).scheduled();
    setState(() => _future = next);
    await next;
  }

  Future<void> _cancelOrder(CorporateOrder order) async {
    if (!order.canDelete) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'This order can no longer be cancelled — the cut-off time has passed.',
          ),
        ),
      );
      return;
    }

    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel this order?'),
        content: Text(
          'This removes the ${DateFormat('MMM d').format(order.deliveryDate)} lunch'
          '${order.amountPaid > 0 ? ' and credits ${bdt.format(order.amountPaid)} prepaid amount back to Middo Balance' : ''}'
          '.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep order'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: MiddoColors.orangeDeep),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Cancel order'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    try {
      await AppScope.of(context).cancelOrder(order.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order cancelled'),
          backgroundColor: MiddoColors.forest,
        ),
      );
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: FutureBuilder<List<CorporateOrder>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const ListSkeleton(rows: 4);
          }
          if (snapshot.hasError) {
            return MiddoEmptyState(
              icon: Icons.cloud_off_rounded,
              title: 'Couldn’t load schedule',
              message: snapshot.error.toString(),
              actionLabel: 'Retry',
              onAction: _reload,
            );
          }

          final orders = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              controller: _scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Scheduled',
                            style: Theme.of(context)
                                .textTheme
                                .headlineMedium
                                ?.copyWith(
                                  fontWeight: FontWeight.w800,
                                  letterSpacing: -0.8,
                                ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            orders.isEmpty
                                ? 'No upcoming lunches yet.'
                                : '${orders.length} upcoming order${orders.length == 1 ? '' : 's'}.',
                            style: const TextStyle(
                              color: MiddoColors.inkSoft,
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                            ),
                          ),
                        ],
                      ),
                    ),
                    IconButton(
                      tooltip: 'Home',
                      onPressed: () => context.go('/home'),
                      icon: const Icon(Icons.home_outlined),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: FilledButton.icon(
                        onPressed: () => context.go('/menu'),
                        style: FilledButton.styleFrom(
                          backgroundColor: MiddoColors.forest,
                          minimumSize: const Size.fromHeight(48),
                        ),
                        icon: const Icon(Icons.add_rounded, size: 20),
                        label: const Text('New order'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: OutlinedButton.icon(
                        onPressed: () => context.push('/history'),
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(48),
                        ),
                        icon: const Icon(Icons.history_rounded, size: 20),
                        label: const Text('History'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                if (orders.isEmpty)
                  MiddoEmptyState(
                    icon: Icons.calendar_month_outlined,
                    title: 'Nothing on the calendar',
                    message:
                        'Order from the menu, or check past lunches in History.',
                    actionLabel: 'Browse menu',
                    onAction: () => context.go('/menu'),
                  )
                else
                  ...orders.map(
                    (order) => MealOrderCard(
                      order: order,
                      onTrack: () => context.push('/track/${order.id}'),
                      onSecondary: order.canDelete
                          ? () => _cancelOrder(order)
                          : () => context.push('/support/${order.id}'),
                      secondaryLabel: order.canDelete ? 'Cancel' : 'Support',
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
              ],
            ),
          );
        },
      ),
    );
  }
}
