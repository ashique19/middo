import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/push_notification_service.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';

final _bdt = NumberFormat.currency(locale: 'en_BD', symbol: '৳', decimalDigits: 0);

/// Side navigation for account + secondary Middo destinations.
class MiddoAppDrawer extends StatelessWidget {
  const MiddoAppDrawer({super.key, this.user, this.boxesInCustody});

  final CorporateUser? user;
  final int? boxesInCustody;

  Future<void> _signOut(BuildContext context) async {
    Navigator.of(context).pop();
    await PushNotificationService.instance.unregisterFromBackend();
    if (!context.mounted) return;
    await AppScope.of(context).logout();
    if (!context.mounted) return;
    context.go('/login');
  }

  void _go(BuildContext context, String route) {
    Navigator.of(context).pop();
    context.push(route);
  }

  @override
  Widget build(BuildContext context) {
    final name = user?.receiverName.trim();
    final company = user?.companyName.trim() ?? 'Middo';
    final balance = user?.balance;
    final initial = user?.initial ?? 'M';

    return Drawer(
      backgroundColor: MiddoColors.cream,
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 16, 16),
              child: Row(
                children: [
                  CircleAvatar(
                    radius: 26,
                    backgroundColor: MiddoColors.amberSoft,
                    foregroundColor: MiddoColors.orange,
                    child: Text(
                      initial,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 18,
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          company,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            color: MiddoColors.forest,
                          ),
                        ),
                        if (name != null && name.isNotEmpty) ...[
                          const SizedBox(height: 2),
                          Text(
                            name,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                              color: MiddoColors.inkSoft,
                            ),
                          ),
                        ],
                        if (balance != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            'Balance ${_bdt.format(balance)}',
                            style: const TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w800,
                              color: MiddoColors.orange,
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: MiddoColors.creamBorder),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  const _DrawerSectionLabel('Account'),
                  _DrawerTile(
                    icon: Icons.person_outline_rounded,
                    label: 'Profile',
                    onTap: () => _go(context, '/profile'),
                  ),
                  _DrawerTile(
                    icon: Icons.lock_outline_rounded,
                    label: 'Change password',
                    onTap: () => _go(context, '/profile/password'),
                  ),
                  const _DrawerSectionLabel('Middo'),
                  _DrawerTile(
                    icon: Icons.inventory_2_outlined,
                    label: 'Middo boxes',
                    subtitle: boxesInCustody == null
                        ? null
                        : '$boxesInCustody with you',
                    onTap: () => _go(context, '/boxes'),
                  ),
                  _DrawerTile(
                    icon: Icons.history_rounded,
                    label: 'Order history',
                    onTap: () => _go(context, '/history'),
                  ),
                  _DrawerTile(
                    icon: Icons.card_giftcard_outlined,
                    label: 'Meal packages',
                    onTap: () => _go(context, '/packages'),
                  ),
                  _DrawerTile(
                    icon: Icons.event_repeat_rounded,
                    label: 'My subscriptions',
                    onTap: () => _go(context, '/subscriptions'),
                  ),
                ],
              ),
            ),
            const Divider(height: 1, color: MiddoColors.creamBorder),
            Padding(
              padding: const EdgeInsets.fromLTRB(8, 4, 8, 12),
              child: _DrawerTile(
                icon: Icons.logout_rounded,
                label: 'Sign out',
                danger: true,
                onTap: () => _signOut(context),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _DrawerSectionLabel extends StatelessWidget {
  const _DrawerSectionLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 14, 20, 4),
      child: Text(
        label.toUpperCase(),
        style: const TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.8,
          color: MiddoColors.muted,
        ),
      ),
    );
  }
}

class _DrawerTile extends StatelessWidget {
  const _DrawerTile({
    required this.icon,
    required this.label,
    required this.onTap,
    this.subtitle,
    this.danger = false,
  });

  final IconData icon;
  final String label;
  final String? subtitle;
  final VoidCallback onTap;
  final bool danger;

  @override
  Widget build(BuildContext context) {
    final color = danger ? MiddoColors.orangeDeep : MiddoColors.ink;
    return ListTile(
      leading: Icon(icon, color: color),
      title: Text(
        label,
        style: TextStyle(
          fontWeight: FontWeight.w700,
          color: color,
        ),
      ),
      subtitle: subtitle == null
          ? null
          : Text(
              subtitle!,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: MiddoColors.inkSoft,
              ),
            ),
      onTap: onTap,
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      contentPadding: const EdgeInsets.symmetric(horizontal: 20),
    );
  }
}
