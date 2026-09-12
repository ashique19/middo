import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import 'api_client.dart';

/// Local FIFO queue for ops mutations when offline.
class OfflineMutationQueue {
  OfflineMutationQueue._();
  static final instance = OfflineMutationQueue._();

  static const _key = 'middo_operation_offline_queue_v1';

  Future<List<Map<String, dynamic>>> peek() async {
    final prefs = await SharedPreferences.getInstance();
    final raw = prefs.getString(_key);
    if (raw == null || raw.isEmpty) return [];
    final decoded = jsonDecode(raw);
    if (decoded is! List) return [];
    return decoded
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  Future<int> count() async => (await peek()).length;

  Future<void> enqueue({
    required String method,
    required String path,
    Map<String, dynamic>? body,
    String? label,
  }) async {
    final items = await peek();
    items.add({
      'id': DateTime.now().microsecondsSinceEpoch.toString(),
      'method': method,
      'path': path,
      'body': body,
      'label': label ?? '$method $path',
      'queued_at': DateTime.now().toIso8601String(),
    });
    await _save(items);
  }

  Future<void> _save(List<Map<String, dynamic>> items) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(items));
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }

  /// Flush queued mutations FIFO. Returns flushed / remaining / last error.
  Future<Map<String, dynamic>> flush(ApiClient client) async {
    final items = await peek();
    if (items.isEmpty) {
      return {'flushed': 0, 'remaining': 0};
    }

    final remaining = <Map<String, dynamic>>[];
    var flushed = 0;
    String? lastError;

    for (var i = 0; i < items.length; i++) {
      final item = items[i];
      final method = (item['method']?.toString() ?? 'POST').toUpperCase();
      final path = item['path']?.toString() ?? '';
      final body = item['body'] is Map
          ? Map<String, dynamic>.from(item['body'] as Map)
          : null;
      if (path.isEmpty) continue;

      try {
        switch (method) {
          case 'PATCH':
            await client.patch(path, body: body);
          case 'DELETE':
            await client.delete(path, body: body);
          default:
            await client.post(path, body: body);
        }
        flushed++;
      } on ApiException catch (e) {
        lastError = e.message;
        remaining.addAll(items.sublist(i));
        break;
      } catch (e) {
        lastError = e.toString();
        remaining.addAll(items.sublist(i));
        break;
      }
    }

    await _save(remaining);
    return {
      'flushed': flushed,
      'remaining': remaining.length,
      if (lastError != null) 'error': lastError,
    };
  }
}
