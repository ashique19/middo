import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
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
  bool _busy = false;

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

  Future<void> _setShift(String status) async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).setShift(status);
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(
        context,
        res['message']?.toString() ?? 'Shift updated.',
      );
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
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
                  SkeletonBox(height: 88, radius: 14),
                  SizedBox(height: 16),
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
            final shift = data['shift_status']?.toString() ?? 'on';
            final options =
                (data['shift_options'] as Map?)?.cast<String, dynamic>() ??
                    const {
                      'on': 'On shift',
                      'off': 'Off shift',
                      'unable': 'Unable',
                    };

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                DeliveryPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'SHIFT',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.8,
                          color: MiddoColors.muted,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        data['shift_label']?.toString() ??
                            options[shift]?.toString() ??
                            shift,
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 16,
                        ),
                      ),
                      const SizedBox(height: 4),
                      const Text(
                        'Off / unable blocks new lunch & custom runs.',
                        style: TextStyle(
                          fontSize: 12,
                          color: MiddoColors.inkSoft,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: options.entries.map((e) {
                          final selected = e.key == shift;
                          return ChoiceChip(
                            label: Text(e.value.toString()),
                            selected: selected,
                            onSelected: _busy
                                ? null
                                : (_) => _setShift(e.key),
                            selectedColor: MiddoColors.forest,
                            labelStyle: TextStyle(
                              color: selected
                                  ? Colors.white
                                  : MiddoColors.inkSoft,
                              fontWeight: FontWeight.w700,
                              fontSize: 12,
                            ),
                            backgroundColor: MiddoColors.white,
                            side: BorderSide(
                              color: selected
                                  ? MiddoColors.forest
                                  : MiddoColors.creamBorder,
                            ),
                          );
                        }).toList(),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
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
