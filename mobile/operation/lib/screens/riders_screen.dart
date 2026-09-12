import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';

class RidersScreen extends StatefulWidget {
  const RidersScreen({super.key});

  @override
  State<RidersScreen> createState() => _RidersScreenState();
}

class _RidersScreenState extends State<RidersScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _board;

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
      final data = await AppScope.of(context).ridersBoard();
      if (!mounted) return;
      setState(() {
        _board = data;
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
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(_error!),
            FilledButton(onPressed: _load, child: const Text('Retry')),
          ],
        ),
      );
    }

    final counts = Map<String, dynamic>.from((_board?['counts'] as Map?) ?? {});
    final awaiting = (_board?['awaiting_accept'] as List?) ?? const [];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: counts.entries
                .map(
                  (e) => Chip(
                    label: Text('${e.key}: ${e.value}'),
                    backgroundColor: MiddoColors.orange.withValues(alpha: 0.12),
                  ),
                )
                .toList(),
          ),
          const SizedBox(height: 16),
          Text(
            'Awaiting rider accept',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
          if (awaiting.isEmpty)
            const Text('Queue clear.', style: TextStyle(color: MiddoColors.muted)),
          ...awaiting.map((raw) {
            final row = Map<String, dynamic>.from(raw as Map);
            return Card(
              child: ListTile(
                title: Text(row['menu']?.toString() ?? 'Order #${row['id']}'),
                subtitle: Text(
                  '${row['kitchen'] ?? '—'} · ${row['area'] ?? '—'} · ${row['delivery_date'] ?? ''}',
                ),
                trailing: Text('#${row['id']}'),
              ),
            );
          }),
        ],
      ),
    );
  }
}
