import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/auth_store.dart';
import '../screens/boxes_screen.dart';
import '../screens/change_password_screen.dart';
import '../screens/checkout_screen.dart';
import '../screens/forgot_password_screen.dart';
import '../screens/history_screen.dart';
import '../screens/home_screen.dart';
import '../screens/login_screen.dart';
import '../screens/menu_screen.dart';
import '../screens/package_checkout_screen.dart';
import '../screens/packages_screen.dart';
import '../screens/payment_result_screen.dart';
import '../screens/profile_screen.dart';
import '../screens/schedule_screen.dart';
import '../screens/shell_scaffold.dart';
import '../screens/signup_screen.dart';
import '../screens/splash_screen.dart';
import '../screens/subscription_detail_screen.dart';
import '../screens/subscriptions_screen.dart';
import '../screens/support_screen.dart';
import '../screens/track_screen.dart';
import '../screens/wallet_screen.dart';

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

      const publicAuth = {
        '/login',
        '/signup',
        '/forgot-password',
        '/payment/result',
      };
      final loggedIn = AuthStore.instance.isAuthenticated;
      final onPublicAuth = publicAuth.contains(loc);

      if (!loggedIn && !onPublicAuth) return '/login';
      if (loggedIn && {'/login', '/signup', '/forgot-password'}.contains(loc)) {
        return '/home';
      }
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
        path: '/signup',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const SignupScreen(),
        ),
      ),
      GoRoute(
        path: '/forgot-password',
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const ForgotPasswordScreen(),
        ),
      ),
      GoRoute(
        path: '/payment/result',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) {
          final ok = state.uri.queryParameters['ok'] != '0';
          return _fadePage(
            key: state.pageKey,
            child: PaymentResultScreen(
              success: ok,
              title: state.uri.queryParameters['title'] ?? 'Payment',
              message: state.uri.queryParameters['message'],
              primaryLabel: state.uri.queryParameters['primary_label'],
              primaryRoute: state.uri.queryParameters['primary_route'],
              secondaryLabel:
                  state.uri.queryParameters['secondary_label'] ?? 'Go home',
              secondaryRoute:
                  state.uri.queryParameters['secondary_route'] ?? '/home',
              confirming: state.uri.queryParameters['confirming'] == '1',
            ),
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
                pageBuilder: (context, state) => NoTransitionPage(
                  key: state.pageKey,
                  child: const HomeScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/menu',
                pageBuilder: (context, state) => NoTransitionPage(
                  key: state.pageKey,
                  child: const MenuScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/schedule',
                pageBuilder: (context, state) => NoTransitionPage(
                  key: state.pageKey,
                  child: const ScheduleScreen(),
                ),
              ),
            ],
          ),
          StatefulShellBranch(
            routes: [
              GoRoute(
                path: '/wallet',
                pageBuilder: (context, state) => NoTransitionPage(
                  key: state.pageKey,
                  child: const WalletScreen(),
                ),
              ),
            ],
          ),
        ],
      ),
      GoRoute(
        path: '/checkout/:menuItemId',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: CheckoutScreen(
            menuItemId: state.pathParameters['menuItemId']!,
          ),
        ),
      ),
      GoRoute(
        path: '/packages',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const PackagesScreen(),
        ),
      ),
      GoRoute(
        path: '/packages/:packageId',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: PackageCheckoutScreen(
            packageId: state.pathParameters['packageId']!,
          ),
        ),
      ),
      GoRoute(
        path: '/subscriptions',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const SubscriptionsScreen(),
        ),
      ),
      GoRoute(
        path: '/subscriptions/:subscriptionId',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: SubscriptionDetailScreen(
            subscriptionId: state.pathParameters['subscriptionId']!,
          ),
        ),
      ),
      GoRoute(
        path: '/track/:orderId',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: TrackScreen(
            orderId: state.pathParameters['orderId']!,
          ),
        ),
      ),
      GoRoute(
        path: '/history',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const HistoryScreen(),
        ),
      ),
      GoRoute(
        path: '/boxes',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const BoxesScreen(),
        ),
      ),
      GoRoute(
        path: '/profile',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const ProfileScreen(),
        ),
      ),
      GoRoute(
        path: '/profile/password',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: const ChangePasswordScreen(),
        ),
      ),
      GoRoute(
        path: '/support/:orderId',
        parentNavigatorKey: rootNavigatorKey,
        pageBuilder: (context, state) => _fadePage(
          key: state.pageKey,
          child: SupportScreen(
            orderId: state.pathParameters['orderId']!,
          ),
        ),
      ),
    ],
  );
}
