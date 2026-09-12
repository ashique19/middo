import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/pickers.dart';

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

  Future<void> _assign(int orderId, {bool reassign = false}) async {
    final riderId = await pickRiderId(context);
    if (riderId == null || !mounted) return;
    String? reason;
    if (reassign) {
      reason = await promptText(context, title: 'Reassign reason');
      if (!mounted) return;
    }
    try {
      final res = reassign
          ? await AppScope.of(context).reassignLunchRider(
              orderId: orderId,
              riderId: riderId,
              reason: reason,
            )
          : await AppScope.of(context).assignLunchRider(
              orderId: orderId,
              riderId: riderId,
            );
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Rider updated.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Future<void> _createCustomRun() async {
    final from = TextEditingController();
    final to = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Custom run'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: from,
              decoration: const InputDecoration(labelText: 'From'),
            ),
            TextField(
              controller: to,
              decoration: const InputDecoration(labelText: 'To'),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Next'),
          ),
        ],
      ),
    );
    final fromLabel = from.text.trim();
    final toLabel = to.text.trim();
    from.dispose();
    to.dispose();
    if (ok != true || fromLabel.isEmpty || toLabel.isEmpty || !mounted) {
      return;
    }

    final riderId = await pickRiderId(context);
    if (riderId == null || !mounted) return;
    try {
      final res = await AppScope.of(context).createCustomRun(
        fromLabel: fromLabel,
        toLabel: toLabel,
        riderId: riderId,
      );
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Custom run created.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Future<void> _cancelRun(int id) async {
    try {
      final res = await AppScope.of(context).cancelCustomRun(id);
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Cancelled.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
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
    final onTheWay = (_board?['on_the_way'] as List?) ?? const [];
    final customRuns = (_board?['custom_runs'] as List?) ?? const [];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              ...counts.entries.map(
                (e) => Chip(
                  label: Text('${e.key}: ${e.value}'),
                  backgroundColor: MiddoColors.orange.withValues(alpha: 0.12),
                ),
              ),
              ActionChip(
                avatar: const Icon(Icons.add, size: 18),
                label: const Text('Custom run'),
                onPressed: _createCustomRun,
              ),
            ],
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
            const Text(
              'Queue clear.',
              style: TextStyle(color: MiddoColors.muted),
            ),
          ...awaiting.map((raw) {
            final row = Map<String, dynamic>.from(raw as Map);
            final id = row['id'] as int? ?? 0;
            return Card(
              child: ListTile(
                title: Text(row['menu']?.toString() ?? 'Order #$id'),
                subtitle: Text(
                  '${row['kitchen'] ?? '—'} · ${row['area'] ?? '—'} · '
                  '${row['delivery_date'] ?? ''}',
                ),
                trailing: FilledButton(
                  onPressed: id == 0 ? null : () => _assign(id),
                  child: const Text('Assign'),
                ),
              ),
            );
          }),
          const SizedBox(height: 16),
          Text(
            'On the way',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
          if (onTheWay.isEmpty)
            const Text(
              'None active.',
              style: TextStyle(color: MiddoColors.muted),
            ),
          ...onTheWay.map((raw) {
            final row = Map<String, dynamic>.from(raw as Map);
            final id = row['id'] as int? ?? 0;
            return Card(
              child: ListTile(
                title: Text(row['menu']?.toString() ?? 'Order #$id'),
                subtitle: Text(
                  'Rider: ${row['rider'] ?? row['rider_name'] ?? '—'}',
                ),
                trailing: TextButton(
                  onPressed:
                      id == 0 ? null : () => _assign(id, reassign: true),
                  child: const Text('Reassign'),
                ),
              ),
            );
          }),
          const SizedBox(height: 16),
          Text(
            'Custom runs',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
          if (customRuns.isEmpty)
            const Text(
              'No custom runs.',
              style: TextStyle(color: MiddoColors.muted),
            ),
          ...customRuns.map((raw) {
            final row = Map<String, dynamic>.from(raw as Map);
            final id = row['id'] as int? ?? 0;
            return Card(
              child: ListTile(
                title: Text(row['label']?.toString() ?? 'Run #$id'),
                subtitle: Text(
                  '${row['status'] ?? ''} · ${row['rider'] ?? ''}',
                ),
                trailing: TextButton(
                  onPressed: id == 0 ? null : () => _cancelRun(id),
                  child: const Text('Cancel'),
                ),
              ),
            );
          }),
        ],
      ),
    );
  }
}
