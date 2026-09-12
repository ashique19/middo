import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/pickers.dart';

class BoxesScreen extends StatefulWidget {
  const BoxesScreen({super.key});

  @override
  State<BoxesScreen> createState() => _BoxesScreenState();
}

class _BoxesScreenState extends State<BoxesScreen> {
  final _qr = TextEditingController();
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _lookup;
  List<dynamic> _requests = const [];
  List<dynamic> _warehouseBoxes = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _qr.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final scope = AppScope.of(context);
      final results = await Future.wait([
        scope.boxRequests(),
        scope.boxes(custody: 'warehouse'),
      ]);
      if (!mounted) return;
      setState(() {
        _requests = (results[0]['requests'] as List?) ?? const [];
        _warehouseBoxes = (results[1]['boxes'] as List?) ?? const [];
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

  Future<void> _runLookup([String? code]) async {
    final qr = (code ?? _qr.text).trim();
    if (qr.isEmpty) return;
    _qr.text = qr;
    setState(() {
      _error = null;
      _lookup = null;
    });
    try {
      final data = await AppScope.of(context).lookupBoxByQr(qr);
      if (!mounted) return;
      setState(() => _lookup = Map<String, dynamic>.from(data['box'] as Map));
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    }
  }

  Future<void> _scan() async {
    final code = await context.push<String>('/qr-scan');
    if (code != null && code.isNotEmpty) {
      await _runLookup(code);
    }
  }

  Future<void> _assignRequest(Map<String, dynamic> request) async {
    final requestId = request['id'] as int? ?? 0;
    if (requestId == 0) return;
    final selected = <int>{};
    final qty = request['quantity'] as int? ?? 1;

    final boxIds = await showDialog<List<int>>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setLocal) {
            return AlertDialog(
              title: Text('Assign boxes (need $qty)'),
              content: SizedBox(
                width: double.maxFinite,
                height: 320,
                child: _warehouseBoxes.isEmpty
                    ? const Text('No warehouse boxes available.')
                    : ListView(
                        children: _warehouseBoxes.map((raw) {
                          final box = Map<String, dynamic>.from(raw as Map);
                          final id = box['id'] as int? ?? 0;
                          return CheckboxListTile(
                            value: selected.contains(id),
                            title: Text(
                              box['qr_code_id']?.toString() ?? 'Box #$id',
                            ),
                            subtitle:
                                Text(box['asset_status']?.toString() ?? ''),
                            onChanged: (v) {
                              setLocal(() {
                                if (v == true) {
                                  selected.add(id);
                                } else {
                                  selected.remove(id);
                                }
                              });
                            },
                          );
                        }).toList(),
                      ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: selected.isEmpty
                      ? null
                      : () => Navigator.pop(ctx, selected.toList()),
                  child: const Text('Next'),
                ),
              ],
            );
          },
        );
      },
    );

    if (boxIds == null || boxIds.isEmpty || !mounted) return;
    final riderId = await pickRiderId(context);
    if (riderId == null || !mounted) return;

    try {
      final res = await AppScope.of(context).assignBoxRequest(
        requestId: requestId,
        boxIds: boxIds,
        riderId: riderId,
      );
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Assigned.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Future<void> _ackReturn() async {
    final id = _lookup?['id'] as int?;
    if (id == null) return;
    try {
      final res = await AppScope.of(context).ackBoxReturn(id);
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Return acknowledged.');
      await _runLookup(_lookup!['qr_code_id']?.toString());
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Future<void> _reassignLookup() async {
    final id = _lookup?['id'] as int?;
    if (id == null) return;
    final riderId = await pickRiderId(context);
    if (riderId == null || !mounted) return;
    try {
      final res = await AppScope.of(context).reassignBox(
        boxId: id,
        kind: 'kitchen_to_ops',
        riderId: riderId,
      );
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Reassigned.');
      await _runLookup(_lookup!['qr_code_id']?.toString());
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(
          'QR lookup',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _qr,
                decoration: const InputDecoration(
                  labelText: 'Scan / paste QR (e.g. MB-000001)',
                ),
                onSubmitted: (_) => _runLookup(),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filledTonal(
              onPressed: _scan,
              icon: const Icon(Icons.qr_code_scanner),
              tooltip: 'Scan',
            ),
            const SizedBox(width: 4),
            FilledButton(onPressed: _runLookup, child: const Text('Find')),
          ],
        ),
        if (_lookup != null) ...[
          const SizedBox(height: 12),
          Card(
            child: Column(
              children: [
                ListTile(
                  title: Text(_lookup!['qr_code_id']?.toString() ?? 'Box'),
                  subtitle: Text(
                    'Status: ${_lookup!['asset_status'] ?? '—'} · '
                    'Kitchen: ${_lookup!['kitchen_id'] ?? '—'}',
                  ),
                  trailing: Text('#${_lookup!['id']}'),
                ),
                OverflowBar(
                  children: [
                    TextButton(
                      onPressed: _reassignLookup,
                      child: const Text('Reassign rider'),
                    ),
                    FilledButton(
                      onPressed: _ackReturn,
                      child: const Text('Ack return'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
        if (_error != null) ...[
          const SizedBox(height: 8),
          Text(_error!, style: const TextStyle(color: Colors.red)),
        ],
        const SizedBox(height: 20),
        Text(
          'Open kitchen requests',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
        ),
        const SizedBox(height: 8),
        if (_loading) const Center(child: CircularProgressIndicator()),
        if (!_loading && _requests.isEmpty)
          const Text(
            'No open box requests.',
            style: TextStyle(color: MiddoColors.muted),
          ),
        ..._requests.map((raw) {
          final row = Map<String, dynamic>.from(raw as Map);
          return Card(
            child: ListTile(
              title: Text(
                row['kitchen_name']?.toString() ??
                    'Kitchen #${row['kitchen_id']}',
              ),
              subtitle: Text(
                'Need ${row['quantity'] ?? '?'} · ${row['status'] ?? ''}',
              ),
              trailing: FilledButton(
                onPressed: () => _assignRequest(row),
                child: const Text('Assign'),
              ),
            ),
          );
        }),
      ],
    );
  }
}
