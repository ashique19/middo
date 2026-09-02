import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_ui.dart';

class GroupsScreen extends StatefulWidget {
  const GroupsScreen({super.key});

  @override
  State<GroupsScreen> createState() => _GroupsScreenState();
}

class _GroupsScreenState extends State<GroupsScreen> {
  Future<Map<String, dynamic>>? _payload;
  final Set<int> _busyIds = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _payload ??= AppScope.of(context).orderGroupsPayload();
  }

  Future<void> _reload() async {
    setState(() {
      _payload = AppScope.of(context).orderGroupsPayload();
    });
    await _payload;
  }

  Future<void> _run(int id, Future<void> Function() action) async {
    setState(() => _busyIds.add(id));
    try {
      await action();
      if (!mounted) return;
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } catch (e) {
      if (mounted) showKitchenSnack(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busyIds.remove(id));
    }
  }

  Future<void> _accept(Map g) async {
    final id = g['id'] as int;
    await _run(id, () async {
      final res = await AppScope.of(context).acceptOrderGroup(id);
      if (!mounted) return;
      showKitchenSnack(
        context,
        res['message']?.toString() ?? 'Accepted.',
      );
      context.go('/orders');
    });
  }

  Future<void> _decline(Map g) async {
    final reason = await promptKitchenText(
      context,
      title: 'Decline ${g['name'] ?? 'group'}',
      hint: 'Reason (min 3 chars)',
      confirmLabel: 'Decline',
    );
    if (reason == null) return;
    final id = g['id'] as int;
    await _run(id, () async {
      await AppScope.of(context).declineOrderGroup(id, reason: reason);
      if (!mounted) return;
      showKitchenSnack(context, 'Declined ${g['name'] ?? 'group'}.');
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _payload,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return KitchenError(snap.error!, onRetry: _reload);
            }
            final groups = (snap.data?['groups'] as List?) ?? const [];
            final capacity =
                (snap.data?['capacity'] as Map?)?.cast<String, dynamic>() ??
                    const {};
            if (groups.isEmpty) {
              return ListView(
                children: [
                  if (capacity.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 16, 16, 0),
                      child: Text(
                        'Slots left ${capacity['remaining_slots'] ?? '—'} · Boxes ${capacity['sendable_boxes'] ?? '—'}',
                        style: const TextStyle(color: MiddoColors.inkSoft),
                      ),
                    ),
                  const KitchenEmpty('No groups in the claim pool.'),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: groups.length + 1,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                if (i == 0) {
                  return Text(
                    'Slots left ${capacity['remaining_slots'] ?? '—'} · Boxes ${capacity['sendable_boxes'] ?? '—'}',
                    style: const TextStyle(
                      color: MiddoColors.inkSoft,
                      fontWeight: FontWeight.w600,
                    ),
                  );
                }
                final g = groups[i - 1] as Map;
                final id = g['id'] as int? ?? 0;
                final canAccept = g['can_accept'] == true;
                final needsBoxes = g['needs_more_boxes'] == true;
                final busy = _busyIds.contains(id);
                final window = (g['accept_window'] as Map?)?['label'];
                return KitchenPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        g['name']?.toString() ?? 'Group',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${g['menu_name'] ?? ''} · qty ${g['total_quantity'] ?? '—'}'
                        '${g['date_label'] != null ? ' · ${g['date_label']}' : ''}',
                        style: const TextStyle(color: MiddoColors.inkSoft),
                      ),
                      if (window != null) ...[
                        const SizedBox(height: 6),
                        KitchenStatusChip(
                          window.toString(),
                          positive: canAccept,
                        ),
                      ],
                      if (needsBoxes) ...[
                        const SizedBox(height: 6),
                        const Text(
                          'Need more boxes before you can accept.',
                          style: TextStyle(
                            color: MiddoColors.orange,
                            fontWeight: FontWeight.w600,
                            fontSize: 12,
                          ),
                        ),
                      ],
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          Expanded(
                            child: FilledButton(
                              onPressed:
                                  (!canAccept || busy) ? null : () => _accept(g),
                              child: Text(busy ? '…' : 'Accept'),
                            ),
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: OutlinedButton(
                              onPressed: busy ? null : () => _decline(g),
                              child: const Text('Decline'),
                            ),
                          ),
                        ],
                      ),
                      if (needsBoxes)
                        TextButton(
                          onPressed: () => context.push('/boxes'),
                          child: const Text('Request / manage boxes'),
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
