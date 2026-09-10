import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/auth_store.dart';
import '../screens/account_screen.dart';
import '../screens/alerts_screen.dart';
import '../screens/boxes_screen.dart';
import '../screens/cash_screen.dart';
import '../screens/home_screen.dart';
import '../screens/login_screen.dart';
import '../screens/more_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/run_detail_screen.dart';
import '../screens/runs_history_screen.dart';
import '../screens/runs_screen.dart';
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
        path: '/history',
        pageBuilder: (context, state) {
          final period =
              state.uri.queryParameters['period'] ?? 'this_month';
          return _fadePage(
            key: state.pageKey,
            child: RunsHistoryScreen(initialPeriod: period),
          );
        },
      ),
      GoRoute(
        parentNavigatorKey: rootNavigatorKey,
        path: '/runs/:id',
        pageBuilder: (context, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return _fadePage(
            key: state.pageKey,
            child: RunDetailScreen(runId: id),
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
                path: '/runs',
                pageBuilder: (context, state) => _fadePage(
                  key: state.pageKey,
                  child: const RunsScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/boxes',
                pageBuilder: (context, state) => _fadePage(
                  key: state.pageKey,
                  child: const BoxesScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/cash',
                pageBuilder: (context, state) => _fadePage(
                  key: state.pageKey,
                  child: const CashScreen(),
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
