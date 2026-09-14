import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/empty_state.dart';
import '../widgets/kitchen_ui.dart';
import '../widgets/skeleton.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen>
    with SingleTickerProviderStateMixin {
  late final TabController _tabs;
  Future<List<dynamic>>? _activeGroups;
  Future<Map<String, dynamic>>? _history;
  final Set<String> _busy = {};

  @override
  void initState() {
    super.initState();
    _tabs = TabController(length: 2, vsync: this);
    _tabs.addListener(() {
      if (_tabs.indexIsChanging) return;
      MiddoHaptics.selection();
      if (_tabs.index == 1) {
        _history ??= AppScope.of(context).ordersHistory(period: 'last_2_months');
        setState(() {});
      }
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _activeGroups ??= AppScope.of(context).activeOrderGroups();
  }

  @override
  void dispose() {
    _tabs.dispose();
    super.dispose();
  }

  Future<void> _reloadActive() async {
    setState(() {
      _activeGroups = AppScope.of(context).activeOrderGroups();
    });
    await _activeGroups;
  }

  Future<void> _reloadHistory() async {
    setState(() {
      _history = AppScope.of(context).ordersHistory(period: 'last_2_months');
    });
    await _history;
  }

  Future<void> _run(String key, Future<void> Function() action) async {
    setState(() => _busy.add(key));
    try {
      await action();
      if (!mounted) return;
      await _reloadActive();
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } catch (e) {
      if (mounted) showKitchenSnack(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(key));
    }
  }

  Future<void> _markGroupReady(Map g) async {
    final id = g['id'] as int;
    await _run('g-$id', () async {
      final res = await AppScope.of(context).markGroupReady(id);
      if (!mounted) return;
      MiddoHaptics.success();
      showKitchenSnack(context, res['message']?.toString() ?? 'Marked ready.');
    });
  }

  Future<void> _release(Map g) async {
    final id = g['id'] as int;
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text('Release ${g['name'] ?? 'group'}?'),
        content: const Text('Returns this group to the Middo claim pool.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Release'),
          ),
        ],
      ),
    );
    if (ok != true) return;
    await _run('g-$id', () async {
      await AppScope.of(context).releaseOrderGroup(id);
      if (!mounted) return;
      showKitchenSnack(context, 'Released ${g['name'] ?? 'group'}.');
    });
  }

  Future<void> _shortage(Map g) async {
    final reason = await promptKitchenText(
      context,
      title: 'Report shortage',
      hint: 'Reason (min 3 chars)',
      confirmLabel: 'Report',
    );
    if (reason == null) return;
    final id = g['id'] as int;
    await _run('g-$id', () async {
      await AppScope.of(context).reportShortage(id, reason: reason);
      if (!mounted) return;
      showKitchenSnack(context, 'Shortage reported.');
    });
  }

  Future<void> _markOrderReady(Map order) async {
    final id = order['id'] as int;
    await _run('o-$id', () async {
      final res = await AppScope.of(context).markOrderReady(id);
      if (!mounted) return;
      MiddoHaptics.success();
      showKitchenSnack(context, res['message']?.toString() ?? 'Marked ready.');
    });
  }

  @override
  Widget build(BuildContext context) {
    // Nested under ShellScaffold (which owns the page header) — tabs only.
    return Column(
      children: [
        Material(
          color: MiddoColors.cream,
          child: TabBar(
            controller: _tabs,
            labelColor: MiddoColors.forest,
            unselectedLabelColor: MiddoColors.inkSoft,
            indicatorColor: MiddoColors.forest,
            tabs: const [
              Tab(text: 'Active'),
              Tab(text: 'History'),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            controller: _tabs,
            children: [
              _ActiveOrdersTab(
                future: _activeGroups,
                busy: _busy,
                onReload: _reloadActive,
                onMarkGroupReady: _markGroupReady,
                onRelease: _release,
                onShortage: _shortage,
                onMarkOrderReady: _markOrderReady,
              ),
              _HistoryOrdersTab(
                future: _history,
                onReload: _reloadHistory,
                onEnsureLoaded: () {
                  if (_history != null) return;
                  setState(() {
                    _history = AppScope.of(context)
                        .ordersHistory(period: 'last_2_months');
                  });
                },
              ),
            ],
          ),
        ),
      ],
    );
  }
}

class _ActiveOrdersTab extends StatelessWidget {
  const _ActiveOrdersTab({
    required this.future,
    required this.busy,
    required this.onReload,
    required this.onMarkGroupReady,
    required this.onRelease,
    required this.onShortage,
    required this.onMarkOrderReady,
  });

  final Future<List<dynamic>>? future;
  final Set<String> busy;
  final Future<void> Function() onReload;
  final Future<void> Function(Map g) onMarkGroupReady;
  final Future<void> Function(Map g) onRelease;
  final Future<void> Function(Map g) onShortage;
  final Future<void> Function(Map order) onMarkOrderReady;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onReload,
      child: FutureBuilder<List<dynamic>>(
        future: future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const ListSkeleton(rows: 4);
          }
          if (snap.hasError) {
            return KitchenError(snap.error!, onRetry: onReload);
          }
          final groups = snap.data ?? const [];
          if (groups.isEmpty) {
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                SizedBox(
                  height: MediaQuery.sizeOf(context).height * 0.5,
                  child: MiddoEmptyState(
                    icon: Icons.receipt_long_outlined,
                    title: 'No active order groups',
                    message:
                        'Accepted Middo groups with open orders appear here.',
                    actionLabel: 'Claim groups',
                    onAction: () => context.go('/groups'),
                  ),
                ),
              ],
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            itemCount: groups.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, i) {
              final g = groups[i] as Map;
              final gid = g['id'] as int? ?? 0;
              final orders = (g['orders'] as List?) ?? const [];
              final groupBusy = busy.contains('g-$gid');
              return KitchenPanel(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${g['name'] ?? 'Group'} · ${g['menu_name'] ?? ''}',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Qty ${g['total_quantity'] ?? '—'} · ${orders.length} order(s)'
                      '${g['date_label'] != null ? ' · ${g['date_label']}' : ''}',
                      style: const TextStyle(color: MiddoColors.inkSoft),
                    ),
                    const SizedBox(height: 10),
                    Wrap(
                      spacing: 8,
                      runSpacing: 8,
                      children: [
                        if (g['can_mark_group_ready'] == true)
                          FilledButton(
                            onPressed: groupBusy
                                ? null
                                : () => onMarkGroupReady(g),
                            child: Text(groupBusy ? '…' : 'Mark group ready'),
                          ),
                        if (g['can_release'] == true)
                          OutlinedButton(
                            onPressed: groupBusy ? null : () => onRelease(g),
                            child: const Text('Release'),
                          ),
                        if (g['can_report_shortage'] == true)
                          OutlinedButton(
                            onPressed: groupBusy ? null : () => onShortage(g),
                            child: const Text('Shortage'),
                          ),
                      ],
                    ),
                    const Divider(height: 20),
                    for (final raw in orders)
                      _OrderRow(
                        order: raw as Map,
                        busy: busy.contains('o-${raw['id']}'),
                        onReady: () => onMarkOrderReady(raw),
                        onOpen: () => context.push('/orders/${raw['id']}'),
                        onDispatch: () =>
                            context.push('/orders/${raw['id']}/dispatch'),
                      ),
                  ],
                ),
              );
            },
          );
        },
      ),
    );
  }
}

class _HistoryOrdersTab extends StatefulWidget {
  const _HistoryOrdersTab({
    required this.future,
    required this.onReload,
    required this.onEnsureLoaded,
  });

  final Future<Map<String, dynamic>>? future;
  final Future<void> Function() onReload;
  final VoidCallback onEnsureLoaded;

  @override
  State<_HistoryOrdersTab> createState() => _HistoryOrdersTabState();
}

class _HistoryOrdersTabState extends State<_HistoryOrdersTab> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      widget.onEnsureLoaded();
    });
  }

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: widget.onReload,
      child: FutureBuilder<Map<String, dynamic>>(
        future: widget.future,
        builder: (context, snap) {
          if (widget.future == null ||
              snap.connectionState != ConnectionState.done) {
            return const ListSkeleton(rows: 5);
          }
          if (snap.hasError) {
            return KitchenError(snap.error!, onRetry: widget.onReload);
          }
          final groups = (snap.data?['groups'] as List?) ?? const [];
          final label = snap.data?['label']?.toString() ?? 'Past 2 months';
          if (groups.isEmpty) {
            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: [
                SizedBox(
                  height: MediaQuery.sizeOf(context).height * 0.5,
                  child: MiddoEmptyState(
                    icon: Icons.history,
                    title: 'No history yet',
                    message:
                        'Order groups your kitchen accepted in the past 2 months show here.',
                  ),
                ),
              ],
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
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
                        for (final raw in orders.take(8))
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
    );
  }
}

class _OrderRow extends StatelessWidget {
  const _OrderRow({
    required this.order,
    required this.busy,
    required this.onReady,
    required this.onOpen,
    required this.onDispatch,
  });

  final Map order;
  final bool busy;
  final VoidCallback onReady;
  final VoidCallback onOpen;
  final VoidCallback onDispatch;

  @override
  Widget build(BuildContext context) {
    final canReady = order['can_mark_ready'] == true;
    final canDispatch = order['can_dispatch'] == true;
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InkWell(
            onTap: onOpen,
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    '#${order['id']} · ${order['order_status'] ?? ''} · x${order['quantity'] ?? ''}',
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                ),
                const Icon(Icons.chevron_right, size: 18),
              ],
            ),
          ),
          Text(
            '${order['area_name'] ?? '—'}'
            '${order['rider_name'] != null ? ' · ${order['rider_name']}' : ''}',
            style: const TextStyle(
              color: MiddoColors.inkSoft,
              fontSize: 12,
            ),
          ),
          if (canReady || canDispatch) ...[
            const SizedBox(height: 6),
            Wrap(
              spacing: 8,
              children: [
                if (canReady)
                  FilledButton.tonal(
                    onPressed: busy ? null : onReady,
                    child: Text(busy ? '…' : 'Ready'),
                  ),
                if (canDispatch)
                  FilledButton(
                    onPressed: onDispatch,
                    child: const Text('Dispatch'),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
