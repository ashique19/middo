import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/push_notification_service.dart';
import '../theme/middo_colors.dart';

/// Matches `resources/views/components/kitchen/layout/header.blade.php`.
class KitchenMobileHeader extends StatefulWidget implements PreferredSizeWidget {
  const KitchenMobileHeader({
    super.key,
    required this.title,
    this.showBack = false,
  });

  final String title;
  final bool showBack;

  @override
  Size get preferredSize => const Size.fromHeight(64);

  @override
  State<KitchenMobileHeader> createState() => _KitchenMobileHeaderState();
}

class _KitchenMobileHeaderState extends State<KitchenMobileHeader> {
  Future<int>? _unread;
  Future<String>? _initial;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _unread ??= AppScope.of(context).unreadAlertCount();
    _initial ??= _loadInitial();
  }

  Future<String> _loadInitial() async {
    try {
      final data = await AppScope.of(context).me();
      final user = (data['user'] as Map?) ?? data;
      final first = user['first_name']?.toString().trim();
      if (first != null && first.isNotEmpty) {
        return first.substring(0, 1).toUpperCase();
      }
    } catch (_) {}
    return 'K';
  }

  void _refreshUnread() {
    setState(() {
      _unread = AppScope.of(context).unreadAlertCount();
    });
  }

  Future<void> _logout() async {
    await PushNotificationService.instance.unregisterFromBackend();
    await AppScope.of(context).logout();
    if (!mounted) return;
    context.go('/login');
  }

  void _openAccountMenu() {
    showModalBottomSheet<void>(
      context: context,
      backgroundColor: MiddoColors.white,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (ctx) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.person_outline),
                title: const Text('Profile'),
                onTap: () {
                  Navigator.pop(ctx);
                  context.push('/profile');
                },
              ),
              ListTile(
                leading: const Icon(Icons.lock_outline),
                title: const Text('Change password'),
                onTap: () {
                  Navigator.pop(ctx);
                  context.push('/profile');
                },
              ),
              const Divider(height: 1),
              ListTile(
                leading: const Icon(Icons.logout, color: Colors.red),
                title: const Text(
                  'Log out',
                  style: TextStyle(color: Colors.red),
                ),
                onTap: () {
                  Navigator.pop(ctx);
                  _logout();
                },
              ),
            ],
          ),
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Material(
      color: MiddoColors.cream,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
          child: Row(
            children: [
              if (widget.showBack)
                _HeaderIconButton(
                  onTap: () {
                    if (context.canPop()) {
                      context.pop();
                    } else {
                      context.go('/home');
                    }
                  },
                  border: true,
                  child: const Icon(Icons.arrow_back, size: 20),
                )
              else
                _CashButton(onTap: () => context.push('/account')),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Middo Kitchen',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.4,
                        color: Color(0xFF8A735C),
                      ),
                    ),
                    Text(
                      widget.title,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: MiddoColors.ink,
                        height: 1.15,
                      ),
                    ),
                  ],
                ),
              ),
              FutureBuilder<int>(
                future: _unread,
                builder: (context, snap) {
                  final unread = snap.data ?? 0;
                  return _HeaderIconButton(
                    onTap: () async {
                      await context.push('/alerts');
                      _refreshUnread();
                    },
                    border: true,
                    badge: unread > 0
                        ? (unread > 9 ? '9+' : '$unread')
                        : null,
                    child: const Icon(Icons.notifications_outlined, size: 22),
                  );
                },
              ),
              const SizedBox(width: 8),
              FutureBuilder<String>(
                future: _initial,
                builder: (context, snap) {
                  final initial = snap.data ?? 'K';
                  return _HeaderIconButton(
                    onTap: _openAccountMenu,
                    background: MiddoColors.orange.withValues(alpha: 0.12),
                    foreground: MiddoColors.orange,
                    child: Text(
                      initial,
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                      ),
                    ),
                  );
                },
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CashButton extends StatelessWidget {
  const _CashButton({required this.onTap});

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: MiddoColors.forest,
      borderRadius: BorderRadius.circular(16),
      elevation: 1,
      shadowColor: MiddoColors.forest.withValues(alpha: 0.25),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: const Padding(
          padding: EdgeInsets.symmetric(horizontal: 10, vertical: 10),
          child: Text(
            'Cash',
            style: TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.2,
            ),
          ),
        ),
      ),
    );
  }
}

class _HeaderIconButton extends StatelessWidget {
  const _HeaderIconButton({
    required this.onTap,
    required this.child,
    this.border = false,
    this.badge,
    this.background,
    this.foreground,
  });

  final VoidCallback onTap;
  final Widget child;
  final bool border;
  final String? badge;
  final Color? background;
  final Color? foreground;

  @override
  Widget build(BuildContext context) {
    return Stack(
      clipBehavior: Clip.none,
      children: [
        Material(
          color: background ?? MiddoColors.white.withValues(alpha: 0.8),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(16),
            side: border
                ? const BorderSide(color: MiddoColors.creamBorder)
                : BorderSide.none,
          ),
          child: InkWell(
            onTap: onTap,
            borderRadius: BorderRadius.circular(16),
            child: SizedBox(
              width: 44,
              height: 44,
              child: DefaultTextStyle(
                style: TextStyle(color: foreground ?? MiddoColors.ink),
                child: IconTheme(
                  data: IconThemeData(color: foreground ?? MiddoColors.ink),
                  child: Center(child: child),
                ),
              ),
            ),
          ),
        ),
        if (badge != null)
          Positioned(
            top: -4,
            right: -4,
            child: Container(
              constraints: const BoxConstraints(minWidth: 18, minHeight: 18),
              padding: const EdgeInsets.symmetric(horizontal: 4),
              decoration: BoxDecoration(
                color: MiddoColors.orange,
                borderRadius: BorderRadius.circular(999),
              ),
              alignment: Alignment.center,
              child: Text(
                badge!,
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ),
      ],
    );
  }
}
