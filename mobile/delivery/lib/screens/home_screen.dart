import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_ui.dart';
import '../widgets/empty_state.dart';
import '../widgets/skeleton.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  Future<Map<String, dynamic>>? _dashboard;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _dashboard ??= AppScope.of(context).dashboard();
  }

  Future<void> _reload() async {
    setState(() {
      _dashboard = AppScope.of(context).dashboard();
    });
    await _dashboard;
  }

  void _openTile(String key) {
    MiddoHaptics.selection();
    switch (key) {
      case 'alerts':
        context.push('/alerts');
      case 'runs':
        context.go('/runs');
      case 'boxes':
        context.go('/boxes');
      case 'delivered':
      case 'cash':
        context.go('/cash');
      case 'custom_runs':
        context.go('/more');
      default:
        return;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _dashboard,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return ListView(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                children: const [
                  Row(
                    children: [
                      Expanded(child: SkeletonBox(height: 88, radius: 14)),
                      SizedBox(width: 10),
                      Expanded(child: SkeletonBox(height: 88, radius: 14)),
                    ],
                  ),
                  SizedBox(height: 10),
                  Row(
                    children: [
                      Expanded(child: SkeletonBox(height: 88, radius: 14)),
                      SizedBox(width: 10),
                      Expanded(child: SkeletonBox(height: 88, radius: 14)),
                    ],
                  ),
                ],
              );
            }
            if (snap.hasError) {
              return MiddoEmptyState(
                icon: Icons.cloud_off_outlined,
                title: 'Dashboard unavailable',
                message: '${snap.error}',
                actionLabel: 'Retry',
                onAction: _reload,
              );
            }

            final data = snap.data ?? const {};
            final tiles = (data['tiles'] as List?) ?? const [];

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                GridView.builder(
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  itemCount: tiles.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisSpacing: 10,
                    crossAxisSpacing: 10,
                    childAspectRatio: 1.45,
                  ),
                  itemBuilder: (context, i) {
                    final tile = (tiles[i] as Map).cast<String, dynamic>();
                    final key = tile['key']?.toString() ?? '';
                    return DeliveryPanel(
                      onTap: () => _openTile(key),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          Text(
                            tile['label']?.toString() ?? '',
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.4,
                              color: MiddoColors.muted,
                            ),
                          ),
                          Text(
                            '${tile['count'] ?? 0}',
                            style: const TextStyle(
                              fontSize: 28,
                              fontWeight: FontWeight.w900,
                              letterSpacing: -0.8,
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
