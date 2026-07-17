import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/auth_store.dart';
import '../screens/checkout_screen.dart';
import '../screens/history_screen.dart';
import '../screens/home_screen.dart';
import '../screens/login_screen.dart';
import '../screens/menu_screen.dart';
import '../screens/schedule_screen.dart';
import '../screens/shell_scaffold.dart';
import '../screens/splash_screen.dart';
import '../screens/support_screen.dart';
import '../screens/track_screen.dart';
import '../screens/wallet_screen.dart';

final _rootKey = GlobalKey<NavigatorState>();

GoRouter createAppRouter() {
  return GoRouter(
    navigatorKey: _rootKey,
    initialLocation: '/splash',
    redirect: (context, state) {
      final loc = state.matchedLocation;
      if (loc == '/splash') return null;

      final loggedIn = AuthStore.instance.isAuthenticated;
      final loggingIn = loc == '/login';
      if (!loggedIn && !loggingIn) return '/login';
      if (loggedIn && loggingIn) return '/home';
      return null;
    },
    routes: [
      GoRoute(
        path: '/splash',
        builder: (context, state) => const SplashScreen(),
      ),
      GoRoute(
        path: '/login',
        builder: (context, state) => const LoginScreen(),
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
                builder: (context, state) => const HomeScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/menu',
                builder: (context, state) => const MenuScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/schedule',
                builder: (context, state) => const ScheduleScreen(),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/wallet',
                builder: (context, state) => const WalletScreen(),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: '/checkout/:menuItemId',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => CheckoutScreen(
          menuItemId: state.pathParameters['menuItemId']!,
        ),
      ),
      GoRoute(
        path: '/track/:orderId',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => TrackScreen(
          orderId: state.pathParameters['orderId']!,
        ),
      ),
      GoRoute(
        path: '/history',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => const HistoryScreen(),
      ),
      GoRoute(
        path: '/support/:orderId',
        parentNavigatorKey: _rootKey,
        builder: (context, state) => SupportScreen(
          orderId: state.pathParameters['orderId']!,
        ),
      ),
    ],
  );
}
