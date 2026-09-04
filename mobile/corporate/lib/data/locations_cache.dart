import 'dart:convert';

import 'package:shared_preferences/shared_preferences.dart';

import '../models/models.dart';

/// Persist city/area lists so register/profile work offline briefly.
class LocationsCache {
  LocationsCache._();
  static final instance = LocationsCache._();

  static const _key = 'middo_corporate_locations_v1';
  static const _ttl = Duration(days: 7);

  Future<List<LocationCity>?> read() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final raw = prefs.getString(_key);
      if (raw == null || raw.isEmpty) return null;
      final decoded = jsonDecode(raw) as Map<String, dynamic>;
      final savedAt = DateTime.tryParse(decoded['saved_at']?.toString() ?? '');
      if (savedAt == null || DateTime.now().difference(savedAt) > _ttl) {
        return null;
      }
      final cities = (decoded['cities'] as List? ?? [])
          .whereType<Map>()
          .map((e) => LocationCity.fromJson(Map<String, dynamic>.from(e)))
          .toList();
      return cities.isEmpty ? null : cities;
    } catch (_) {
      return null;
    }
  }

  Future<void> write(List<LocationCity> cities) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString(
        _key,
        jsonEncode({
          'saved_at': DateTime.now().toIso8601String(),
          'cities': cities
              .map(
                (c) => {
                  'id': c.id,
                  'name': c.name,
                  'areas': c.areas
                      .map((a) => {'id': a.id, 'name': a.name})
                      .toList(),
                },
              )
              .toList(),
        }),
      );
    } catch (_) {}
  }
}
