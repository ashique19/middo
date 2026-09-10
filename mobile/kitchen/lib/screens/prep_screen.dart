import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/empty_state.dart';
import '../widgets/kitchen_ui.dart';
import '../widgets/skeleton.dart';

class PrepScreen extends StatefulWidget {
  const PrepScreen({super.key});

  @override
  State<PrepScreen> createState() => _PrepScreenState();
}

class _PrepScreenState extends State<PrepScreen> {
  Future<Map<String, dynamic>>? _menus;
  Future<Map<String, dynamic>>? _shopping;
  bool _loaded = false;
  String _ingredientQuery = '';

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

  Future<void> _reload() async {
    final repo = AppScope.of(context);
    setState(() {
      _menus = repo.menusToday();
      _shopping = repo.shoppingList();
    });
    await Future.wait([
      _menus ?? Future.value(<String, dynamic>{}),
      _shopping ?? Future.value(<String, dynamic>{}),
    ]);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _reload,
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
                  return const SkeletonBox(height: 120, radius: 14);
                }
                if (snap.hasError) {
                  return Text('Menus error: ${snap.error}');
                }
                final menus = (snap.data?['menus'] as List?) ?? const [];
                if (menus.isEmpty) {
                  return const MiddoEmptyState(
                    icon: Icons.restaurant_menu_outlined,
                    title: 'No menus today',
                    message:
                        'Accepted groups for today will show menu rollups here.',
                  );
                }
                return Column(
                  children: [
                    for (final raw in menus)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: Builder(
                          builder: (context) {
                            final map = Map<String, dynamic>.from(raw as Map);
                            return KitchenPanel(
                              onTap: () {
                                MiddoHaptics.selection();
                                final id = map['id'];
                                final parsed =
                                    id is int ? id : int.tryParse('$id');
                                if (parsed != null) {
                                  context.push('/menus/$parsed');
                                }
                              },
                              child: Row(
                                children: [
                                  Expanded(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.start,
                                      children: [
                                        Text(
                                          map['name']?.toString() ?? 'Menu',
                                          style: const TextStyle(
                                            fontWeight: FontWeight.w800,
                                          ),
                                        ),
                                        const SizedBox(height: 4),
                                        Text(
                                          'Qty ${map['total_qty'] ?? '—'} · ${map['order_count'] ?? '—'} orders',
                                          style: const TextStyle(
                                            color: MiddoColors.inkSoft,
                                            fontWeight: FontWeight.w600,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                  const Icon(
                                    Icons.chevron_right,
                                    color: MiddoColors.muted,
                                  ),
                                ],
                              ),
                            );
                          },
                        ),
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
            TextField(
              onChanged: (v) => setState(() => _ingredientQuery = v.trim()),
              decoration: const InputDecoration(
                hintText: 'Search ingredients…',
                prefixIcon: Icon(Icons.search),
                isDense: true,
              ),
            ),
            const SizedBox(height: 10),
            FutureBuilder<Map<String, dynamic>>(
              future: _shopping,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const SkeletonBox(height: 160, radius: 14);
                }
                if (snap.hasError) {
                  return Text('Shopping list error: ${snap.error}');
                }
                final items = (snap.data?['ingredients'] as List?) ??
                    (snap.data?['items'] as List?) ??
                    const [];
                final q = _ingredientQuery.toLowerCase();
                final filtered = q.isEmpty
                    ? items
                    : items.where((item) {
                        final name =
                            ((item as Map)['name']?.toString() ?? '')
                                .toLowerCase();
                        return name.contains(q);
                      }).toList();
                if (items.isEmpty) {
                  return const MiddoEmptyState(
                    icon: Icons.shopping_basket_outlined,
                    title: 'Shopping list empty',
                    message:
                        'Accept order groups with recipes to build today’s ingredient rollup.',
                  );
                }
                if (filtered.isEmpty) {
                  return Padding(
                    padding: const EdgeInsets.symmetric(vertical: 24),
                    child: Text(
                      'No ingredients match “$_ingredientQuery”.',
                      style: const TextStyle(color: MiddoColors.inkSoft),
                    ),
                  );
                }
                return Column(
                  children: [
                    for (final item in filtered)
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
                          '${(item as Map)['name'] ?? 'Item'} — ${item['quantity'] ?? item['qty'] ?? ''}${item['unit'] != null ? ' ${item['unit']}' : ''}',
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
