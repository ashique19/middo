import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';

class PartyDetailScreen extends StatefulWidget {
  const PartyDetailScreen({super.key, required this.partyId});

  final int partyId;

  @override
  State<PartyDetailScreen> createState() => _PartyDetailScreenState();
}

class _PartyDetailScreenState extends State<PartyDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _party;

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
      final data = await AppScope.of(context).showParty(widget.partyId);
      if (!mounted) return;
      setState(() {
        _party = Map<String, dynamic>.from(data['party'] as Map);
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
    final party = _party;
    final role = party?['role']?.toString() ?? 'party';

    return Scaffold(
      appBar: AppBar(title: Text('${role[0].toUpperCase()}${role.substring(1)} details')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    if ((party?['profile_photo_url']?.toString() ?? '').isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: CircleAvatar(
                          radius: 36,
                          backgroundImage: NetworkImage(
                            party!['profile_photo_url'].toString(),
                          ),
                        ),
                      ),
                    Text(
                      party?['name']?.toString() ?? '—',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text('Role: $role'),
                    Text('Mobile: ${party?['mobile'] ?? '—'}'),
                    if (party?['company_name'] != null)
                      Text('Company: ${party?['company_name']}'),
                    if (party?['area_name'] != null)
                      Text('Area: ${party?['area_name']}'),
                    if (party?['city_name'] != null)
                      Text('City: ${party?['city_name']}'),
                    if (party?['status'] != null)
                      Text('Status: ${party?['status']}'),
                    const SizedBox(height: 12),
                    if (party?['open_orders_count'] != null)
                      Text('Open orders: ${party?['open_orders_count']}'),
                    if (party?['active_groups_count'] != null)
                      Text('Active groups: ${party?['active_groups_count']}'),
                    if (party?['active_runs_count'] != null)
                      Text('Active runs: ${party?['active_runs_count']}'),
                  ],
                ),
    );
  }
}

class OrderGroupDetailScreen extends StatefulWidget {
  const OrderGroupDetailScreen({super.key, required this.groupId});

  final int groupId;

  @override
  State<OrderGroupDetailScreen> createState() => _OrderGroupDetailScreenState();
}

class _OrderGroupDetailScreenState extends State<OrderGroupDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _group;

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
      final data = await AppScope.of(context).showOrderGroup(widget.groupId);
      if (!mounted) return;
      setState(() {
        _group = Map<String, dynamic>.from(data['group'] as Map);
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
    final group = _group;
    final orders = (group?['orders'] as List?) ?? const [];
    final kitchenId = group?['kitchen_id'];

    return Scaffold(
      appBar: AppBar(title: Text('Group #${widget.groupId}')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(
                      group?['name']?.toString() ?? 'Group',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 8),
                    Text('Menu: ${group?['menu_name'] ?? '—'}'),
                    Text('Area: ${group?['area_name'] ?? '—'}'),
                    Text('Delivery date: ${group?['delivery_date'] ?? '—'}'),
                    Text(
                      'Orders: ${group?['orders_count'] ?? orders.length} · qty ${group?['qty'] ?? '—'}',
                    ),
                    const SizedBox(height: 8),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: (group?['kitchen_profile_photo_url']?.toString() ?? '').isNotEmpty
                          ? CircleAvatar(
                              backgroundImage: NetworkImage(
                                group!['kitchen_profile_photo_url'].toString(),
                              ),
                            )
                          : const Icon(Icons.soup_kitchen),
                      title: Text(group?['kitchen_name']?.toString() ?? 'Kitchen unassigned'),
                      subtitle: Text(group?['kitchen_mobile']?.toString() ?? ''),
                      trailing: kitchenId is int ? const Icon(Icons.chevron_right) : null,
                      onTap: kitchenId is int
                          ? () => context.push('/parties/$kitchenId')
                          : null,
                    ),
                    const Divider(height: 28),
                    const Text('Orders in group', style: TextStyle(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 8),
                    if (orders.isEmpty)
                      const Text('No orders', style: TextStyle(color: MiddoColors.muted)),
                    for (final raw in orders)
                      Builder(builder: (context) {
                        final row = (raw as Map).cast<String, dynamic>();
                        final id = row['id'];
                        return ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text('#$id · ${row['menu_name'] ?? 'Order'}'),
                          subtitle: Text(
                            [
                              row['customer_name'],
                              row['order_status'],
                            ].where((e) => e != null && e.toString().isNotEmpty).join(' · '),
                          ),
                          trailing: const Icon(Icons.chevron_right),
                          onTap: id is int ? () => context.push('/orders/$id') : null,
                        );
                      }),
                  ],
                ),
    );
  }
}
