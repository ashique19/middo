import 'dart:io';

import 'package:flutter/foundation.dart';

import 'operation_repository.dart';

/// FCM scaffold for Operation.
///
/// Full Firebase init is deferred until a `com.middo.operation` Firebase Android
/// app + `google-services.json` exist. Call [syncToken] after login once FCM
/// is wired; [registerManualToken] supports sideload/debug token injection.
class PushNotificationService {
  PushNotificationService._();
  static final instance = PushNotificationService._();

  OperationRepository? _repository;
  String? _token;
  bool ready = false;

  String? get token => _token;

  void attachRepository(OperationRepository repository) {
    _repository = repository;
  }

  Future<void> init() async {
    // Firebase packages intentionally not required yet — see README.
    ready = false;
    debugPrint(
      'Push: Operation FCM scaffold idle (add Firebase app for com.middo.operation).',
    );
  }

  Future<void> registerManualToken(String token) async {
    _token = token;
    await syncToken();
  }

  Future<void> syncToken() async {
    final repo = _repository;
    final token = _token;
    if (repo == null || token == null || token.isEmpty) return;
    try {
      await repo.registerDeviceToken(
        token: token,
        platform: kIsWeb
            ? 'web'
            : (Platform.isIOS ? 'ios' : 'android'),
        deviceName: 'operation-app',
      );
    } catch (e) {
      debugPrint('Push: token sync failed ($e)');
    }
  }

  Future<void> unregister() async {
    final repo = _repository;
    final token = _token;
    if (repo == null || token == null || token.isEmpty) return;
    try {
      await repo.unregisterDeviceToken(token);
    } catch (_) {}
  }
}
