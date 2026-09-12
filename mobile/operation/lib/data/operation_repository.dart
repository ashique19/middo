import 'api_client.dart';
import 'auth_store.dart';

class OperationRepository {
  OperationRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
    String deviceName = 'operation-app',
  }) async {
    final data = await _client.post(
      '/login',
      auth: false,
      body: {
        'mobile': mobile,
        'password': password,
        'device_name': deviceName,
      },
    );
    final token = data['token']?.toString();
    if (token == null || token.isEmpty) {
      throw ApiException('Login response missing token');
    }
    await AuthStore.instance.saveToken(token);
    return data;
  }

  Future<void> logout() async {
    try {
      await _client.post('/logout');
    } finally {
      await AuthStore.instance.clear();
    }
  }

  Future<Map<String, dynamic>> me() => _client.get('/me');

  Future<Map<String, dynamic>> dashboard() => _client.get('/dashboard');

  Future<Map<String, dynamic>> boxRequests() => _client.get('/boxes/requests');

  Future<Map<String, dynamic>> lookupBoxByQr(String qr) =>
      _client.get('/boxes/lookup', query: {'qr': qr.trim()});

  Future<Map<String, dynamic>> ridersBoard() => _client.get('/riders/board');

  Future<Map<String, dynamic>> cashHandovers({String status = 'pending'}) =>
      _client.get('/cash-handovers', query: {'status': status});

  Future<Map<String, dynamic>> acceptCashHandover(int id) =>
      _client.post('/cash-handovers/$id/accept');

  Future<Map<String, dynamic>> slaBoard() => _client.get('/sla');

  Future<Map<String, dynamic>> opsDay({String? date}) =>
      _client.get('/ops-day', query: {
        if (date != null) 'date': date,
      });

  Future<Map<String, dynamic>> complaints({String status = 'open'}) =>
      _client.get('/complaints', query: {'status': status});
}

OperationRepository createOperationRepository() => OperationRepository();
