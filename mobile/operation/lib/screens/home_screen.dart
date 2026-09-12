import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _dashboard;

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
      final data = await AppScope.of(context).dashboard();
      if (!mounted) return;
      setState(() {
        _dashboard = data;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  void _openTile(String key) {
    switch (key) {
      case 'alerts':
        context.push('/alerts');
      case 'sla':
        context.push('/sla');
      case 'awaiting_rider':
        context.go('/riders');
      case 'box_requests':
        context.go('/boxes');
      case 'cash_handovers':
        context.go('/cash');
      case 'complaints':
        context.push('/more');
      default:
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }

    final tiles = (_dashboard?['tiles'] as List?) ?? const [];
    final today = (_dashboard?['today'] as Map?)?.cast<String, dynamic>();

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            "Today's pulse",
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          if (today != null) ...[
            const SizedBox(height: 4),
            Text(
              '${today['label'] ?? today['date'] ?? ''} · '
              '${today['orders'] ?? 0} orders · qty ${today['qty'] ?? 0}',
              style: const TextStyle(color: MiddoColors.muted),
            ),
          ],
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              ActionChip(
                avatar: const Icon(Icons.search, size: 18),
                label: const Text('Orders'),
                onPressed: () => context.push('/orders'),
              ),
              ActionChip(
                avatar: const Icon(Icons.notifications_outlined, size: 18),
                label: const Text('Alerts'),
                onPressed: () => context.push('/alerts'),
              ),
              ActionChip(
                avatar: const Icon(Icons.speed, size: 18),
                label: const Text('SLA'),
                onPressed: () => context.push('/sla'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          ...tiles.map((raw) {
            final tile = Map<String, dynamic>.from(raw as Map);
            final key = tile['key']?.toString() ?? '';
            return Card(
              margin: const EdgeInsets.only(bottom: 10),
              child: ListTile(
                onTap: key.isEmpty ? null : () => _openTile(key),
                title: Text(tile['label']?.toString() ?? key),
                trailing: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      '${tile['count'] ?? 0}',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: MiddoColors.orange,
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const Icon(Icons.chevron_right, color: MiddoColors.muted),
                  ],
                ),
              ),
            );
          }),
        ],
      ),
    );
  }
}
