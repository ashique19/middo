import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/push_notification_service.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_ui.dart';

class MoreScreen extends StatefulWidget {
  const MoreScreen({super.key});

  @override
  State<MoreScreen> createState() => _MoreScreenState();
}

class _MoreScreenState extends State<MoreScreen> {
  Future<Map<String, dynamic>>? _me;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _me ??= AppScope.of(context).me();
  }

  Future<void> _logout() async {
    await PushNotificationService.instance.unregisterFromBackend();
    await AppScope.of(context).logout();
    if (!mounted) return;
    context.go('/login');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('More')),
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
              return KitchenPanel(
                onTap: () => context.push('/profile'),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name.isEmpty ? 'Kitchen partner' : name,
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
            icon: Icons.inventory_2_outlined,
            title: 'Boxes',
            subtitle: 'Stock, incoming, request',
            onTap: () => context.push('/boxes'),
          ),
          _NavTile(
            icon: Icons.account_balance_wallet_outlined,
            title: 'Account & cash',
            subtitle: 'Balance, withdraw, handovers',
            onTap: () => context.push('/account'),
          ),
          _NavTile(
            icon: Icons.support_agent_outlined,
            title: 'Complaints',
            subtitle: 'Kitchen-related threads',
            onTap: () => context.push('/complaints'),
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
            subtitle: 'Details & password',
            onTap: () => context.push('/profile'),
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
