import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

/// Phase 3.2 — local queue for ops mutations when offline.
///
/// Stores pending POST/PATCH payloads and flushes FIFO when connectivity
/// returns. Not auto-wired yet; Home/More expose pending count for pilot.
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
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_key, jsonEncode(items));
  }

  Future<void> clear() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_key);
  }
}
