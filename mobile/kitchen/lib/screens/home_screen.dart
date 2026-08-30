import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Future<Map<String, dynamic>>? _dashboard;
  Future<List<dynamic>>? _alerts;
  bool _loaded = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      final repo = AppScope.of(context);
      _dashboard = repo.dashboard();
      _alerts = repo.alerts();
    }
  }

  void _reload() {
    final repo = AppScope.of(context);
    _dashboard = repo.dashboard();
    _alerts = repo.alerts();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Kitchen')),
      body: RefreshIndicator(
        onRefresh: () async {
          setState(_reload);
          await Future.wait([
            _dashboard ?? Future.value(<String, dynamic>{}),
            _alerts ?? Future.value(<dynamic>[]),
          ]);
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            FutureBuilder<Map<String, dynamic>>(
              future: _dashboard,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                if (snap.hasError) {
                  return Text('Dashboard error: ${snap.error}');
                }
                final tiles = (snap.data?['tiles'] as List?) ?? const [];
                final capacity =
                    (snap.data?['capacity'] as Map?)?.cast<String, dynamic>() ??
                        const <String, dynamic>{};
                return Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (capacity.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: Text(
                          'Slots ${capacity['open_groups'] ?? '—'} / ${capacity['allowed_open_groups'] ?? '—'} · Boxes ${capacity['sendable_boxes'] ?? '—'}',
                          style: const TextStyle(
                            color: MiddoColors.inkSoft,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                    Wrap(
                      spacing: 10,
                      runSpacing: 10,
                      children: [
                        for (final raw in tiles)
                          _Tile(
                            label: (raw as Map)['label']?.toString() ?? '',
                            value: '${raw['count'] ?? '—'}',
                          ),
                      ],
                    ),
                  ],
                );
              },
            ),
            const SizedBox(height: 22),
            Text(
              'Alerts',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 8),
            FutureBuilder<List<dynamic>>(
              future: _alerts,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const SizedBox.shrink();
                }
                final alerts = snap.data ?? const [];
                if (alerts.isEmpty) {
                  return const Text('No alerts right now.');
                }
                return Column(
                  children: [
                    for (final raw in alerts.take(5))
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          (raw as Map)['title']?.toString() ?? 'Alert',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(raw['body']?.toString() ?? ''),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}

class _Tile extends StatelessWidget {
  const _Tile({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 156,
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
            value,
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: MiddoColors.forest,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: const TextStyle(
              color: MiddoColors.inkSoft,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
