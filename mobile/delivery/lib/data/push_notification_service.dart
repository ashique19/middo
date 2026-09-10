import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../router/app_router.dart';
import 'auth_store.dart';
import 'delivery_repository.dart';
import 'middo_haptics.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
}

/// FCM for delivery rider alerts (`type=staff_alert`).
class PushNotificationService {
  PushNotificationService._();
  static final instance = PushNotificationService._();

  bool _ready = false;
  String? _token;
  DeliveryRepository? _repository;
  String? _pendingPath;

  bool get isReady => _ready;
  String? get token => _token;

  Future<void> init() async {
    if (kIsWeb) return;

    try {
      await Firebase.initializeApp();
      FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);

      final messaging = FirebaseMessaging.instance;

      await messaging.setForegroundNotificationPresentationOptions(
        alert: true,
        badge: true,
        sound: true,
      );

      final settings = await messaging.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );

      if (settings.authorizationStatus == AuthorizationStatus.denied) {
        debugPrint('Push: notification permission denied');
        return;
      }

      FirebaseMessaging.onMessage.listen(_onForegroundMessage);
      FirebaseMessaging.onMessageOpenedApp.listen(_onMessageOpened);
      messaging.onTokenRefresh.listen((token) {
        _token = token;
        _syncTokenQuietly();
      });

      final initial = await messaging.getInitialMessage();
      if (initial != null) {
        _pendingPath = _pathFrom(initial.data);
      }

      _ready = true;
      debugPrint('Push: Firebase ready');
    } catch (e, st) {
      debugPrint('Push: Firebase unavailable — continuing without FCM ($e)');
      debugPrint('$st');
      _ready = false;
    }
  }

  void attachRepository(DeliveryRepository repository) {
    _repository = repository;
  }

  Future<void> syncWithBackend() async {
    if (!_ready || !AuthStore.instance.isAuthenticated) return;

    try {
      final messaging = FirebaseMessaging.instance;
      final token = await messaging.getToken();
      if (token == null || token.isEmpty) return;

      _token = token;
      final repo = _repository;
      if (repo == null) return;

      await repo.registerDeviceToken(
        token: token,
        platform: Platform.isIOS ? 'ios' : 'android',
        deviceName: Platform.isAndroid ? 'android' : 'ios',
      );
      debugPrint('Push: device token registered');
    } catch (e) {
      debugPrint('Push: token sync failed ($e)');
    }
  }

  Future<void> unregisterFromBackend() async {
    final token = _token;
    final repo = _repository;
    if (token == null || repo == null) return;

    try {
      await repo.unregisterDeviceToken(token: token);
    } catch (e) {
      debugPrint('Push: unregister failed ($e)');
    }
  }

  void consumePendingDeepLink(GoRouter router) {
    final path = _pendingPath;
    if (path == null || path.isEmpty) return;
    if (!AuthStore.instance.isAuthenticated) return;

    _pendingPath = null;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      router.go(path);
    });
  }

  void _onForegroundMessage(RemoteMessage message) {
    MiddoHaptics.light();
    final copy = _copyFor(message);
    final path = _pathFrom(message.data);

    final ctx = rootNavigatorKey.currentContext;
    if (ctx == null) return;

    final messenger = ScaffoldMessenger.maybeOf(ctx);
    messenger?.hideCurrentSnackBar();
    messenger?.showSnackBar(
      SnackBar(
        content: Text(
          copy.$2.isEmpty ? copy.$1 : '${copy.$1} — ${copy.$2}',
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
        ),
        action: path == null
            ? null
            : SnackBarAction(
                label: 'Open',
                onPressed: () => GoRouter.of(ctx).go(path),
              ),
        duration: const Duration(seconds: 5),
      ),
    );
  }

  void _onMessageOpened(RemoteMessage message) {
    final path = _pathFrom(message.data);
    if (path == null) return;

    final ctx = rootNavigatorKey.currentContext;
    if (ctx != null && AuthStore.instance.isAuthenticated) {
      GoRouter.of(ctx).go(path);
    } else {
      _pendingPath = path;
    }
  }

  Future<void> _syncTokenQuietly() async {
    try {
      await syncWithBackend();
    } catch (_) {}
  }

  (String, String) _copyFor(RemoteMessage message) {
    final alertType = message.data['alert_type']?.toString() ??
        message.data['staff_alert_type']?.toString() ??
        '';
    final fallbackTitle = message.notification?.title ?? 'Middo Delivery';
    final fallbackBody = message.notification?.body ?? '';

    return switch (alertType) {
      'run_assigned' || 'lunch_dispatch' => (
          'New run assigned',
          fallbackBody.isEmpty
              ? 'Open Runs to pick up from the kitchen.'
              : fallbackBody,
        ),
      'box_pending' || 'ops_to_kitchen_box' || 'empty_box_pickup' => (
          'Box action needed',
          fallbackBody.isEmpty
              ? 'Check Boxes for pending handoffs.'
              : fallbackBody,
        ),
      'custom_run' => (
          'Custom run update',
          fallbackBody.isEmpty
              ? 'Open More → Custom runs.'
              : fallbackBody,
        ),
      'cash_collect' => (
          'Cash to collect',
          fallbackBody.isEmpty
              ? 'Open Cash to record collection.'
              : fallbackBody,
        ),
      _ => (fallbackTitle, fallbackBody),
    };
  }

  String? _pathFrom(Map<String, dynamic> data) {
    final type = data['type']?.toString();
    if (type != 'staff_alert' && type != null && type.isNotEmpty) {
      return null;
    }

    final explicit = data['path']?.toString();
    if (explicit != null && explicit.startsWith('/')) return explicit;

    final deep = data['deep_link']?.toString();
    if (deep != null && deep.startsWith('middo-delivery://')) {
      final uri = Uri.tryParse(deep);
      if (uri != null) {
        final host = uri.host;
        if (host.isNotEmpty) {
          if (uri.pathSegments.isEmpty) return '/$host';
          return '/$host/${uri.pathSegments.join('/')}';
        }
      }
    }

    final alertType = data['alert_type']?.toString() ??
        data['staff_alert_type']?.toString() ??
        '';
    final runId = data['run_id']?.toString() ?? data['order_id']?.toString();

    if (runId != null &&
        runId.isNotEmpty &&
        (alertType == 'run_assigned' ||
            alertType == 'lunch_dispatch' ||
            alertType == 'custom_run')) {
      return '/runs/$runId';
    }

    return switch (alertType) {
      'box_pending' ||
      'ops_to_kitchen_box' ||
      'empty_box_pickup' ||
      'kitchen_to_ops_box' =>
        '/boxes',
      'cash_collect' => '/cash',
      'custom_run' => '/more',
      'run_assigned' || 'lunch_dispatch' => '/runs',
      _ => '/alerts',
    };
  }
}
