import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/empty_state.dart';
import '../widgets/kitchen_mobile_header.dart';
import '../widgets/kitchen_ui.dart';
import '../widgets/skeleton.dart';

class OrderHistoryScreen extends StatefulWidget {
  const OrderHistoryScreen({super.key, this.initialPeriod = 'this_month'});

  final String initialPeriod;

  @override
  State<OrderHistoryScreen> createState() => _OrderHistoryScreenState();
}

class _OrderHistoryScreenState extends State<OrderHistoryScreen> {
  late String _period = widget.initialPeriod;
  Future<Map<String, dynamic>>? _future;

  static const _periods = [
    ('this_month', 'This month'),
    ('last_month', 'Last month'),
    ('last_3_months', 'Last 3 months'),
  ];

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).ordersHistory(period: _period);
  }

  Future<void> _load(String period) async {
    MiddoHaptics.selection();
    setState(() {
      _period = period;
      _future = AppScope.of(context).ordersHistory(period: period);
    });
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const KitchenMobileHeader(
        title: 'Order history',
        showBack: true,
      ),
      body: Column(
        children: [
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: Row(
              children: [
                for (final (key, label) in _periods) ...[
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: ChoiceChip(
                      label: Text(label),
                      selected: _period == key,
                      onSelected: (_) => _load(key),
                    ),
                  ),
                ],
              ],
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _load(_period),
              child: FutureBuilder<Map<String, dynamic>>(
                future: _future,
                builder: (context, snap) {
                  if (snap.connectionState != ConnectionState.done) {
                    return const ListSkeleton(rows: 5);
                  }
                  if (snap.hasError) {
                    return KitchenError(
                      snap.error!,
                      onRetry: () => _load(_period),
                    );
                  }
                  final groups = (snap.data?['groups'] as List?) ?? const [];
                  final label = snap.data?['label']?.toString() ?? '';
                  if (groups.isEmpty) {
                    return ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: [
                        SizedBox(
                          height: MediaQuery.sizeOf(context).height * 0.5,
                          child: MiddoEmptyState(
                            icon: Icons.history,
                            title: 'No orders in $label',
                            message:
                                'History shows groups your kitchen cooked in this period.',
                            actionLabel: 'Active orders',
                            onAction: () => context.go('/orders'),
                          ),
                        ),
                      ],
                    );
                  }
                  return ListView.builder(
                    padding: const EdgeInsets.fromLTRB(16, 4, 16, 24),
                    itemCount: groups.length + 1,
                    itemBuilder: (context, index) {
                      if (index == 0) {
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: Text(
                            label,
                            style: const TextStyle(
                              fontWeight: FontWeight.w800,
                              color: MiddoColors.inkSoft,
                            ),
                          ),
                        );
                      }
                      final g = groups[index - 1] as Map;
                      final orders = (g['orders'] as List?) ?? const [];
                      return Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: KitchenPanel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                g['name']?.toString() ?? 'Group',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 16,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                '${g['menu_name'] ?? 'Menu'} · ${g['delivery_date'] ?? ''} · qty ${g['total_quantity'] ?? '—'}',
                                style: const TextStyle(
                                  color: MiddoColors.inkSoft,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              if (orders.isNotEmpty) ...[
                                const SizedBox(height: 10),
                                for (final raw in orders.take(6))
                                  ListTile(
                                    contentPadding: EdgeInsets.zero,
                                    dense: true,
                                    title: Text(
                                      '#${(raw as Map)['id']} · ${raw['menu_name'] ?? ''}',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                        fontSize: 13,
                                      ),
                                    ),
                                    subtitle: Text(
                                      '${raw['order_status'] ?? ''} · ${raw['delivery_time'] ?? ''} · x${raw['quantity'] ?? ''}',
                                    ),
                                    onTap: () {
                                      MiddoHaptics.selection();
                                      context.push('/orders/${raw['id']}');
                                    },
                                  ),
                              ],
                            ],
                          ),
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}
