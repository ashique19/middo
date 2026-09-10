import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/deliver_otp_flow.dart';
import '../widgets/delivery_ui.dart';
import '../widgets/skeleton.dart';

class RunsScreen extends StatefulWidget {
  const RunsScreen({super.key});

  @override
  State<RunsScreen> createState() => _RunsScreenState();
}

class _RunsScreenState extends State<RunsScreen> {
  Future<List<dynamic>>? _runs;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _runs ??= AppScope.of(context).runs();
  }

  Future<void> _reload() async {
    setState(() {
      _runs = AppScope.of(context).runs();
    });
    await _runs;
  }

  Future<void> _pickup(int id) async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).pickupRun(id);
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(context, res['message']?.toString() ?? 'Picked up.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _deliver(int id) async {
    await runDeliverFlow(
      context,
      runId: id,
      onBusy: () {
        if (mounted) setState(() => _busy = true);
      },
      onIdle: () {
        if (mounted) setState(() => _busy = false);
      },
      onSuccess: _reload,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<List<dynamic>>(
          future: _runs,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const ListSkeleton(rows: 3);
            }
            if (snap.hasError) {
              return DeliveryError(snap.error!, onRetry: _reload);
            }
            final runs = snap.data ?? const [];
            if (runs.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 80),
                  DeliveryEmpty('No active runs. Pull to refresh.'),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: runs.length,
              separatorBuilder: (_, __) => const SizedBox(height: 10),
              itemBuilder: (context, i) {
                final run = (runs[i] as Map).cast<String, dynamic>();
                final id = (run['id'] as num?)?.toInt() ?? 0;
                return DeliveryPanel(
                  onTap: () => context.push('/runs/$id'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              run['label']?.toString() ?? 'Run #$id',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                                fontSize: 15,
                              ),
                            ),
                          ),
                          DeliveryStatusChip(
                            run['status']?.toString() ?? '',
                            positive: run['can_deliver'] == true,
                          ),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '${run['menu_name'] ?? ''} · qty ${run['quantity'] ?? ''}',
                        style: const TextStyle(
                          color: MiddoColors.inkSoft,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
                      ),
                      if (run['address'] != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          run['address'].toString(),
                          style: const TextStyle(
                            color: MiddoColors.muted,
                            fontSize: 12,
                          ),
                        ),
                      ],
                      const SizedBox(height: 12),
                      Row(
                        children: [
                          if (run['can_pickup'] == true)
                            Expanded(
                              child: FilledButton(
                                onPressed:
                                    _busy ? null : () => _pickup(id),
                                child: const Text('Pickup'),
                              ),
                            ),
                          if (run['can_pickup'] == true &&
                              run['can_deliver'] == true)
                            const SizedBox(width: 8),
                          if (run['can_deliver'] == true)
                            Expanded(
                              child: FilledButton(
                                onPressed:
                                    _busy ? null : () => _deliver(id),
                                style: FilledButton.styleFrom(
                                  backgroundColor: MiddoColors.forest,
                                ),
                                child: const Text('Deliver'),
                              ),
                            ),
                          if (run['can_pickup'] != true &&
                              run['can_deliver'] != true)
                            TextButton(
                              onPressed: () => context.push('/runs/$id'),
                              child: const Text('Details'),
                            ),
                        ],
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
