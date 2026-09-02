import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_mobile_header.dart';
import '../widgets/kitchen_ui.dart';

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  Future<Map<String, dynamic>>? _future;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).showOrder(widget.orderId);
  }

  Future<void> _reload() async {
    setState(() {
      _future = AppScope.of(context).showOrder(widget.orderId);
    });
    await _future;
  }

  Future<void> _markReady() async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).markOrderReady(widget.orderId);
      if (!mounted) return;
      showKitchenSnack(context, res['message']?.toString() ?? 'Marked ready.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: KitchenMobileHeader(
        title: 'Order #${widget.orderId}',
        showBack: true,
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return KitchenError(snap.error!, onRetry: _reload);
            }
            final order =
                (snap.data?['order'] as Map?)?.cast<String, dynamic>() ??
                    const {};
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                KitchenPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        order['menu_name']?.toString() ?? 'Order',
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 18,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text('Status: ${order['order_status'] ?? '—'}'),
                      Text('Qty: ${order['quantity'] ?? '—'}'),
                      Text('Area: ${order['area_name'] ?? '—'}'),
                      Text('Date: ${order['delivery_date'] ?? '—'}'),
                      if (order['rider_name'] != null)
                        Text('Rider: ${order['rider_name']}'),
                      if (order['payment_method_label'] != null)
                        Text(
                          'Payment: ${order['payment_method_label']}',
                          style: const TextStyle(color: MiddoColors.inkSoft),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                if (order['can_mark_ready'] == true)
                  FilledButton(
                    onPressed: _busy ? null : _markReady,
                    child: Text(_busy ? 'Working…' : 'Mark ready'),
                  ),
                if (order['can_dispatch'] == true) ...[
                  const SizedBox(height: 8),
                  FilledButton(
                    onPressed: () =>
                        context.push('/orders/${widget.orderId}/dispatch'),
                    child: const Text('Dispatch with boxes'),
                  ),
                ],
                if (order['awaiting_rider_claim'] == true) ...[
                  const SizedBox(height: 12),
                  const Text(
                    'Waiting for ops/rider assignment before dispatch.',
                    style: TextStyle(color: MiddoColors.inkSoft),
                  ),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}
