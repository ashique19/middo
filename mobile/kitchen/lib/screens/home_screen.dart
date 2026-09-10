import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/empty_state.dart';
import '../widgets/kitchen_ui.dart';
import '../widgets/skeleton.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Future<Map<String, dynamic>>? _dashboard;
  Future<List<dynamic>>? _alerts;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final repo = AppScope.of(context);
    _dashboard ??= repo.dashboard();
    _alerts ??= repo.alerts();
  }

  void _reloadFutures() {
    final repo = AppScope.of(context);
    _dashboard = repo.dashboard();
    _alerts = repo.alerts();
  }

  Future<void> _reload() async {
    setState(_reloadFutures);
    await Future.wait([
      _dashboard ?? Future.value(<String, dynamic>{}),
      _alerts ?? Future.value(<dynamic>[]),
    ]);
  }

  void _openTile(String key) {
    MiddoHaptics.selection();
    switch (key) {
      case 'alerts':
        context.push('/alerts');
        return;
      case 'claimable_groups':
        context.go('/groups');
        return;
      case 'boxes_in_stock':
      case 'boxes':
        context.push('/boxes');
        return;
      case 'preparing':
      case 'ready_for_pickup':
      case 'active_orders':
        context.go('/orders');
        return;
      case 'orders_this_month':
        context.push('/history?period=this_month');
        return;
      case 'orders_last_month':
        context.push('/history?period=last_month');
        return;
      case 'orders_last_3_months':
        context.push('/history?period=last_3_months');
        return;
      default:
        return;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _reload,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            FutureBuilder<Map<String, dynamic>>(
              future: _dashboard,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const Column(
                    children: [
                      SkeletonBox(height: 48, radius: 14),
                      SizedBox(height: 12),
                      SkeletonBox(height: 14, width: 180),
                      SizedBox(height: 16),
                      Row(
                        children: [
                          Expanded(child: SkeletonBox(height: 88, radius: 14)),
                          SizedBox(width: 10),
                          Expanded(child: SkeletonBox(height: 88, radius: 14)),
                        ],
                      ),
                      SizedBox(height: 10),
                      Row(
                        children: [
                          Expanded(child: SkeletonBox(height: 88, radius: 14)),
                          SizedBox(width: 10),
                          Expanded(child: SkeletonBox(height: 88, radius: 14)),
                        ],
                      ),
                    ],
                  );
                }
                if (snap.hasError) {
                  return MiddoEmptyState(
                    icon: Icons.cloud_off_outlined,
                    title: 'Dashboard unavailable',
                    message: '${snap.error}',
                    actionLabel: 'Retry',
                    onAction: _reload,
                  );
                }
                final tiles = (snap.data?['tiles'] as List?) ?? const [];
                final capacity =
                    (snap.data?['capacity'] as Map?)?.cast<String, dynamic>() ??
                        const <String, dynamic>{};
                final notices =
                    (snap.data?['ops_incoming_notices'] as List?) ?? const [];
                final lowStock = snap.data?['insufficient_box_stock'] == true;
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (lowStock)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 10),
                        child: KitchenPanel(
                          onTap: () {
                            MiddoHaptics.warning();
                            context.push('/boxes');
                          },
                          child: const Text(
                            'Box stock is low vs capacity — request more boxes.',
                            style: TextStyle(
                              color: MiddoColors.orange,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    if (capacity.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          'Slots ${capacity['open_groups'] ?? '—'} / ${capacity['allowed_open_groups'] ?? '—'} · Boxes ${capacity['sendable_boxes'] ?? '—'}',
                          style: const TextStyle(
                            color: MiddoColors.inkSoft,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        for (final raw in tiles)
                          _Tile(
                            label: (raw as Map)['label']?.toString() ?? '',
                            value: '${raw['count'] ?? '—'}',
                            onTap: () =>
                                _openTile(raw['key']?.toString() ?? ''),
                          ),
                      ],
                    ),
                    if (notices.isNotEmpty) ...[
                      const SizedBox(height: 16),
                      for (final n in notices)
                        Padding(
                          padding: const EdgeInsets.only(bottom: 8),
                          child: KitchenPanel(
                            child: Text(
                              n.toString(),
                              style: const TextStyle(
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ),
                    ],
                  ],
                );
              },
            ),
            const SizedBox(height: 22),
            Row(
              children: [
                Expanded(
                  child: Text(
                    'Alerts',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                ),
                TextButton(
                  onPressed: () => context.push('/alerts'),
                  child: const Text('See all'),
                ),
              ],
            ),
            const SizedBox(height: 8),
            FutureBuilder<List<dynamic>>(
              future: _alerts,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const SkeletonBox(height: 72, radius: 12);
                }
                final alerts = snap.data ?? const [];
                if (alerts.isEmpty) {
                  return const Text(
                    'No alerts right now.',
                    style: TextStyle(color: MiddoColors.inkSoft),
                  );
                }
                return Column(
                  children: [
                    for (final raw in alerts.take(5))
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        onTap: () => context.push('/alerts'),
                        title: Text(
                          (raw as Map)['title']?.toString() ?? 'Alert',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(raw['body']?.toString() ?? ''),
                        trailing: raw['is_unread'] == true
                            ? const KitchenStatusChip('New', positive: true)
                            : null,
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({
    required this.label,
    required this.value,
    this.onTap,
  });

  final String label;
  final String value;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          width: 156,
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: MiddoColors.white,
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: MiddoColors.creamBorder),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: MiddoColors.forest,
                    ),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: const TextStyle(
                  color: MiddoColors.inkSoft,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
