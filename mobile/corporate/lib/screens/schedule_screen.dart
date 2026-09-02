import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/tab_scroll_bus.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

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
            return const MiddoPageLoader(message: 'Loading schedule…');
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }

          final orders = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              controller: _scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
              children: [
                Text(
                  'Scheduled',
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.8,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  '${orders.length} upcoming orders on your calendar.',
                  style: const TextStyle(
                    color: MiddoColors.inkSoft,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 14),
                FilledButton(
                  onPressed: () => context.go('/menu'),
                  child: const Text('Place New Order'),
                ),
                const SizedBox(height: 16),
                if (orders.isEmpty)
                  const Text(
                    'No upcoming lunch schedules found.',
                    style: TextStyle(
                      color: MiddoColors.inkSoft,
                      fontWeight: FontWeight.w600,
                    ),
                  )
                else
                  ...orders.map(
                    (order) => MealOrderCard(
                      order: order,
                      onTrack: () => context.push('/track/${order.id}'),
                      onSecondary:
                          order.canDelete ? () => _cancelOrder(order) : null,
                      secondaryLabel: 'Cancel',
                      onPay: order.canPayOnline &&
                              order.onlinePaymentUrl != null
                          ? () {
                              final uri =
                                  Uri.tryParse(order.onlinePaymentUrl!);
                              if (uri != null) {
                                launchUrl(
                                  uri,
                                  mode: LaunchMode.externalApplication,
                                );
                              }
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
