import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';
import 'network_status.dart';

/// Persists safe delivery mutations while offline and flushes FIFO on reconnect.
class OfflineMutationQueue {
  OfflineMutationQueue._();
  static final instance = OfflineMutationQueue._();

  static const _prefsKey = 'delivery_offline_mutation_queue_v1';
  static const _prefsKeyFailed = 'delivery_offline_mutation_failed_v1';

  final _controller = StreamController<int>.broadcast();
  StreamSubscription<bool>? _netSub;
  List<Map<String, dynamic>> _items = [];
  List<Map<String, dynamic>> _failed = [];
  var _loaded = false;
  var _flushing = false;
  var _started = false;
  ApiClient? _client;

  Stream<int> get onChange => _controller.stream;
  int get pendingCount => _items.length;
  int get failedCount => _failed.length;
  List<Map<String, dynamic>> get items => List.unmodifiable(_items);
  List<Map<String, dynamic>> get failedItems => List.unmodifiable(_failed);

  Future<void> start({ApiClient? client}) async {
    if (_started) return;
    _started = true;
    _client = client ?? ApiClient();
    await _ensureLoaded();
    _netSub = NetworkStatus.instance.onChange.listen((online) {
      if (online) unawaited(flush());
    });
    if (NetworkStatus.instance.isOnline) {
      unawaited(flush());
    }
  }

  Future<Map<String, dynamic>> enqueue({
    required String type,
    required String method,
    required String path,
    Map<String, dynamic>? body,
    String? fileField,
    String? filePath,
  }) async {
    await _ensureLoaded();
    final item = <String, dynamic>{
      'id': _newUuid(),
      'type': type,
      'method': method.toUpperCase(),
      'path': path,
      'body': body ?? <String, dynamic>{},
      'created_at': DateTime.now().toUtc().toIso8601String(),
      'idempotency_key': _newUuid(),
      if (fileField != null) 'file_field': fileField,
      if (filePath != null) 'file_path': filePath,
    };
    _items = [..._items, item];
    await _persist();
    _controller.add(_items.length);
    return {
      'message': 'Saved — will sync when online',
      'queued': true,
      'queue_id': item['id'],
      'pending_count': _items.length,
    };
  }

  Future<void> flush() async {
    if (_flushing) return;
    if (!NetworkStatus.instance.isOnline) return;
    await _ensureLoaded();
    if (_items.isEmpty) return;

    _flushing = true;
    final client = _client ?? ApiClient();
    try {
      while (_items.isNotEmpty && NetworkStatus.instance.isOnline) {
        final item = _items.first;
        try {
          await _send(client, item);
          _items = _items.sublist(1);
          await _persist();
          _controller.add(_items.length);
        } on ApiException catch (e) {
          // Park permanent client failures for rider review; keep network / 5xx.
          final code = e.statusCode ?? 0;
          if (code >= 400 && code < 500 && code != 408 && code != 429) {
            final failed = {
              ...item,
              'failed_at': DateTime.now().toUtc().toIso8601String(),
              'error_status': code,
              'error_message': e.message,
            };
            _items = _items.sublist(1);
            _failed = [..._failed, failed];
            await _persist();
            _controller.add(_items.length);
            continue;
          }
          break;
        } catch (_) {
          break;
        }
      }
    } finally {
      _flushing = false;
    }
  }

  Future<void> _send(ApiClient client, Map<String, dynamic> item) async {
    final method = (item['method']?.toString() ?? 'POST').toUpperCase();
    final path = item['path']?.toString() ?? '';
    final body = (item['body'] is Map)
        ? Map<String, dynamic>.from(item['body'] as Map)
        : <String, dynamic>{};
    final key = item['idempotency_key']?.toString();
    final filePath = item['file_path']?.toString();
    final fileField = item['file_field']?.toString() ?? 'pod_photo';

    if (filePath != null && filePath.isNotEmpty) {
      final fields = <String, String>{};
      body.forEach((k, v) {
        if (v != null) fields[k] = v.toString();
      });
      await client.postMultipart(
        path,
        fields: fields,
        fileField: fileField,
        filePath: filePath,
        idempotencyKey: key,
      );
      return;
    }

    if (method == 'PATCH') {
      await client.patch(path, body: body, idempotencyKey: key);
    } else {
      await client.post(path, body: body, idempotencyKey: key);
    }
  }

  Future<void> _ensureLoaded() async {
    if (_loaded) return;
    if (kIsWeb) {
      _items = [];
      _loaded = true;
      return;
    }
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_prefsKey);
      if (raw != null && raw.isNotEmpty) {
        final decoded = jsonDecode(raw);
        if (decoded is List) {
          _items = decoded
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        }
      }
      final rawFailed = prefs.getString(_prefsKeyFailed);
      if (rawFailed != null && rawFailed.isNotEmpty) {
        final decoded = jsonDecode(rawFailed);
        if (decoded is List) {
          _failed = decoded
              .whereType<Map>()
              .map((e) => Map<String, dynamic>.from(e))
              .toList();
        }
      }
    } catch (_) {
      _items = [];
    }
    _loaded = true;
  }

  Future<void> _persist() async {
    if (kIsWeb) return;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_prefsKey, jsonEncode(_items));
    await prefs.setString(_prefsKeyFailed, jsonEncode(_failed));
  }

  String _newUuid() {
    final r = Random.secure();
    final bytes = List<int>.generate(16, (_) => r.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    String hex(int b) => b.toRadixString(16).padLeft(2, '0');
    final h = bytes.map(hex).join();
    return '${h.substring(0, 8)}-${h.substring(8, 12)}-'
        '${h.substring(12, 16)}-${h.substring(16, 20)}-${h.substring(20)}';
  }


  Future<void> retryFailed(String id) async {
    await _ensureLoaded();
    final idx = _failed.indexWhere((e) => e['id']?.toString() == id);
    if (idx < 0) return;
    final item = Map<String, dynamic>.from(_failed[idx]);
    item.remove('failed_at');
    item.remove('error_status');
    item.remove('error_message');
    _failed = [..._failed]..removeAt(idx);
    _items = [..._items, item];
    await _persist();
    _controller.add(_items.length);
    await flush();
  }

  Future<void> discardFailed(String id) async {
    await _ensureLoaded();
    _failed = _failed.where((e) => e['id']?.toString() != id).toList();
    await _persist();
    _controller.add(_items.length);
  }

  Future<void> discardAllFailed() async {
    await _ensureLoaded();
    _failed = [];
    await _persist();
    _controller.add(_items.length);
  }

  Future<void> dispose() async {
    await _netSub?.cancel();
    await _controller.close();
  }
}
