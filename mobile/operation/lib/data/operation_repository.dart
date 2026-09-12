import 'api_client.dart';
import 'auth_store.dart';

class OperationRepository {
  OperationRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  ApiClient get client => _client;

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

  Future<Map<String, dynamic>> boards({String? date}) => _client.get(
      '/boards',
      query: {
        if (date != null && date.isNotEmpty) 'date': date,
      },
    );

  Future<Map<String, dynamic>> alerts() => _client.get('/alerts');

  Future<Map<String, dynamic>> markAlertRead(int id) =>
      _client.patch('/alerts/$id/read');

  Future<Map<String, dynamic>> markAllAlertsRead() =>
      _client.post('/alerts/read-all');

  Future<Map<String, dynamic>> registerDeviceToken({
    required String token,
    String? platform,
    String? deviceName,
  }) =>
      _client.post('/device-tokens', body: {
        'token': token,
        if (platform != null) 'platform': platform,
        if (deviceName != null) 'device_name': deviceName,
      });

  Future<Map<String, dynamic>> unregisterDeviceToken(String token) =>
      _client.delete('/device-tokens', body: {'token': token});

  Future<Map<String, dynamic>> boxes({String? custody, String? search}) =>
      _client.get('/boxes', query: {
        if (custody != null) 'custody': custody,
        if (search != null && search.isNotEmpty) 'search': search,
      });

  Future<Map<String, dynamic>> boxRequests() => _client.get('/boxes/requests');

  Future<Map<String, dynamic>> lookupBoxByQr(String qr) =>
      _client.get('/boxes/lookup', query: {'qr': qr.trim()});

  Future<Map<String, dynamic>> assignBoxRequest({
    required int requestId,
    required List<int> boxIds,
    required int riderId,
  }) =>
      _client.post('/boxes/requests/$requestId/assign', body: {
        'box_ids': boxIds,
        'rider_id': riderId,
      });

  Future<Map<String, dynamic>> reassignBox({
    required int boxId,
    required String kind,
    required int riderId,
    int? requestId,
    int? kitchenId,
  }) =>
      _client.post('/boxes/$boxId/reassign', body: {
        'kind': kind,
        'rider_id': riderId,
        if (requestId != null) 'request_id': requestId,
        if (kitchenId != null) 'kitchen_id': kitchenId,
      });

  Future<Map<String, dynamic>> ackBoxReturn(int boxId) =>
      _client.post('/boxes/$boxId/ack-return');

  Future<Map<String, dynamic>> ridersBoard() => _client.get('/riders/board');

  Future<Map<String, dynamic>> assignLunchRider({
    required int orderId,
    required int riderId,
  }) =>
      _client.post('/orders/$orderId/assign-rider', body: {
        'rider_id': riderId,
      });

  Future<Map<String, dynamic>> reassignLunchRider({
    required int orderId,
    required int riderId,
    String? reason,
  }) =>
      _client.post('/orders/$orderId/reassign-rider', body: {
        'rider_id': riderId,
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });

  Future<Map<String, dynamic>> createCustomRun({
    required String fromLabel,
    required String toLabel,
    required int riderId,
    int? areaId,
    int? commissionAmount,
    String? notes,
  }) =>
      _client.post('/custom-runs', body: {
        'from_label': fromLabel,
        'to_label': toLabel,
        'rider_id': riderId,
        if (areaId != null) 'area_id': areaId,
        if (commissionAmount != null) 'commission_amount': commissionAmount,
        if (notes != null) 'notes': notes,
      });

  Future<Map<String, dynamic>> cancelCustomRun(int id) =>
      _client.post('/custom-runs/$id/cancel');

  Future<Map<String, dynamic>> cashHandovers({String status = 'pending'}) =>
      _client.get('/cash-handovers', query: {'status': status});

  Future<Map<String, dynamic>> acceptCashHandover(int id) =>
      _client.post('/cash-handovers/$id/accept');

  Future<Map<String, dynamic>> rejectCashHandover(int id, {String? reason}) =>
      _client.post('/cash-handovers/$id/reject', body: {
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });

  Future<Map<String, dynamic>> slaBoard() => _client.get('/sla');

  Future<Map<String, dynamic>> assignKitchen({
    required int groupId,
    required int kitchenId,
  }) =>
      _client.post('/order-groups/$groupId/assign-kitchen', body: {
        'kitchen_id': kitchenId,
      });

  Future<Map<String, dynamic>> bulkAssignKitchen({
    required List<int> groupIds,
    required int kitchenId,
  }) =>
      _client.post('/order-groups/bulk-assign-kitchen', body: {
        'group_ids': groupIds,
        'kitchen_id': kitchenId,
      });

  Future<Map<String, dynamic>> opsDay({String? date}) =>
      _client.get('/ops-day', query: {
        if (date != null) 'date': date,
      });

  Future<Map<String, dynamic>> complaints({String status = 'open'}) =>
      _client.get('/complaints', query: {'status': status});

  Future<Map<String, dynamic>> showComplaint(int id) =>
      _client.get('/complaints/$id');

  Future<Map<String, dynamic>> replyComplaint(int id, {required String message}) =>
      _client.post('/complaints/$id/reply', body: {'message': message});

  Future<Map<String, dynamic>> completeComplaint(int id) =>
      _client.post('/complaints/$id/complete');

  Future<Map<String, dynamic>> searchOrders(String q, {String package = 'all'}) =>
      _client.get('/orders/search', query: {'q': q, 'package': package});

  Future<Map<String, dynamic>> showOrder(int id) => _client.get('/orders/$id');

  Future<Map<String, dynamic>> forceCancelOrder(int id, {String? reason}) =>
      _client.post('/orders/$id/force-cancel', body: {
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });

  Future<Map<String, dynamic>> releaseRider(int id, {String? reason}) =>
      _client.post('/orders/$id/release-rider', body: {
        if (reason != null && reason.isNotEmpty) 'reason': reason,
      });
}

OperationRepository createOperationRepository() => OperationRepository();
