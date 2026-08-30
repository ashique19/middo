import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/push_notification_service.dart';
import '../theme/middo_colors.dart';

class MoreScreen extends StatefulWidget {
  const MoreScreen({super.key});

  @override
  State<MoreScreen> createState() => _MoreScreenState();
}

class _MoreScreenState extends State<MoreScreen> {
  Future<Map<String, dynamic>>? _me;
  bool _loaded = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      _me = AppScope.of(context).me();
    }
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
              return Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: MiddoColors.white,
                  borderRadius: BorderRadius.circular(14),
                  border: Border.all(color: MiddoColors.creamBorder),
                ),
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
                  ],
                ),
              );
            },
          ),
          const SizedBox(height: 16),
          const ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.inventory_2_outlined),
            title: Text('Boxes'),
            subtitle: Text('API ready — UI next'),
          ),
          const ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.account_balance_wallet_outlined),
            title: Text('Account'),
            subtitle: Text('API ready — UI next'),
          ),
          const ListTile(
            contentPadding: EdgeInsets.zero,
            leading: Icon(Icons.support_agent_outlined),
            title: Text('Complaints'),
            subtitle: Text('API ready — UI next'),
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
