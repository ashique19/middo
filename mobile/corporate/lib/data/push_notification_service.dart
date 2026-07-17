import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../router/app_router.dart';
import 'auth_store.dart';
import 'corporate_repository.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
}

/// Handles FCM init, token sync with Middo API, and order deep-links.
///
/// Gracefully no-ops when Firebase is not configured (placeholder
/// `google-services.json` or missing server key on the backend).
class PushNotificationService {
  PushNotificationService._();
  static final instance = PushNotificationService._();

  bool _ready = false;
  String? _token;
  CorporateRepository? _repository;
  String? _pendingOrderId;

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
        _pendingOrderId = _orderIdFrom(initial.data);
      }

      _ready = true;
      debugPrint('Push: Firebase ready');
    } catch (e, st) {
      debugPrint('Push: Firebase unavailable — continuing without FCM ($e)');
      debugPrint('$st');
      _ready = false;
    }
  }

  void attachRepository(CorporateRepository repository) {
    _repository = repository;
  }

  /// Call after login or when restoring an authenticated session.
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

  /// Unregister before clearing the auth session on logout.
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

  /// Navigate to a pending deep-link once the router is ready.
  void consumePendingDeepLink(GoRouter router) {
    final orderId = _pendingOrderId;
    if (orderId == null || orderId.isEmpty) return;
    if (!AuthStore.instance.isAuthenticated) return;

    _pendingOrderId = null;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      router.go('/track/$orderId');
    });
  }

  void _onForegroundMessage(RemoteMessage message) {
    final title = message.notification?.title ?? 'Middo';
    final body = message.notification?.body ?? '';
    final orderId = _orderIdFrom(message.data);

    final ctx = rootNavigatorKey.currentContext;
    if (ctx == null) return;

    final messenger = ScaffoldMessenger.maybeOf(ctx);
    messenger?.hideCurrentSnackBar();
    messenger?.showSnackBar(
      SnackBar(
        content: Text(
          body.isEmpty ? title : '$title — $body',
          maxLines: 3,
          overflow: TextOverflow.ellipsis,
        ),
        action: orderId == null
            ? null
            : SnackBarAction(
                label: 'Track',
                onPressed: () => GoRouter.of(ctx).push('/track/$orderId'),
              ),
        duration: const Duration(seconds: 5),
      ),
    );
  }

  void _onMessageOpened(RemoteMessage message) {
    final orderId = _orderIdFrom(message.data);
    if (orderId == null) return;

    final ctx = rootNavigatorKey.currentContext;
    if (ctx != null && AuthStore.instance.isAuthenticated) {
      GoRouter.of(ctx).push('/track/$orderId');
    } else {
      _pendingOrderId = orderId;
    }
  }

  Future<void> _syncTokenQuietly() async {
    try {
      await syncWithBackend();
    } catch (_) {}
  }

  String? _orderIdFrom(Map<String, dynamic> data) {
    final raw = data['order_id']?.toString();
    if (raw == null || raw.isEmpty) return null;
    return raw;
  }
}
