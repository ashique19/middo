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
    final reason = await promptText(
      context,
      title: 'Return to packed — reason',
      label: 'Why is the rider being released?',
    );
    if (!mounted) return;
    try {
      final res = await AppScope.of(context).releaseRider(
        widget.orderId,
        reason: reason,
      );
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Rider released; order back to packed.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Future<void> _ungroup() async {
    try {
      final res = await AppScope.of(context).ungroupOrder(widget.orderId);
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Removed from group.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Widget _partyTile({
    required String role,
    required String title,
    String? subtitle,
    dynamic id,
    IconData icon = Icons.person,
    String? photoUrl,
  }) {
    final hasId = id is int && id > 0;
    final photo = photoUrl?.trim();
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: (photo != null && photo.isNotEmpty)
          ? CircleAvatar(
              backgroundImage: NetworkImage(photo),
              onBackgroundImageError: (_, __) {},
            )
          : Icon(icon, color: hasId ? MiddoColors.orange : MiddoColors.muted),
      title: Text('$role · $title'),
      subtitle: subtitle == null || subtitle.isEmpty ? null : Text(subtitle),
      trailing: hasId ? const Icon(Icons.chevron_right) : null,
      onTap: hasId ? () => context.push('/parties/$id') : null,
    );
  }

  @override
  Widget build(BuildContext context) {
    final order = _order;
    final canRelease = order?['can_release_rider'] == true;
    final groupId = order?['group_id'];
    final groupName = order?['group_name']?.toString();

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
                      order?['menu_name']?.toString() ??
                          order?['menu']?.toString() ??
                          'Order',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text('Status: ${order?['order_status'] ?? '—'}'),
                    Text('Area: ${order?['area_name'] ?? '—'}'),
                    if (order?['delivery_date'] != null)
                      Text(
                        'Delivery: ${order?['delivery_date']}'
                        '${order?['delivery_time'] != null ? ' · ${order?['delivery_time']}' : ''}',
                      ),
                    if (order?['address'] != null) Text('Address: ${order?['address']}'),
                    const SizedBox(height: 12),
                    const Text('Group', style: TextStyle(fontWeight: FontWeight.w800)),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: const Icon(Icons.layers_outlined),
                      title: Text(groupName?.isNotEmpty == true ? groupName! : 'Ungrouped'),
                      subtitle: groupId is int ? Text('Group #$groupId') : const Text('Not in a group'),
                      trailing: groupId is int ? const Icon(Icons.chevron_right) : null,
                      onTap: groupId is int
                          ? () => context.push('/order-groups/$groupId')
                          : null,
                    ),
                    if (groupId is int)
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton(
                          onPressed: _ungroup,
                          child: const Text('Remove from group'),
                        ),
                      ),
                    const Divider(height: 28),
                    const Text('Parties', style: TextStyle(fontWeight: FontWeight.w800)),
                    _partyTile(
                      role: 'Customer',
                      title: order?['customer_name']?.toString() ?? '—',
                      subtitle: order?['customer_mobile']?.toString(),
                      id: order?['customer_id'],
                      icon: Icons.business,
                    ),
                    _partyTile(
                      role: 'Rider',
                      title: order?['rider_name']?.toString() ?? 'Unassigned',
                      subtitle: order?['rider_mobile']?.toString(),
                      id: order?['rider_id'],
                      icon: Icons.delivery_dining,
                    ),
                    _partyTile(
                      role: 'Kitchen',
                      title: order?['kitchen_name']?.toString() ?? 'Unassigned',
                      subtitle: order?['kitchen_mobile']?.toString(),
                      id: order?['kitchen_id'],
                      icon: Icons.soup_kitchen,
                      photoUrl: order?['kitchen_profile_photo_url']?.toString(),
                    ),
                    const SizedBox(height: 20),
                    if (canRelease) ...[
                      Text(
                        order?['release_rider_hint']?.toString() ??
                            'Unassigns the rider, returns Middo boxes to the kitchen, voids the open delivery share, and sets the order back to packed.',
                        style: const TextStyle(color: MiddoColors.muted, fontSize: 13),
                      ),
                      const SizedBox(height: 8),
                      FilledButton(
                        onPressed: _releaseRider,
                        child: const Text('Return to packed (release rider)'),
                      ),
                      const SizedBox(height: 8),
                    ],
                    OutlinedButton(
                      onPressed: _forceCancel,
                      child: const Text('Force cancel'),
                    ),
                  ],
                ),
    );
  }
}
