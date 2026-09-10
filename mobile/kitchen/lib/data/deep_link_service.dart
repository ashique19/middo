import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import 'auth_store.dart';

/// Handles `middo-kitchen://` deep links from pushes / shared URLs.
class DeepLinkService {
  DeepLinkService._();
  static final instance = DeepLinkService._();

  final _appLinks = AppLinks();
  Uri? _pending;
  var _started = false;

  Future<void> start(GoRouter router) async {
    if (_started || kIsWeb) return;
    _started = true;

    try {
      final initial = await _appLinks.getInitialLink();
      if (initial != null) _pending = initial;
    } catch (e) {
      debugPrint('DeepLink: initial link failed ($e)');
    }

    _appLinks.uriLinkStream.listen((uri) => _handle(router, uri));

    WidgetsBinding.instance.addPostFrameCallback((_) {
      final pending = _pending;
      if (pending == null) return;
      _pending = null;
      _handle(router, pending);
    });
  }

  void _handle(GoRouter router, Uri uri) {
    if (uri.scheme != 'middo-kitchen') return;

    if (!AuthStore.instance.isAuthenticated) {
      _pending = uri;
      router.go('/login');
      return;
    }

    final host = uri.host.toLowerCase();
    final segments = uri.pathSegments;

    String? route;
    switch (host) {
      case 'groups':
        route = '/groups';
      case 'alerts':
        route = '/alerts';
      case 'orders':
        if (segments.isNotEmpty) {
          final id = segments.first;
          if (segments.length > 1 && segments[1] == 'dispatch') {
            route = '/orders/$id/dispatch';
          } else {
            route = '/orders/$id';
          }
        } else {
          route = '/orders';
        }
      case 'boxes':
        route = '/boxes';
      case 'prep':
        route = '/prep';
      case 'account':
        route = '/account';
      case 'history':
        final period = uri.queryParameters['period'] ?? 'this_month';
        route = '/history?period=$period';
      case 'home':
        route = '/home';
      case 'menus':
        if (segments.isNotEmpty) {
          route = '/menus/${segments.first}';
        } else {
          route = '/prep';
        }
      default:
        if (uri.path.isNotEmpty && uri.path != '/') {
          route = uri.path;
        }
    }

    if (route != null) {
      router.go(route);
    }
  }
}
