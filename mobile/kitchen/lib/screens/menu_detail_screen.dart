import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_mobile_header.dart';
import '../widgets/kitchen_ui.dart';
import '../widgets/skeleton.dart';

class MenuDetailScreen extends StatefulWidget {
  const MenuDetailScreen({super.key, required this.menuId});

  final int menuId;

  @override
  State<MenuDetailScreen> createState() => _MenuDetailScreenState();
}

class _MenuDetailScreenState extends State<MenuDetailScreen> {
  Future<Map<String, dynamic>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).showMenu(widget.menuId);
  }

  Future<void> _reload() async {
    setState(() {
      _future = AppScope.of(context).showMenu(widget.menuId);
    });
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: KitchenMobileHeader(
        title: 'Menu #${widget.menuId}',
        showBack: true,
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const ListSkeleton(rows: 3);
            }
            if (snap.hasError) {
              return KitchenError(snap.error!, onRetry: _reload);
            }
            final menu =
                (snap.data?['menu'] as Map?)?.cast<String, dynamic>() ??
                    const <String, dynamic>{};
            final meals = (menu['meal_items'] as List?) ?? const [];
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                Text(
                  menu['name']?.toString() ?? 'Menu',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                ),
                if ((menu['summary']?.toString() ?? '').isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Text(
                    menu['summary'].toString(),
                    style: const TextStyle(
                      color: MiddoColors.inkSoft,
                      fontWeight: FontWeight.w600,
                      height: 1.4,
                    ),
                  ),
                ],
                const SizedBox(height: 20),
                Text(
                  'Meal items',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 10),
                if (meals.isEmpty)
                  const KitchenEmpty('No meal items on this menu.'),
                for (final raw in meals)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: KitchenPanel(
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  (raw as Map)['name']?.toString() ?? 'Item',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                if ((raw['summary']?.toString() ?? '')
                                    .isNotEmpty) ...[
                                  const SizedBox(height: 4),
                                  Text(
                                    raw['summary'].toString(),
                                    style: const TextStyle(
                                      color: MiddoColors.inkSoft,
                                      fontSize: 13,
                                    ),
                                  ),
                                ],
                                if (raw['has_recipe'] == true) ...[
                                  const SizedBox(height: 6),
                                  Text(
                                    raw['recipe_title']?.toString() ??
                                        'Recipe available',
                                    style: const TextStyle(
                                      color: MiddoColors.forest,
                                      fontWeight: FontWeight.w700,
                                      fontSize: 12,
                                    ),
                                  ),
                                ],
                              ],
                            ),
                          ),
                          KitchenStatusChip(
                            raw['has_recipe'] == true ? 'Recipe' : 'No recipe',
                            positive: raw['has_recipe'] == true,
                          ),
                        ],
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}
