import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';

class PrepScreen extends StatefulWidget {
  const PrepScreen({super.key});

  @override
  State<PrepScreen> createState() => _PrepScreenState();
}

class _PrepScreenState extends State<PrepScreen> {
  Future<Map<String, dynamic>>? _menus;
  Future<Map<String, dynamic>>? _shopping;
  bool _loaded = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      final repo = AppScope.of(context);
      _menus = repo.menusToday();
      _shopping = repo.shoppingList();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: () async {
          final repo = AppScope.of(context);
          setState(() {
            _menus = repo.menusToday();
            _shopping = repo.shoppingList();
          });
          await Future.wait([
            _menus ?? Future.value(<String, dynamic>{}),
            _shopping ?? Future.value(<String, dynamic>{}),
          ]);
        },
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            Text(
              'Today’s menus',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 8),
            FutureBuilder<Map<String, dynamic>>(
              future: _menus,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const Padding(
                    padding: EdgeInsets.all(16),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                if (snap.hasError) {
                  return Text('Menus error: ${snap.error}');
                }
                final menus = (snap.data?['menus'] as List?) ?? const [];
                if (menus.isEmpty) {
                  return const Text('No menus for today.');
                }
                return Column(
                  children: [
                    for (final raw in menus)
                      ListTile(
                        contentPadding: EdgeInsets.zero,
                        title: Text(
                          (raw as Map)['name']?.toString() ?? 'Menu',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                        subtitle: Text(
                            'Qty ${raw['total_qty'] ?? '—'} · ${raw['order_count'] ?? '—'} orders'),
                      ),
                  ],
                );
              },
            ),
            const SizedBox(height: 20),
            Text(
              'Shopping list',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 8),
            FutureBuilder<Map<String, dynamic>>(
              future: _shopping,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const SizedBox.shrink();
                }
                if (snap.hasError) {
                  return Text('Shopping list error: ${snap.error}');
                }
                final items = (snap.data?['ingredients'] as List?) ??
                    (snap.data?['items'] as List?) ??
                    const [];
                if (items.isEmpty) {
                  return const Text('Shopping list is empty.');
                }
                return Column(
                  children: [
                    for (final raw in items)
                      Container(
                        width: double.infinity,
                        margin: const EdgeInsets.only(bottom: 8),
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: MiddoColors.white,
                          borderRadius: BorderRadius.circular(12),
                          border: Border.all(color: MiddoColors.creamBorder),
                        ),
                        child: Text(
                          '${(raw as Map)['name'] ?? 'Item'} — ${raw['quantity'] ?? raw['qty'] ?? ''}${raw['unit'] != null ? ' ${raw['unit']}' : ''}',
                          style: const TextStyle(fontWeight: FontWeight.w600),
                        ),
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
