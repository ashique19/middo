import 'dart:convert';

import 'package:http/http.dart' as http;

import 'api_config.dart';
import 'auth_store.dart';

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.errors});

  final String message;
  final int? statusCode;
  final Map<String, dynamic>? errors;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

  Future<Map<String, dynamic>> get(
    String path, {
    bool auth = true,
  }) =>
      _send('GET', path, auth: auth);

  Future<Map<String, dynamic>> post(
    String path, {
    Map<String, dynamic>? body,
    bool auth = true,
  }) =>
      _send('POST', path, body: body, auth: auth);

  Future<Map<String, dynamic>> patch(
    String path, {
    Map<String, dynamic>? body,
    bool auth = true,
  }) =>
      _send('PATCH', path, body: body, auth: auth);

  Future<Map<String, dynamic>> delete(
    String path, {
    Map<String, dynamic>? body,
    bool auth = true,
  }) =>
      _send('DELETE', path, body: body, auth: auth);

  Future<Map<String, dynamic>> _send(
    String method,
    String path, {
    Map<String, dynamic>? body,
    bool auth = true,
  }) async {
    final uri = Uri.parse('${ApiConfig.apiRoot}$path');
    final headers = <String, String>{
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };

    if (auth && AuthStore.instance.isAuthenticated) {
      headers['Authorization'] = 'Bearer ${AuthStore.instance.token}';
    }

    late http.Response response;
    try {
      switch (method) {
        case 'GET':
          response = await _client.get(uri, headers: headers);
        case 'PATCH':
          response = await _client.patch(
            uri,
            headers: headers,
            body: body == null ? null : jsonEncode(body),
          );
        case 'DELETE':
          response = await _client.delete(
            uri,
            headers: headers,
            body: body == null ? null : jsonEncode(body),
          );
        default:
          response = await _client.post(
            uri,
            headers: headers,
            body: body == null ? null : jsonEncode(body),
          );
      }
    } catch (_) {
      throw ApiException(
        'Could not reach Middo API at ${ApiConfig.baseUrl}. Is `php artisan serve` running?',
      );
    }

    final decoded = response.body.isEmpty
        ? <String, dynamic>{}
        : jsonDecode(response.body) as Map<String, dynamic>;

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded;
    }

    final message = decoded['message']?.toString() ??
        (decoded['errors'] is Map
            ? ((decoded['errors'] as Map).values.first is List
                ? ((decoded['errors'] as Map).values.first as List)
                    .first
                    .toString()
                : decoded['errors'].toString())
            : 'Request failed (${response.statusCode})');

    throw ApiException(
      message,
      statusCode: response.statusCode,
      errors: decoded['errors'] is Map
          ? Map<String, dynamic>.from(decoded['errors'] as Map)
          : null,
    );
  }
}
