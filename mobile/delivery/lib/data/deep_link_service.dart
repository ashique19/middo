import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import 'auth_store.dart';

/// Handles `middo-delivery://` deep links from pushes / shared URLs.
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
    if (uri.scheme != 'middo-delivery') return;

    if (!AuthStore.instance.isAuthenticated) {
      _pending = uri;
      router.go('/login');
      return;
    }

    final host = uri.host.toLowerCase();
    final segments = uri.pathSegments;

    String? route;
    switch (host) {
      case 'runs':
        if (segments.isNotEmpty) {
          route = '/runs/${segments.first}';
        } else {
          route = '/runs';
        }
      case 'boxes':
        route = '/boxes';
      case 'cash':
        route = '/cash';
      case 'alerts':
        route = '/alerts';
      case 'account':
        route = '/account';
      case 'profile':
        route = '/profile';
      case 'history':
        final period = uri.queryParameters['period'] ?? 'this_month';
        route = '/history?period=$period';
      case 'home':
        route = '/home';
      case 'more':
        route = '/more';
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
