import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/auth_store.dart';
import '../screens/account_screen.dart';
import '../screens/alerts_screen.dart';
import '../screens/boxes_screen.dart';
import '../screens/complaints_screen.dart';
import '../screens/dispatch_screen.dart';
import '../screens/groups_screen.dart';
import '../screens/home_screen.dart';
import '../screens/login_screen.dart';
import '../screens/more_screen.dart';
import '../screens/order_detail_screen.dart';
import '../screens/orders_screen.dart';
import '../screens/prep_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/shell_scaffold.dart';
import '../screens/splash_screen.dart';

final rootNavigatorKey = GlobalKey<NavigatorState>();

CustomTransitionPage<void> _fadePage({
  required LocalKey key,
  required Widget child,
}) {
  return CustomTransitionPage<void>(
    key: key,
    child: child,
    transitionDuration: const Duration(milliseconds: 220),
    reverseTransitionDuration: const Duration(milliseconds: 180),
    transitionsBuilder: (context, animation, secondaryAnimation, child) {
      final curved = CurvedAnimation(
        parent: animation,
        curve: Curves.easeOutCubic,
        reverseCurve: Curves.easeInCubic,
      );
      return FadeTransition(
        opacity: curved,
        child: SlideTransition(
          position: Tween<Offset>(
            begin: const Offset(0.02, 0.01),
            end: Offset.zero,
          ).animate(curved),
          child: child,
        ),
      );
    },
  );
}

GoRouter createAppRouter() {
  return GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: '/splash',
    redirect: (context, state) {
      final loc = state.matchedLocation;
      if (loc == '/splash') return null;

      const publicAuth = {'/login'};
      final loggedIn = AuthStore.instance.isAuthenticated;
      final onPublicAuth = publicAuth.contains(loc);

      if (!loggedIn && !onPublicAuth) return '/login';
      if (loggedIn && onPublicAuth) return '/home';
      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const SplashScreen(),
        ),
      ),
      GoRoute(
        path: '/login',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const LoginScreen(),
        ),
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/alerts',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const AlertsScreen(),
        ),
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/boxes',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const BoxesScreen(),
        ),
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/account',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const AccountScreen(),
        ),
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/profile',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const ProfileScreen(),
        ),
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/complaints',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const ComplaintsScreen(),
        ),
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/complaints/:id',
        pageBuilder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return _fadePage(
            key: state.pageKey,
            child: ComplaintDetailScreen(complaintId: id),
          );
        },
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/orders/:id/dispatch',
        pageBuilder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return _fadePage(
            key: state.pageKey,
            child: DispatchScreen(orderId: id),
          );
        },
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/orders/:id',
        pageBuilder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return _fadePage(
            key: state.pageKey,
            child: OrderDetailScreen(orderId: id),
          );
        },
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) {
          return ShellScaffold(navigationShell: navigationShell);
        },
        branches: [
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/home',
                pageBuilder: (context, state) => _fadePage(
                  key: state.pageKey,
                  child: const HomeScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/orders',
                pageBuilder: (context, state) => _fadePage(
                  key: state.pageKey,
                  child: const OrdersScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/groups',
                pageBuilder: (context, state) => _fadePage(
                  key: state.pageKey,
                  child: const GroupsScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/prep',
                pageBuilder: (context, state) => _fadePage(
                  key: state.pageKey,
                  child: const PrepScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/more',
                pageBuilder: (context, state) => _fadePage(
                  key: state.pageKey,
                  child: const MoreScreen(),
                ),
              ),
            ],
          ),
        ],
      ),
    ],
  );
}
