import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/pickers.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  final _q = TextEditingController();
  bool _loading = false;
  String? _error;
  List<dynamic> _orders = const [];

  @override
  void dispose() {
    _q.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    final q = _q.text.trim();
    if (q.isEmpty) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await AppScope.of(context).searchOrders(q);
      if (!mounted) return;
      setState(() {
        _orders = (data['orders'] as List?) ?? const [];
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Order search')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _q,
                  decoration: const InputDecoration(
                    labelText: 'Order id / mobile / name',
                  ),
                  onSubmitted: (_) => _search(),
                ),
              ),
              const SizedBox(width: 8),
              FilledButton(onPressed: _search, child: const Text('Search')),
            ],
          ),
          if (_error != null) ...[
            const SizedBox(height: 8),
            Text(_error!, style: const TextStyle(color: Colors.red)),
          ],
          const SizedBox(height: 12),
          if (_loading) const Center(child: CircularProgressIndicator()),
          if (!_loading && _orders.isEmpty)
            const Text(
              'No results.',
              style: TextStyle(color: MiddoColors.muted),
            ),
          ..._orders.map((raw) {
            final row = Map<String, dynamic>.from(raw as Map);
            final id = row['id'] as int? ?? 0;
            return Card(
              child: ListTile(
                title: Text('#$id · ${row['menu_name'] ?? row['menu'] ?? ''}'),
                subtitle: Text(
                  '${row['customer_name'] ?? ''} · '
                  '${row['customer_mobile'] ?? ''} · '
                  '${row['order_status'] ?? ''}',
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: id == 0 ? null : () => context.push('/orders/$id'),
              ),
            );
          }),
        ],
      ),
    );
  }
}

class OrderDetailScreen extends StatefulWidget {
  const OrderDetailScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<OrderDetailScreen> createState() => _OrderDetailScreenState();
}

class _OrderDetailScreenState extends State<OrderDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _order;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await AppScope.of(context).showOrder(widget.orderId);
      if (!mounted) return;
      setState(() {
        _order = Map<String, dynamic>.from(data['order'] as Map);
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _forceCancel() async {
    final reason = await promptText(context, title: 'Force cancel reason');
    if (!mounted) return;
    try {
      final res = await AppScope.of(context).forceCancelOrder(
        widget.orderId,
        reason: reason,
      );
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Cancelled.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Future<void> _releaseRider() async {
    final reason = await promptText(context, title: 'Release rider reason');
    if (!mounted) return;
    try {
      final res = await AppScope.of(context).releaseRider(
        widget.orderId,
        reason: reason,
      );
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Rider released.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Order #${widget.orderId}')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(
                      _order?['menu_name']?.toString() ??
                          _order?['menu']?.toString() ??
                          'Order',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text('Status: ${_order?['order_status'] ?? '—'}'),
                    Text(
                      'Customer: ${_order?['customer_name'] ?? '—'} '
                      '(${_order?['customer_mobile'] ?? ''})',
                    ),
                    Text('Area: ${_order?['area_name'] ?? '—'}'),
                    Text(
                      'Rider: ${_order?['rider_name'] ?? _order?['rider_id'] ?? '—'}',
                    ),
                    Text(
                      'Kitchen: ${_order?['kitchen_name'] ?? _order?['kitchen_id'] ?? '—'}',
                    ),
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: _releaseRider,
                      child: const Text('Release rider'),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton(
                      onPressed: _forceCancel,
                      child: const Text('Force cancel'),
                    ),
                  ],
                ),
    );
  }
}
