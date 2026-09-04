import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/middo_haptics.dart';
import '../data/tab_scroll_bus.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class ShellScaffold extends StatefulWidget {
  const ShellScaffold({super.key, required this.navigationShell});

  final StatefulNavigationShell navigationShell;

  @override
  State<ShellScaffold> createState() => _ShellScaffoldState();
}

class _ShellScaffoldState extends State<ShellScaffold> {
  CorporateUser? _user;
  int? _boxesInCustody;
  var _profileRequested = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_profileRequested) {
      _profileRequested = true;
      _loadDrawerProfile();
    }
  }

  Future<void> _loadDrawerProfile() async {
    try {
      final repo = AppScope.of(context);
      final results = await Future.wait([
        repo.me(),
        repo.dashboard(),
      ]);
      if (!mounted) return;
      setState(() {
        _user = results[0] as CorporateUser;
        _boxesInCustody =
            (results[1] as DashboardData).metrics.boxesInCustody;
      });
    } catch (_) {}
  }

  void _onDestinationSelected(int index) {
    MiddoHaptics.selection();
    if (index == widget.navigationShell.currentIndex) {
      TabScrollBus.instance.scrollToTop(index);
      return;
    }
    widget.navigationShell.goBranch(index);
    _loadDrawerProfile();
  }

  String get _tabTitle {
    return switch (widget.navigationShell.currentIndex) {
      0 => 'Home',
      1 => 'Menu',
      2 => 'Schedule',
      3 => 'Wallet',
      _ => 'Middo',
    };
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MiddoColors.cream,
      drawer: MiddoAppDrawer(
        user: _user,
        boxesInCustody: _boxesInCustody,
      ),
      appBar: AppBar(
        backgroundColor: MiddoColors.cream,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleSpacing: 0,
        title: Row(
          children: [
            Image.asset(
              'assets/images/middo-app-icon.png',
              height: 28,
              width: 28,
              errorBuilder: (_, __, ___) => const Icon(
                Icons.lunch_dining_rounded,
                color: MiddoColors.forest,
              ),
            ),
            const SizedBox(width: 8),
            const Text(
              'Middo',
              style: TextStyle(
                fontWeight: FontWeight.w900,
                fontSize: 20,
                letterSpacing: -0.4,
                color: MiddoColors.forest,
              ),
            ),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                _tabTitle,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 13,
                  color: MiddoColors.inkSoft,
                ),
              ),
            ),
          ],
        ),
        actions: [
          if (_user != null)
            Padding(
              padding: const EdgeInsets.only(right: 4),
              child: Center(
                child: Material(
                  color: MiddoColors.amberSoft,
                  borderRadius: BorderRadius.circular(999),
                  child: InkWell(
                    onTap: () {
                      MiddoHaptics.selection();
                      context.go('/wallet');
                    },
                    borderRadius: BorderRadius.circular(999),
                    child: Padding(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 7,
                      ),
                      child: Text(
                        bdt.format(_user!.balance),
                        style: const TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 12,
                          color: MiddoColors.orangeDeep,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          Padding(
            padding: const EdgeInsets.only(right: 10),
            child: IconButton(
              tooltip: 'Account',
              onPressed: () => showAccountSheet(
                context,
                user: _user,
                onProfileUpdated: _loadDrawerProfile,
              ),
              icon: CircleAvatar(
                radius: 16,
                backgroundColor: MiddoColors.forest,
                foregroundColor: Colors.white,
                child: Text(
                  _user?.initial ?? 'M',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          const NetworkBanner(),
          Expanded(child: widget.navigationShell),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        height: 68,
        backgroundColor: MiddoColors.white,
        indicatorColor: MiddoColors.forest.withValues(alpha: 0.12),
        selectedIndex: widget.navigationShell.currentIndex,
        onDestinationSelected: _onDestinationSelected,
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.restaurant_menu_outlined),
            selectedIcon: Icon(Icons.restaurant_menu_rounded),
            label: 'Menu',
          ),
          NavigationDestination(
            icon: Icon(Icons.calendar_month_outlined),
            selectedIcon: Icon(Icons.calendar_month_rounded),
            label: 'Schedule',
          ),
          NavigationDestination(
            icon: Icon(Icons.account_balance_wallet_outlined),
            selectedIcon: Icon(Icons.account_balance_wallet_rounded),
            label: 'Wallet',
          ),
        ],
      ),
    );
  }
}
