import 'dart:async';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/foundation.dart';

/// App-wide network reachability. Offline banner listens to [onChange].
class NetworkStatus {
  NetworkStatus._();
  static final instance = NetworkStatus._();

  final _controller = StreamController<bool>.broadcast();
  StreamSubscription<List<ConnectivityResult>>? _sub;
  bool _online = true;
  var _started = false;

  bool get isOnline => _online;
  Stream<bool> get onChange => _controller.stream;

  Future<void> start() async {
    if (_started) return;
    _started = true;
    if (kIsWeb) {
      // Browser handles offline separately; assume online until a request fails.
      _online = true;
      return;
    }
    try {
      final initial = await Connectivity().checkConnectivity();
      _setOnline(_hasConnection(initial));
      _sub = Connectivity().onConnectivityChanged.listen((results) {
        _setOnline(_hasConnection(results));
      });
    } catch (_) {
      _online = true;
    }
  }

  void markRequestFailed() {
    // Soft signal used when connectivity plugin is unavailable (e.g. web).
    if (kIsWeb && _online) {
      _setOnline(false);
      Future<void>.delayed(const Duration(seconds: 8), () {
        if (!_controller.isClosed) _setOnline(true);
      });
    }
  }

  void markRequestSucceeded() {
    if (!_online) _setOnline(true);
  }

  void _setOnline(bool value) {
    if (_online == value) return;
    _online = value;
    _controller.add(value);
  }

  bool _hasConnection(List<ConnectivityResult> results) {
    if (results.isEmpty) return false;
    return results.any((r) => r != ConnectivityResult.none);
  }

  Future<void> dispose() async {
    await _sub?.cancel();
    await _controller.close();
  }
}
