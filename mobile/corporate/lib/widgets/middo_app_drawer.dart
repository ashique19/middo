import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/middo_haptics.dart';
import '../data/push_notification_service.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';

final _bdt = NumberFormat.currency(locale: 'en_BD', symbol: '৳', decimalDigits: 0);

/// Side navigation for Middo tools (account lives under the avatar sheet).
class MiddoAppDrawer extends StatelessWidget {
  const MiddoAppDrawer({super.key, this.user, this.boxesInCustody});

  final CorporateUser? user;
  final int? boxesInCustody;

  void _go(BuildContext context, String route) {
    MiddoHaptics.selection();
    Navigator.of(context).pop();
    context.push(route);
  }

  @override
  Widget build(BuildContext context) {
    final company = user?.companyName.trim() ?? 'Middo';
    final balance = user?.balance;

    return Drawer(
      backgroundColor: MiddoColors.cream,
      child: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 20, 16, 16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Middo',
                    style: TextStyle(
                      fontWeight: FontWeight.w900,
                      fontSize: 22,
                      color: MiddoColors.forest,
                      letterSpacing: -0.4,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    company,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontWeight: FontWeight.w700,
                      fontSize: 13,
                      color: MiddoColors.inkSoft,
                    ),
                  ),
                  if (balance != null) ...[
                    const SizedBox(height: 6),
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
            const Divider(height: 1, color: MiddoColors.creamBorder),
            Expanded(
              child: ListView(
                padding: const EdgeInsets.symmetric(vertical: 8),
                children: [
                  const _DrawerSectionLabel('Quick links'),
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
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
              child: Text(
                'Account settings are under your profile avatar.',
                style: TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: MiddoColors.muted.withValues(alpha: 0.95),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Account actions (profile / password / sign out).
Future<void> showAccountSheet(
  BuildContext context, {
  CorporateUser? user,
  Future<void> Function()? onProfileUpdated,
}) async {
  MiddoHaptics.selection();
  final action = await showModalBottomSheet<String>(
    context: context,
    backgroundColor: MiddoColors.cream,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
    ),
    builder: (context) {
      return SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 12, 12, 16),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: MiddoColors.creamBorder,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              const SizedBox(height: 14),
              Align(
                alignment: Alignment.centerLeft,
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  child: Text(
                    user?.companyName ?? 'Account',
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 8),
              ListTile(
                leading: const Icon(Icons.person_outline_rounded),
                title: const Text(
                  'View / edit profile',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                onTap: () => Navigator.pop(context, 'profile'),
              ),
              ListTile(
                leading: const Icon(Icons.lock_outline_rounded),
                title: const Text(
                  'Change password',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                onTap: () => Navigator.pop(context, 'password'),
              ),
              ListTile(
                leading: const Icon(Icons.logout_rounded),
                title: const Text(
                  'Sign out',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
                onTap: () => Navigator.pop(context, 'logout'),
              ),
            ],
          ),
        ),
      );
    },
  );

  if (!context.mounted || action == null) return;
  if (action == 'profile') {
    await context.push('/profile');
    await onProfileUpdated?.call();
  } else if (action == 'password') {
    await context.push('/profile/password');
  } else if (action == 'logout') {
    await PushNotificationService.instance.unregisterFromBackend();
    if (!context.mounted) return;
    await AppScope.of(context).logout();
    if (!context.mounted) return;
    context.go('/login');
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
  });

  final IconData icon;
  final String label;
  final String? subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      leading: Icon(icon, color: MiddoColors.ink),
      title: Text(
        label,
        style: const TextStyle(
          fontWeight: FontWeight.w700,
          color: MiddoColors.ink,
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
