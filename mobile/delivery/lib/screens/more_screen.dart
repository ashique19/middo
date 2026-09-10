import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/middo_haptics.dart';
import '../data/push_notification_service.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_ui.dart';

class MoreScreen extends StatefulWidget {
  const MoreScreen({super.key});

  @override
  State<MoreScreen> createState() => _MoreScreenState();
}

class _MoreScreenState extends State<MoreScreen> {
  Future<Map<String, dynamic>>? _me;
  Future<List<dynamic>>? _customRuns;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final repo = AppScope.of(context);
    _me ??= repo.me();
    _customRuns ??= repo.customRuns();
  }

  Future<void> _reloadCustom() async {
    setState(() {
      _customRuns = AppScope.of(context).customRuns();
    });
    await _customRuns;
  }

  Future<void> _logout() async {
    final repo = AppScope.of(context);
    await PushNotificationService.instance.unregisterFromBackend();
    await repo.logout();
    if (!mounted) return;
    context.go('/login');
  }

  Future<void> _start(int id) async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).startCustomRun(id);
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(context, res['message']?.toString() ?? 'Started.');
      await _reloadCustom();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _complete(int id) async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).completeCustomRun(id);
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(context, res['message']?.toString() ?? 'Completed.');
      await _reloadCustom();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
        children: [
          FutureBuilder<Map<String, dynamic>>(
            future: _me,
            builder: (context, snap) {
              final user = (snap.data?['user'] as Map?) ?? snap.data ?? {};
              final name =
                  '${user['first_name'] ?? ''} ${user['last_name'] ?? ''}'
                      .trim();
              return DeliveryPanel(
                onTap: () => context.push('/profile'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name.isEmpty ? 'Delivery rider' : name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      user['mobile']?.toString() ?? '',
                      style: const TextStyle(color: MiddoColors.inkSoft),
                    ),
                    const SizedBox(height: 6),
                    const Text(
                      'View profile',
                      style: TextStyle(
                        color: MiddoColors.forest,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                      ),
                    ),
                  ],
                ),
              );
            },
          ),
          const SizedBox(height: 16),
          _NavTile(
            icon: Icons.account_balance_wallet_outlined,
            title: 'Account',
            subtitle: 'Wallet balance & withdraw',
            onTap: () => context.push('/account'),
          ),
          _NavTile(
            icon: Icons.history,
            title: 'Runs history',
            subtitle: 'This month · last month · last 3 months',
            onTap: () => context.push('/history'),
          ),
          _NavTile(
            icon: Icons.notifications_outlined,
            title: 'Alerts',
            subtitle: 'Staff notifications',
            onTap: () => context.push('/alerts'),
          ),
          _NavTile(
            icon: Icons.person_outline,
            title: 'Profile',
            subtitle: 'Details & change password',
            onTap: () => context.push('/profile'),
          ),
          const SizedBox(height: 16),
          const Text(
            'Custom runs',
            style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
          ),
          const SizedBox(height: 8),
          FutureBuilder<List<dynamic>>(
            future: _customRuns,
            builder: (context, snap) {
              if (snap.connectionState != ConnectionState.done) {
                return const Padding(
                  padding: EdgeInsets.all(16),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              if (snap.hasError) {
                return Text(
                  '${snap.error}',
                  style: const TextStyle(color: Color(0xFFB42318)),
                );
              }
              final runs = snap.data ?? const [];
              if (runs.isEmpty) {
                return const Text(
                  'No custom runs.',
                  style: TextStyle(color: MiddoColors.inkSoft),
                );
              }
              return Column(
                children: runs.map((raw) {
                  final run = (raw as Map).cast<String, dynamic>();
                  final id = (run['id'] as num?)?.toInt() ?? 0;
                  return Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: DeliveryPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Row(
                            children: [
                              Expanded(
                                child: Text(
                                  run['title']?.toString() ?? 'Run #$id',
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                              ),
                              DeliveryStatusChip(
                                run['status']?.toString() ?? '',
                                positive: run['status'] == 'started',
                              ),
                            ],
                          ),
                          const SizedBox(height: 4),
                          Text(
                            '${run['from_label'] ?? ''} → ${run['to_label'] ?? ''}',
                            style: const TextStyle(
                              color: MiddoColors.inkSoft,
                              fontWeight: FontWeight.w600,
                              fontSize: 13,
                            ),
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: [
                              if (run['can_start'] == true)
                                Expanded(
                                  child: FilledButton(
                                    onPressed:
                                        _busy ? null : () => _start(id),
                                    child: const Text('Start'),
                                  ),
                                ),
                              if (run['can_start'] == true &&
                                  run['can_complete'] == true)
                                const SizedBox(width: 8),
                              if (run['can_complete'] == true)
                                Expanded(
                                  child: FilledButton(
                                    onPressed:
                                        _busy ? null : () => _complete(id),
                                    style: FilledButton.styleFrom(
                                      backgroundColor: MiddoColors.forest,
                                    ),
                                    child: const Text('Complete'),
                                  ),
                                ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  );
                }).toList(),
              );
            },
          ),
          const SizedBox(height: 12),
          OutlinedButton(
            onPressed: _logout,
            child: const Text('Log out'),
          ),
        ],
      ),
    );
  }
}

class _NavTile extends StatelessWidget {
  const _NavTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: Icon(icon, color: MiddoColors.forest),
      title: Text(title, style: const TextStyle(fontWeight: FontWeight.w700)),
      subtitle: Text(subtitle),
      trailing: const Icon(Icons.chevron_right),
      onTap: onTap,
    );
  }
}
