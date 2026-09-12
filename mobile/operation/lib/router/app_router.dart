import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/auth_store.dart';
import '../screens/alerts_screen.dart';
import '../screens/boxes_screen.dart';
import '../screens/cash_screen.dart';
import '../screens/complaint_detail_screen.dart';
import '../screens/home_screen.dart';
import '../screens/login_screen.dart';
import '../screens/more_screen.dart';
import '../screens/orders_screen.dart';
import '../screens/qr_scan_screen.dart';
import '../screens/riders_screen.dart';
import '../screens/shell_scaffold.dart';
import '../screens/sla_screen.dart';
import '../screens/splash_screen.dart';

final rootNavigatorKey = GlobalKey<NavigatorState>();

GoRouter createAppRouter() {
  return GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: '/splash',
    redirect: (context, state) {
      final loc = state.matchedLocation;
      if (loc == '/splash') return null;
      final loggedIn = AuthStore.instance.isAuthenticated;
      final onLogin = loc == '/login';
      if (!loggedIn && !onLogin) return '/login';
      if (loggedIn && onLogin) return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, __) => const SplashScreen()),
      GoRoute(path: '/login', builder: (_, __) => const LoginScreen()),
      GoRoute(
        path: '/alerts',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, __) => const AlertsScreen(),
      ),
      GoRoute(
        path: '/sla',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, __) => const SlaScreen(),
      ),
      GoRoute(
        path: '/orders',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, __) => const OrdersScreen(),
      ),
      GoRoute(
        path: '/orders/:id',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return OrderDetailScreen(orderId: id);
        },
      ),
      GoRoute(
        path: '/complaints/:id',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, state) {
          final id = int.tryParse(state.pathParameters['id'] ?? '') ?? 0;
          return ComplaintDetailScreen(complaintId: id);
        },
      ),
      GoRoute(
        path: '/qr-scan',
        parentNavigatorKey: rootNavigatorKey,
        builder: (_, __) => const QrScanScreen(),
      ),
      StatefulShellRoute.indexedStack(
        builder: (context, state, navigationShell) =>
            ShellScaffold(navigationShell: navigationShell),
        branches: [
          StatefulShellBranch(routes: [
            GoRoute(path: '/home', builder: (_, __) => const HomeScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/boxes', builder: (_, __) => const BoxesScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/riders', builder: (_, __) => const RidersScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/cash', builder: (_, __) => const CashScreen()),
          ]),
          StatefulShellBranch(routes: [
            GoRoute(path: '/more', builder: (_, __) => const MoreScreen()),
          ]),
        ],
      ),
    ],
  );
}
