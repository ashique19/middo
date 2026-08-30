import 'dart:io';

import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../router/app_router.dart';
import 'auth_store.dart';
import 'kitchen_repository.dart';

@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  try {
    await Firebase.initializeApp();
  } catch (_) {}
}

/// FCM for kitchen staff alerts (`type=staff_alert`).
class PushNotificationService {
  PushNotificationService._();
  static final instance = PushNotificationService._();

  bool _ready = false;
  String? _token;
  KitchenRepository? _repository;
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

      // Kitchen Android channel expected by SendStaffAlertPush / FcmClient.
      if (!kIsWeb && Platform.isAndroid) {
        await messaging.setForegroundNotificationPresentationOptions(
          alert: true,
          badge: true,
          sound: true,
        );
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

  void attachRepository(KitchenRepository repository) {
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
    final title = message.notification?.title ?? 'Middo Kitchen';
    final body = message.notification?.body ?? '';
    final path = _pathFrom(message.data);

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

  String? _pathFrom(Map<String, dynamic> data) {
    final type = data['type']?.toString();
    if (type == 'staff_alert') {
      final groupId = data['order_group_id']?.toString();
      if (groupId != null && groupId.isNotEmpty) {
        return '/groups';
      }
      return '/home';
    }
    return null;
  }
}
