import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_ui.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  Future<List<dynamic>>? _groups;
  final Set<String> _busy = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _groups ??= AppScope.of(context).activeOrderGroups();
  }

  Future<void> _reload() async {
    setState(() {
      _groups = AppScope.of(context).activeOrderGroups();
    });
    await _groups;
  }

  Future<void> _run(String key, Future<void> Function() action) async {
    setState(() => _busy.add(key));
    try {
      await action();
      if (!mounted) return;
      await _reload();
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
      showKitchenSnack(context, res['message']?.toString() ?? 'Marked ready.');
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<List<dynamic>>(
          future: _groups,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return KitchenError(snap.error!, onRetry: _reload);
            }
            final groups = snap.data ?? const [];
            if (groups.isEmpty) {
              return ListView(
                children: const [KitchenEmpty('No active orders.')],
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
                final groupBusy = _busy.contains('g-$gid');
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
                              onPressed:
                                  groupBusy ? null : () => _markGroupReady(g),
                              child: Text(groupBusy ? '…' : 'Mark group ready'),
                            ),
                          if (g['can_release'] == true)
                            OutlinedButton(
                              onPressed: groupBusy ? null : () => _release(g),
                              child: const Text('Release'),
                            ),
                          if (g['can_report_shortage'] == true)
                            OutlinedButton(
                              onPressed: groupBusy ? null : () => _shortage(g),
                              child: const Text('Shortage'),
                            ),
                        ],
                      ),
                      const Divider(height: 20),
                      for (final raw in orders)
                        _OrderRow(
                          order: raw as Map,
                          busy: _busy.contains('o-${raw['id']}'),
                          onReady: () => _markOrderReady(raw),
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
