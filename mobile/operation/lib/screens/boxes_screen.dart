import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';

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

  @override
  void initState() {
    super.initState();
    _loadRequests();
  }

  @override
  void dispose() {
    _qr.dispose();
    super.dispose();
  }

  Future<void> _loadRequests() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await AppScope.of(context).boxRequests();
      if (!mounted) return;
      setState(() {
        _requests = (data['requests'] as List?) ?? const [];
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

  Future<void> _runLookup() async {
    final code = _qr.text.trim();
    if (code.isEmpty) return;
    setState(() {
      _error = null;
      _lookup = null;
    });
    try {
      final data = await AppScope.of(context).lookupBoxByQr(code);
      if (!mounted) return;
      setState(() => _lookup = Map<String, dynamic>.from(data['box'] as Map));
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
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
            FilledButton(onPressed: _runLookup, child: const Text('Find')),
          ],
        ),
        if (_lookup != null) ...[
          const SizedBox(height: 12),
          Card(
            child: ListTile(
              title: Text(_lookup!['qr_code_id']?.toString() ?? 'Box'),
              subtitle: Text(
                'Status: ${_lookup!['asset_status'] ?? '—'} · '
                'Kitchen: ${_lookup!['kitchen_id'] ?? '—'}',
              ),
              trailing: Text('#${_lookup!['id']}'),
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
          const Text('No open box requests.', style: TextStyle(color: MiddoColors.muted)),
        ..._requests.map((raw) {
          final row = Map<String, dynamic>.from(raw as Map);
          return Card(
            child: ListTile(
              title: Text(row['kitchen_name']?.toString() ?? 'Kitchen #${row['kitchen_id']}'),
              subtitle: Text('Need ${row['quantity'] ?? '?'} · ${row['status'] ?? ''}'),
              trailing: Text('#${row['id']}'),
            ),
          );
        }),
      ],
    );
  }
}
