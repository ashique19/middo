import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_mobile_header.dart';
import '../widgets/delivery_ui.dart';

class RunDetailScreen extends StatefulWidget {
  const RunDetailScreen({super.key, required this.runId});

  final int runId;

  @override
  State<RunDetailScreen> createState() => _RunDetailScreenState();
}

class _RunDetailScreenState extends State<RunDetailScreen> {
  Future<Map<String, dynamic>>? _run;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _run ??= AppScope.of(context).showRun(widget.runId);
  }

  Future<void> _reload() async {
    setState(() {
      _run = AppScope.of(context).showRun(widget.runId);
    });
    await _run;
  }

  Future<void> _pickup() async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).pickupRun(widget.runId);
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

  Future<void> _deliver() async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).deliverRun(widget.runId);
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(context, res['message']?.toString() ?? 'Delivered.');
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
      appBar: DeliveryMobileHeader(
        title: 'Run #${widget.runId}',
        showBack: true,
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _run,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return DeliveryError(snap.error!, onRetry: _reload);
            }
            final run =
                (snap.data?['run'] as Map?)?.cast<String, dynamic>() ??
                    const <String, dynamic>{};
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                DeliveryPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        run['label']?.toString() ?? 'Run #${widget.runId}',
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          fontSize: 18,
                        ),
                      ),
                      const SizedBox(height: 8),
                      DeliveryStatusChip(run['status']?.toString() ?? ''),
                      const SizedBox(height: 14),
                      _kv('Kitchen', run['kitchen_name']),
                      _kv('Area', run['area_name']),
                      _kv('Receiver', run['receiver_name']),
                      _kv('Phone', run['receiver_phone']),
                      _kv('Address', run['address']),
                      _kv('Menu', run['menu_name']),
                      _kv('Quantity', run['quantity']),
                      _kv('Cash due', run['cash_due'] != null
                          ? '৳${run['cash_due']}'
                          : null),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                if (run['can_pickup'] == true)
                  FilledButton(
                    onPressed: _busy ? null : _pickup,
                    child: const Text('Confirm pickup'),
                  ),
                if (run['can_deliver'] == true) ...[
                  if (run['can_pickup'] == true) const SizedBox(height: 10),
                  FilledButton(
                    onPressed: _busy ? null : _deliver,
                    style: FilledButton.styleFrom(
                      backgroundColor: MiddoColors.forest,
                    ),
                    child: const Text('Confirm deliver'),
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }

  Widget _kv(String label, Object? value) {
    if (value == null || value.toString().isEmpty) {
      return const SizedBox.shrink();
    }
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 88,
            child: Text(
              label,
              style: const TextStyle(
                color: MiddoColors.muted,
                fontWeight: FontWeight.w700,
                fontSize: 12,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value.toString(),
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ),
        ],
      ),
    );
  }
}
