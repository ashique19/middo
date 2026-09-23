import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
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

  static const _toolbarHeight = 68.0;

  @override
  Size get preferredSize => const Size.fromHeight(_toolbarHeight);

  @override
  State<KitchenMobileHeader> createState() => _KitchenMobileHeaderState();
}

class _AccountMark {
  const _AccountMark({required this.initial, this.photoUrl});

  final String initial;
  final String? photoUrl;
}

class _KitchenMobileHeaderState extends State<KitchenMobileHeader> {
  Future<int>? _unread;
  Future<_AccountMark>? _mark;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _unread ??= AppScope.of(context).unreadAlertCount();
    _mark ??= _loadMark();
  }

  Future<_AccountMark> _loadMark() async {
    try {
      final data = await AppScope.of(context).me();
      final user = (data['user'] as Map?) ?? data;
      final first = user['first_name']?.toString().trim();
      final photo = user['profile_photo_url']?.toString().trim();
      return _AccountMark(
        initial: (first != null && first.isNotEmpty)
            ? first.substring(0, 1).toUpperCase()
            : 'K',
        photoUrl: (photo != null && photo.isNotEmpty) ? photo : null,
      );
    } catch (_) {}
    return const _AccountMark(initial: 'K');
  }

  void _refreshMark() {
    setState(() {
      _mark = _loadMark();
    });
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
                onTap: () async {
                  Navigator.pop(ctx);
                  await context.push('/profile');
                  if (mounted) _refreshMark();
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
    return AppBar(
      automaticallyImplyLeading: false,
      toolbarHeight: KitchenMobileHeader._toolbarHeight,
      backgroundColor: MiddoColors.cream,
      elevation: 0,
      scrolledUnderElevation: 0,
      surfaceTintColor: Colors.transparent,
      systemOverlayStyle: SystemUiOverlayStyle.dark,
      titleSpacing: 0,
      title: Padding(
        padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.center,
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
                mainAxisAlignment: MainAxisAlignment.center,
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Middo Kitchen',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 1.4,
                      height: 1.2,
                      color: Color(0xFF8A735C),
                    ),
                  ),
                  const SizedBox(height: 2),
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
                  badge: unread > 0 ? (unread > 9 ? '9+' : '$unread') : null,
                  child: const Icon(Icons.notifications_outlined, size: 22),
                );
              },
            ),
            const SizedBox(width: 8),
            FutureBuilder<_AccountMark>(
              future: _mark,
              builder: (context, snap) {
                final mark = snap.data ?? const _AccountMark(initial: 'K');
                return _HeaderIconButton(
                  onTap: _openAccountMenu,
                  background: MiddoColors.orange.withValues(alpha: 0.12),
                  foreground: MiddoColors.orange,
                  child: mark.photoUrl == null
                      ? Text(
                          mark.initial,
                          style: const TextStyle(
                            fontWeight: FontWeight.w900,
                            fontSize: 16,
                          ),
                        )
                      : ClipRRect(
                          borderRadius: BorderRadius.circular(14),
                          child: Image.network(
                            mark.photoUrl!,
                            width: 44,
                            height: 44,
                            fit: BoxFit.cover,
                            errorBuilder: (_, __, ___) => Text(
                              mark.initial,
                              style: const TextStyle(
                                fontWeight: FontWeight.w900,
                                fontSize: 16,
                              ),
                            ),
                          ),
                        ),
                );
              },
            ),
          ],
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
        child: const SizedBox(
          height: 44,
          child: Padding(
            padding: EdgeInsets.symmetric(horizontal: 12),
            child: Center(
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
