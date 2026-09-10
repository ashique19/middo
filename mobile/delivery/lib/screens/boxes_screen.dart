import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_ui.dart';
import '../widgets/skeleton.dart';

class BoxesScreen extends StatefulWidget {
  const BoxesScreen({super.key});

  @override
  State<BoxesScreen> createState() => _BoxesScreenState();
}

class _BoxesScreenState extends State<BoxesScreen> {
  Future<Map<String, dynamic>>? _pending;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _pending ??= AppScope.of(context).pendingBoxes();
  }

  Future<void> _reload() async {
    setState(() {
      _pending = AppScope.of(context).pendingBoxes();
    });
    await _pending;
  }

  Future<void> _act(Future<void> Function() action, String ok) async {
    setState(() => _busy = true);
    try {
      await action();
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(context, ok);
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _pending,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const ListSkeleton(rows: 4);
            }
            if (snap.hasError) {
              return DeliveryError(snap.error!, onRetry: _reload);
            }
            final boxes =
                (snap.data?['boxes'] as List?) ?? const <dynamic>[];
            final requests =
                (snap.data?['requests'] as List?) ?? const <dynamic>[];

            if (boxes.isEmpty && requests.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 80),
                  DeliveryEmpty('No pending box actions.'),
                ],
              );
            }

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                if (requests.isNotEmpty) ...[
                  const Text(
                    'Bulk requests',
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 15,
                    ),
                  ),
                  const SizedBox(height: 8),
                  ...requests.map((raw) {
                    final req = (raw as Map).cast<String, dynamic>();
                    final id = (req['id'] as num?)?.toInt() ?? 0;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: DeliveryPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              req['label']?.toString() ?? 'Request #$id',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${req['box_count'] ?? 0} boxes',
                              style: const TextStyle(
                                color: MiddoColors.inkSoft,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const SizedBox(height: 10),
                            Row(
                              children: [
                                if (req['can_accept_all'] == true)
                                  Expanded(
                                    child: FilledButton(
                                      onPressed: _busy
                                          ? null
                                          : () => _act(
                                                () => AppScope.of(context)
                                                    .acceptAllBoxes(id),
                                                'Accepted all boxes.',
                                              ),
                                      child: const Text('Accept all'),
                                    ),
                                  ),
                                if (req['can_accept_all'] == true &&
                                    req['can_hand_all'] == true)
                                  const SizedBox(width: 8),
                                if (req['can_hand_all'] == true)
                                  Expanded(
                                    child: OutlinedButton(
                                      onPressed: _busy
                                          ? null
                                          : () => _act(
                                                () => AppScope.of(context)
                                                    .handAllBoxes(id),
                                                'Handed all boxes.',
                                              ),
                                      child: const Text('Hand all'),
                                    ),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                  const SizedBox(height: 8),
                ],
                const Text(
                  'Pending actions',
                  style: TextStyle(
                    fontWeight: FontWeight.w900,
                    fontSize: 15,
                  ),
                ),
                const SizedBox(height: 8),
                ...boxes.map((raw) {
                  final box = (raw as Map).cast<String, dynamic>();
                  final id = (box['id'] as num?)?.toInt() ?? 0;
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: DeliveryPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  box['qr_code_id']?.toString() ?? 'Box #$id',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                    fontSize: 15,
                                  ),
                                ),
                              ),
                              DeliveryStatusChip(
                                box['action_label']?.toString() ??
                                    box['action']?.toString() ??
                                    '',
                              ),
                            ],
                          ),
                          const SizedBox(height: 6),
                          Text(
                            box['location']?.toString() ?? '',
                            style: const TextStyle(
                              color: MiddoColors.inkSoft,
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                            ),
                          ),
                          if (box['kitchen_name'] != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              box['kitchen_name'].toString(),
                              style: const TextStyle(
                                color: MiddoColors.muted,
                                fontSize: 12,
                              ),
                            ),
                          ],
                          const SizedBox(height: 10),
                          Wrap(
                            spacing: 8,
                            runSpacing: 8,
                            children: [
                              if (box['can_accept_warehouse'] == true)
                                FilledButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _act(
                                            () => AppScope.of(context)
                                                .acceptWarehouse(id),
                                            'Accepted from warehouse.',
                                          ),
                                  child: const Text('Accept warehouse'),
                                ),
                              if (box['can_hand_to_kitchen'] == true)
                                FilledButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _act(
                                            () => AppScope.of(context)
                                                .handToKitchen(id),
                                            'Handed to kitchen.',
                                          ),
                                  child: const Text('Hand to kitchen'),
                                ),
                              if (box['can_accept_kitchen_return'] == true)
                                FilledButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _act(
                                            () => AppScope.of(context)
                                                .acceptKitchenReturn(id),
                                            'Accepted kitchen return.',
                                          ),
                                  child: const Text('Accept return'),
                                ),
                              if (box['can_hand_to_ops'] == true)
                                FilledButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _act(
                                            () => AppScope.of(context)
                                                .handToOps(id),
                                            'Handed to ops.',
                                          ),
                                  style: FilledButton.styleFrom(
                                    backgroundColor: MiddoColors.forest,
                                  ),
                                  child: const Text('Hand to ops'),
                                ),
                              if (box['can_collect_empty'] == true)
                                FilledButton(
                                  onPressed: _busy
                                      ? null
                                      : () => _act(
                                            () => AppScope.of(context)
                                                .collectEmpty(id),
                                            'Collected empty box.',
                                          ),
                                  child: const Text('Collect empty'),
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  );
                }),
              ],
            );
          },
        ),
      ),
    );
  }
}
