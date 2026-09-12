import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/pickers.dart';

class SlaScreen extends StatefulWidget {
  const SlaScreen({super.key});

  @override
  State<SlaScreen> createState() => _SlaScreenState();
}

class _SlaScreenState extends State<SlaScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _sla;

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
      final data = await AppScope.of(context).slaBoard();
      if (!mounted) return;
      setState(() {
        _sla = data;
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

  Future<void> _assign(int groupId) async {
    final kitchenId = await pickKitchenId(context);
    if (kitchenId == null || !mounted) return;
    try {
      final res = await AppScope.of(context).assignKitchen(
        groupId: groupId,
        kitchenId: kitchenId,
      );
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Kitchen assigned.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Dispatch SLA')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      FilledButton(
                        onPressed: _load,
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(
                        'Unassigned closed groups',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: 8),
                      ...(((_sla?['unassigned_groups'] as List?) ?? const [])
                          .map((raw) {
                        final row = Map<String, dynamic>.from(raw as Map);
                        final id = row['id'] as int? ?? 0;
                        return Card(
                          child: ListTile(
                            title: Text(
                              row['name']?.toString() ?? 'Group #$id',
                            ),
                            subtitle: Text(
                              '${row['menu'] ?? ''} · '
                              '${row['delivery_date'] ?? ''} · '
                              'qty ${row['qty'] ?? row['quantity'] ?? '?'}',
                            ),
                            trailing: FilledButton(
                              onPressed: id == 0 ? null : () => _assign(id),
                              child: const Text('Assign'),
                            ),
                          ),
                        );
                      })),
                      const SizedBox(height: 16),
                      Text(
                        'Late to pack',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: 8),
                      if (((_sla?['late_to_pack'] as List?) ?? const []).isEmpty)
                        const Text(
                          'None late.',
                          style: TextStyle(color: MiddoColors.muted),
                        ),
                      ...(((_sla?['late_to_pack'] as List?) ?? const [])
                          .map((raw) {
                        final row = Map<String, dynamic>.from(raw as Map);
                        return Card(
                          child: ListTile(
                            title: Text(
                              row['menu']?.toString() ??
                                  'Order #${row['id']}',
                            ),
                            subtitle: Text(
                              '${row['kitchen'] ?? '—'} · '
                              '${row['deadline_label'] ?? row['minutes_late'] ?? ''}',
                            ),
                          ),
                        );
                      })),
                    ],
                  ),
                ),
    );
  }
}
