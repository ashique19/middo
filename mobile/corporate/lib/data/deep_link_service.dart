import 'package:app_links/app_links.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:go_router/go_router.dart';

import 'auth_store.dart';

/// Handles `middo://` deep links (payment return, track, checkout).
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
    if (uri.scheme != 'middo') return;

    final host = uri.host.toLowerCase();
    final path = uri.path.toLowerCase();

    // middo://pay/result?status=paid
    if (host == 'pay' || path.startsWith('/pay')) {
      final status = (uri.queryParameters['status'] ?? 'success').toLowerCase();
      final success =
          status == 'paid' || status == 'success' || status == 'credited';
      final qp = <String, String>{
        'ok': success ? '1' : '0',
        'title': uri.queryParameters['title'] ?? 'Payment',
      };
      final message = uri.queryParameters['message'];
      if (message != null && message.isNotEmpty) qp['message'] = message;
      router.go(Uri(path: '/payment/result', queryParameters: qp).toString());
      return;
    }

    if (!AuthStore.instance.isAuthenticated) {
      _pending = uri;
      router.go('/login');
      return;
    }

    // middo://track/123
    if (host == 'track' || path.startsWith('/track')) {
      final orderId = uri.queryParameters['order_id'] ??
          (uri.pathSegments.isNotEmpty ? uri.pathSegments.last : null);
      if (orderId != null && orderId.isNotEmpty) {
        router.go('/track/$orderId');
      }
      return;
    }

    // middo://checkout/m1
    if (host == 'checkout' || path.startsWith('/checkout')) {
      final menuId = uri.queryParameters['menu_item_id'] ??
          (uri.pathSegments.isNotEmpty ? uri.pathSegments.last : null);
      if (menuId != null && menuId.isNotEmpty) {
        router.go('/checkout/$menuId');
      }
      return;
    }

    for (final tab in ['home', 'menu', 'wallet', 'schedule', 'history', 'boxes']) {
      if (host == tab || path == '/$tab') {
        router.go('/$tab');
        return;
      }
    }
  }
}
