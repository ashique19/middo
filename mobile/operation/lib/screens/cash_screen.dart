import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';

class CashScreen extends StatefulWidget {
  const CashScreen({super.key});

  @override
  State<CashScreen> createState() => _CashScreenState();
}

class _CashScreenState extends State<CashScreen> {
  bool _loading = true;
  String? _error;
  List<dynamic> _handovers = const [];

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
      final data = await AppScope.of(context).cashHandovers();
      if (!mounted) return;
      setState(() {
        _handovers = (data['handovers'] as List?) ?? const [];
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

  Future<void> _accept(int id) async {
    try {
      await AppScope.of(context).acceptCashHandover(id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Handover accepted into Middo cash.')),
      );
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.message)));
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

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            'Pending Middo Due',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
          if (_handovers.isEmpty)
            const Text('No pending handovers.', style: TextStyle(color: MiddoColors.muted)),
          ..._handovers.map((raw) {
            final row = Map<String, dynamic>.from(raw as Map);
            final id = row['id'] as int? ?? 0;
            final rider = row['rider'] is Map
                ? Map<String, dynamic>.from(row['rider'] as Map)
                : null;
            return Card(
              child: ListTile(
                title: Text('৳${row['amount'] ?? 0}'),
                subtitle: Text(rider?['name']?.toString() ?? 'Rider'),
                trailing: FilledButton(
                  onPressed: id == 0 ? null : () => _accept(id),
                  child: const Text('Accept'),
                ),
              ),
            );
          }),
        ],
      ),
    );
  }
}
