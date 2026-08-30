import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';

class OrdersScreen extends StatefulWidget {
  const OrdersScreen({super.key});

  @override
  State<OrdersScreen> createState() => _OrdersScreenState();
}

class _OrdersScreenState extends State<OrdersScreen> {
  Future<List<dynamic>>? _groups;
  bool _loaded = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      _groups = AppScope.of(context).activeOrderGroups();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Orders')),
      body: RefreshIndicator(
        onRefresh: () async {
          setState(() {
            _groups = AppScope.of(context).activeOrderGroups();
          });
          await _groups;
        },
        child: FutureBuilder<List<dynamic>>(
          future: _groups,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return ListView(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text('Error: ${snap.error}'),
                  ),
                ],
              );
            }
            final groups = snap.data ?? const [];
            if (groups.isEmpty) {
              return ListView(
                children: const [
                  Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('No active orders.'),
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: groups.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final g = groups[i] as Map;
                final orders = (g['orders'] as List?) ?? const [];
                return Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: MiddoColors.white,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: MiddoColors.creamBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        '${g['name'] ?? 'Group'} · ${g['menu_name'] ?? ''}',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Qty ${g['total_quantity'] ?? '—'} · ${orders.length} order(s)',
                        style: const TextStyle(color: MiddoColors.inkSoft),
                      ),
                      for (final raw in orders.take(4))
                        Padding(
                          padding: const EdgeInsets.only(top: 6),
                          child: Text(
                            '#${(raw as Map)['id']} · ${raw['order_status'] ?? ''} · x${raw['quantity'] ?? ''}',
                            style: const TextStyle(fontSize: 13),
                          ),
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
