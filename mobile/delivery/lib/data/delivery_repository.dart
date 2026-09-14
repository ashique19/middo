import 'api_client.dart';
import 'api_config.dart';
import 'auth_store.dart';
import 'network_status.dart';
import 'offline_mutation_queue.dart';

abstract class DeliveryRepository {
  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
    String deviceName = 'delivery-app',
  });

  Future<void> logout();

  Future<Map<String, dynamic>> me();

  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  });

  Future<void> registerDeviceToken({
    required String token,
    required String platform,
    String? deviceName,
  });

  Future<void> unregisterDeviceToken({required String token});

  Future<Map<String, dynamic>> dashboard();

  Future<Map<String, dynamic>> setShift(String status);

  Future<List<dynamic>> alerts();

  Future<int> unreadAlertCount();

  Future<void> markAlertRead(int id);

  Future<void> markAllAlertsRead();

  Future<List<dynamic>> runs();

  Future<Map<String, dynamic>> showRun(int id);

  Future<Map<String, dynamic>> pickupRun(int id);

  Future<Map<String, dynamic>> sendDeliveryOtp(int id);

  Future<Map<String, dynamic>> deliverRun(
    int id, {
    required String otp,
    String? podPhotoPath,
  });

  Future<Map<String, dynamic>> pendingBoxes();

  Future<void> acceptWarehouse(int boxId);

  Future<void> handToKitchen(int boxId);

  Future<void> acceptKitchenReturn(int boxId);

  Future<void> handToOps(int boxId);

  Future<void> collectEmpty(int boxId);

  Future<void> acceptAllBoxes(int requestId);

  Future<void> handAllBoxes(int requestId);

  Future<List<dynamic>> deliveredOrders();

  Future<Map<String, dynamic>> collectCash(
    int orderId, {
    required int amount,
    String? notes,
  });

  Future<Map<String, dynamic>> cashHandovers();

  Future<Map<String, dynamic>> createCashHandover({
    required List<int> orderIds,
    required String target,
    String? notes,
  });

  Future<Map<String, dynamic>> account();

  Future<Map<String, dynamic>> withdraw({String? notes});

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> body);

  Future<Map<String, dynamic>> sendPaymentLink(int orderId, {String? phone});

  Future<Map<String, dynamic>> updateRunEta(int runId, {required int etaMinutes});

  Future<List<dynamic>> customRuns();

  Future<Map<String, dynamic>> startCustomRun(int id);

  Future<Map<String, dynamic>> completeCustomRun(int id);

  Future<Map<String, dynamic>> runsHistory({String period = 'this_month'});
}

DeliveryRepository createDeliveryRepository() {
  if (ApiConfig.useMock) {
    return MockDeliveryRepository();
  }
  return ApiDeliveryRepository(ApiClient());
}

class ApiDeliveryRepository implements DeliveryRepository {
  ApiDeliveryRepository(this._client);

  final ApiClient _client;

  Future<Map<String, dynamic>?> _enqueueIfOffline({
    required String type,
    required String method,
    required String path,
    Map<String, dynamic>? body,
    String? fileField,
    String? filePath,
  }) async {
    if (NetworkStatus.instance.isOnline) return null;
    return OfflineMutationQueue.instance.enqueue(
      type: type,
      method: method,
      path: path,
      body: body,
      fileField: fileField,
      filePath: filePath,
    );
  }

  @override
  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
    String deviceName = 'delivery-app',
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

  @override
  Future<void> logout() async {
    try {
      await _client.post('/logout');
    } finally {
      await AuthStore.instance.clear();
    }
  }

  @override
  Future<Map<String, dynamic>> me() => _client.get('/me');

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _client.post('/change-password', body: {
      'current_password': currentPassword,
      'password': password,
      'password_confirmation': passwordConfirmation,
    });
  }

  @override
  Future<void> registerDeviceToken({
    required String token,
    required String platform,
    String? deviceName,
  }) async {
    await _client.post('/device-tokens', body: {
      'token': token,
      'platform': platform,
      if (deviceName != null) 'device_name': deviceName,
    });
  }

  @override
  Future<void> unregisterDeviceToken({required String token}) async {
    await _client.delete('/device-tokens', body: {'token': token});
  }

  @override
  Future<Map<String, dynamic>> dashboard() => _client.get('/dashboard');

  @override
  Future<Map<String, dynamic>> setShift(String status) =>
      _client.post('/shift', body: {'status': status});

  @override
  Future<List<dynamic>> alerts() async {
    final data = await _client.get('/alerts');
    return (data['alerts'] as List?) ?? (data['data'] as List?) ?? const [];
  }

  @override
  Future<int> unreadAlertCount() async {
    final data = await _client.get('/alerts');
    return (data['unread_count'] as num?)?.toInt() ?? 0;
  }

  @override
  Future<void> markAlertRead(int id) async {
    await _client.patch('/alerts/$id/read');
  }

  @override
  Future<void> markAllAlertsRead() async {
    await _client.patch('/alerts/read-all');
  }

  @override
  Future<List<dynamic>> runs() async {
    final data = await _client.get('/runs');
    return (data['runs'] as List?) ?? (data['data'] as List?) ?? const [];
  }

  @override
  Future<Map<String, dynamic>> showRun(int id) => _client.get('/runs/$id');

  @override
  Future<Map<String, dynamic>> pickupRun(int id) async {
    final queued = await _enqueueIfOffline(
      type: 'pickup',
      method: 'POST',
      path: '/runs/$id/pickup',
      body: const {},
    );
    if (queued != null) return queued;
    return _client.post('/runs/$id/pickup');
  }

  @override
  Future<Map<String, dynamic>> sendDeliveryOtp(int id) =>
      _client.post('/runs/$id/send-delivery-otp');

  @override
  Future<Map<String, dynamic>> deliverRun(
    int id, {
    required String otp,
    String? podPhotoPath,
  }) async {
    final body = <String, dynamic>{'otp': otp};
    final queued = await _enqueueIfOffline(
      type: 'deliver',
      method: 'POST',
      path: '/runs/$id/deliver',
      body: body,
      fileField: podPhotoPath != null ? 'pod_photo' : null,
      filePath: podPhotoPath,
    );
    if (queued != null) return queued;

    if (podPhotoPath != null && podPhotoPath.isNotEmpty) {
      return _client.postMultipart(
        '/runs/$id/deliver',
        fields: {'otp': otp},
        fileField: 'pod_photo',
        filePath: podPhotoPath,
      );
    }
    return _client.post('/runs/$id/deliver', body: body);
  }

  @override
  Future<Map<String, dynamic>> pendingBoxes() =>
      _client.get('/boxes/pending');

  @override
  Future<void> acceptWarehouse(int boxId) async {
    final queued = await _enqueueIfOffline(
      type: 'box_accept_warehouse',
      method: 'POST',
      path: '/boxes/$boxId/accept-warehouse',
      body: const {},
    );
    if (queued != null) return;
    await _client.post('/boxes/$boxId/accept-warehouse');
  }

  @override
  Future<void> handToKitchen(int boxId) async {
    final queued = await _enqueueIfOffline(
      type: 'box_hand_to_kitchen',
      method: 'POST',
      path: '/boxes/$boxId/hand-to-kitchen',
      body: const {},
    );
    if (queued != null) return;
    await _client.post('/boxes/$boxId/hand-to-kitchen');
  }

  @override
  Future<void> acceptKitchenReturn(int boxId) async {
    final queued = await _enqueueIfOffline(
      type: 'box_accept_kitchen_return',
      method: 'POST',
      path: '/boxes/$boxId/accept-kitchen-return',
      body: const {},
    );
    if (queued != null) return;
    await _client.post('/boxes/$boxId/accept-kitchen-return');
  }

  @override
  Future<void> handToOps(int boxId) async {
    final queued = await _enqueueIfOffline(
      type: 'box_hand_to_ops',
      method: 'POST',
      path: '/boxes/$boxId/hand-to-ops',
      body: const {},
    );
    if (queued != null) return;
    await _client.post('/boxes/$boxId/hand-to-ops');
  }

  @override
  Future<void> collectEmpty(int boxId) async {
    final queued = await _enqueueIfOffline(
      type: 'box_collect_empty',
      method: 'POST',
      path: '/boxes/$boxId/collect-empty',
      body: const {},
    );
    if (queued != null) return;
    await _client.post('/boxes/$boxId/collect-empty');
  }

  @override
  Future<void> acceptAllBoxes(int requestId) async {
    final queued = await _enqueueIfOffline(
      type: 'box_accept_all',
      method: 'POST',
      path: '/boxes/requests/$requestId/accept-all',
      body: const {},
    );
    if (queued != null) return;
    await _client.post('/boxes/requests/$requestId/accept-all');
  }

  @override
  Future<void> handAllBoxes(int requestId) async {
    final queued = await _enqueueIfOffline(
      type: 'box_hand_all',
      method: 'POST',
      path: '/boxes/requests/$requestId/hand-all',
      body: const {},
    );
    if (queued != null) return;
    await _client.post('/boxes/requests/$requestId/hand-all');
  }

  @override
  Future<List<dynamic>> deliveredOrders() async {
    final data = await _client.get('/orders/delivered');
    return (data['orders'] as List?) ?? (data['data'] as List?) ?? const [];
  }

  @override
  Future<Map<String, dynamic>> collectCash(
    int orderId, {
    required int amount,
    String? notes,
  }) async {
    final body = <String, dynamic>{
      'amount': amount,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    };
    final queued = await _enqueueIfOffline(
      type: 'collect_cash',
      method: 'POST',
      path: '/orders/$orderId/collect-cash',
      body: body,
    );
    if (queued != null) return queued;
    return _client.post('/orders/$orderId/collect-cash', body: body);
  }

  @override
  Future<Map<String, dynamic>> cashHandovers() =>
      _client.get('/cash-handovers');

  @override
  Future<Map<String, dynamic>> createCashHandover({
    required List<int> orderIds,
    required String target,
    String? notes,
  }) =>
      _client.post('/cash-handovers', body: {
        'order_ids': orderIds,
        'target': target,
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      });

  @override
  Future<Map<String, dynamic>> account() => _client.get('/account');

  @override
  Future<Map<String, dynamic>> withdraw({String? notes}) =>
      _client.post('/account/withdraw', body: {
        if (notes != null && notes.isNotEmpty) 'notes': notes,
      });

  @override
  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> body) =>
      _client.patch('/profile', body: body);

  @override
  Future<Map<String, dynamic>> sendPaymentLink(int orderId, {String? phone}) =>
      _client.post('/orders/$orderId/send-payment-link', body: {
        if (phone != null && phone.isNotEmpty) 'phone': phone,
      });

  @override
  Future<Map<String, dynamic>> updateRunEta(int runId,
          {required int etaMinutes}) =>
      _client.post('/runs/$runId/eta', body: {'eta_minutes': etaMinutes});

  @override
  Future<List<dynamic>> customRuns() async {
    final data = await _client.get('/custom-runs');
    return (data['custom_runs'] as List?) ??
        (data['data'] as List?) ??
        const [];
  }

  @override
  Future<Map<String, dynamic>> startCustomRun(int id) =>
      _client.post('/custom-runs/$id/start');

  @override
  Future<Map<String, dynamic>> completeCustomRun(int id) =>
      _client.post('/custom-runs/$id/complete');

  @override
  Future<Map<String, dynamic>> runsHistory({String period = 'this_month'}) =>
      _client.get('/runs/history', query: {'period': period});
}

class MockDeliveryRepository implements DeliveryRepository {
  Map<String, dynamic> _user = {
    'id': 4,
    'first_name': 'Demo',
    'last_name': 'Rider',
    'mobile': '01310123454',
    'email': 'delivery@middo.test',
    'role': 'delivery',
    'rider_shift_status': 'on',
  };

  String _shift = 'on';
  int _balance = 1800;
  int _cashOnHand = 950;
  int _dueToMiddo = 450;

  final List<Map<String, dynamic>> _alerts = [
    {
      'id': 1,
      'type': 'run_assigned',
      'title': 'New kitchen dispatch',
      'body': 'Order #501 is ready for pickup at Gulshan Kitchen.',
      'read_at': null,
      'is_unread': true,
      'run_id': 101,
    },
    {
      'id': 2,
      'type': 'box_pending',
      'title': 'Boxes staged for you',
      'body': '3 warehouse boxes waiting at ops.',
      'read_at': null,
      'is_unread': true,
    },
  ];

  final List<Map<String, dynamic>> _runs = [
    {
      'id': 101,
      'type': 'lunch',
      'status': 'ready_for_pickup',
      'label': 'Lunch · Gulshan Kitchen → Banani',
      'kitchen_name': 'Gulshan Kitchen',
      'kitchen_mobile': '01710000001',
      'kitchen_address': 'Road 7, Gulshan',
      'area_name': 'Banani',
      'receiver_name': 'Acme Corp',
      'receiver_phone': '01711112222',
      'address': 'House 12, Road 5, Banani',
      'menu_name': 'Lunch Box',
      'quantity': 4,
      'can_pickup': true,
      'can_deliver': false,
      'cash_due': 0,
      'commission_amount': 40,
      'show_commission': true,
      'payment_method_label': 'Online',
      'box_codes': ['BOX-101-A', 'BOX-101-B'],
      'eta_minutes': null,
      'eta_label': null,
    },
    {
      'id': 102,
      'type': 'lunch',
      'status': 'picked_up',
      'label': 'Lunch · Banani Kitchen → Gulshan',
      'kitchen_name': 'Banani Kitchen',
      'kitchen_mobile': '01710000002',
      'kitchen_address': 'Road 11, Banani',
      'area_name': 'Gulshan',
      'receiver_name': 'Beta Ltd',
      'receiver_phone': '01733334444',
      'address': 'Plot 8, Road 11, Gulshan-2',
      'menu_name': 'Veg Thali',
      'quantity': 2,
      'can_pickup': false,
      'can_deliver': true,
      'cash_due': 450,
      'commission_amount': 45,
      'show_commission': true,
      'payment_method_label': 'Cash on delivery',
      'box_codes': ['BOX-102-A'],
      'eta_minutes': null,
      'eta_label': null,
    },
  ];

  final List<Map<String, dynamic>> _pendingBoxes = [
    {
      'id': 11,
      'qr_code_id': 'BOX-W-11',
      'action': 'accept_warehouse',
      'action_label': 'Accept from warehouse',
      'request_id': 55,
      'location': 'Ops warehouse',
      'can_accept_warehouse': true,
      'can_hand_to_kitchen': false,
      'can_accept_kitchen_return': false,
      'can_hand_to_ops': false,
      'can_collect_empty': false,
    },
    {
      'id': 12,
      'qr_code_id': 'BOX-W-12',
      'action': 'accept_warehouse',
      'action_label': 'Accept from warehouse',
      'request_id': 55,
      'location': 'Ops warehouse',
      'can_accept_warehouse': true,
      'can_hand_to_kitchen': false,
      'can_accept_kitchen_return': false,
      'can_hand_to_ops': false,
      'can_collect_empty': false,
    },
    {
      'id': 21,
      'qr_code_id': 'BOX-K-21',
      'action': 'hand_to_kitchen',
      'action_label': 'Hand to kitchen',
      'request_id': null,
      'location': 'In custody',
      'kitchen_name': 'Gulshan Kitchen',
      'can_accept_warehouse': false,
      'can_hand_to_kitchen': true,
      'can_accept_kitchen_return': false,
      'can_hand_to_ops': false,
      'can_collect_empty': false,
    },
    {
      'id': 31,
      'qr_code_id': 'BOX-R-31',
      'action': 'accept_kitchen_return',
      'action_label': 'Accept kitchen return',
      'request_id': null,
      'location': 'Gulshan Kitchen',
      'can_accept_warehouse': false,
      'can_hand_to_kitchen': false,
      'can_accept_kitchen_return': true,
      'can_hand_to_ops': false,
      'can_collect_empty': false,
    },
    {
      'id': 41,
      'qr_code_id': 'BOX-E-41',
      'action': 'hand_to_ops',
      'action_label': 'Hand to ops',
      'request_id': null,
      'location': 'In custody (empty)',
      'can_accept_warehouse': false,
      'can_hand_to_kitchen': false,
      'can_accept_kitchen_return': false,
      'can_hand_to_ops': true,
      'can_collect_empty': false,
    },
    {
      'id': 51,
      'qr_code_id': 'BOX-C-51',
      'action': 'collect_empty',
      'action_label': 'Collect empty',
      'request_id': null,
      'location': 'Customer site',
      'can_accept_warehouse': false,
      'can_hand_to_kitchen': false,
      'can_accept_kitchen_return': false,
      'can_hand_to_ops': false,
      'can_collect_empty': true,
    },
  ];

  final List<Map<String, dynamic>> _delivered = [
    {
      'id': 501,
      'menu_name': 'Lunch Box',
      'receiver_name': 'Acme Corp',
      'area_name': 'Banani',
      'delivered_at': '2026-09-10 13:05',
      'cash_due': 0,
      'cash_collected': true,
      'can_collect_cash': false,
      'commission_open': 0,
      'projected_commission': 0,
      'projected_due_to_middo': 0,
    },
    {
      'id': 502,
      'menu_name': 'Veg Thali',
      'receiver_name': 'Beta Ltd',
      'area_name': 'Gulshan',
      'delivered_at': '2026-09-10 12:40',
      'cash_due': 450,
      'cash_collected': false,
      'can_collect_cash': true,
      'commission_open': 45,
      'projected_commission': 45,
      'projected_due_to_middo': 405,
    },
  ];

  final List<Map<String, dynamic>> _eligibleOrders = [
    {
      'id': 502,
      'menu_name': 'Veg Thali',
      'due_to_middo': 405,
    },
  ];

  final List<Map<String, dynamic>> _handovers = [
    {
      'id': 77,
      'amount': 500,
      'target': 'kitchen',
      'status': 'pending',
      'notes': 'End of morning shift',
      'created_at': '2026-09-10 11:00',
      'order_ids': [499],
    },
  ];

  final List<Map<String, dynamic>> _withdrawals = [
    {
      'id': 3,
      'amount': 600,
      'status': 'paid',
      'created_at': '2026-09-01 10:00',
    },
  ];

  final List<Map<String, dynamic>> _statement = [
    {
      'id': 1,
      'label': 'Commission · run #90',
      'amount': 120,
      'created_at': '2026-09-05 14:00',
    },
    {
      'id': 2,
      'label': 'Withdrawal',
      'amount': -600,
      'created_at': '2026-09-01 10:00',
    },
  ];

  final List<Map<String, dynamic>> _customRuns = [
    {
      'id': 9,
      'title': 'Docs to Banani HQ',
      'status': 'pending',
      'from_label': 'Ops warehouse',
      'to_label': 'Banani HQ',
      'can_start': true,
      'can_complete': false,
    },
    {
      'id': 10,
      'title': 'Spare boxes to kitchen',
      'status': 'started',
      'from_label': 'Ops',
      'to_label': 'Gulshan Kitchen',
      'can_start': false,
      'can_complete': true,
    },
  ];

  @override
  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
    String deviceName = 'delivery-app',
  }) async {
    await AuthStore.instance.saveToken('mock-delivery-token');
    return {'token': 'mock-delivery-token', 'user': _user};
  }

  @override
  Future<void> logout() async {
    await AuthStore.instance.clear();
  }

  @override
  Future<Map<String, dynamic>> me() async => {
        'user': {..._user, 'rider_shift_status': _shift},
      };

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {}

  @override
  Future<void> registerDeviceToken({
    required String token,
    required String platform,
    String? deviceName,
  }) async {}

  @override
  Future<void> unregisterDeviceToken({required String token}) async {}

  @override
  Future<Map<String, dynamic>> dashboard() async => {
        'shift_status': _shift,
        'shift_label': switch (_shift) {
          'off' => 'Off shift',
          'unable' => 'Unable',
          _ => 'On shift',
        },
        'shift_options': {
          'on': 'On shift',
          'off': 'Off shift',
          'unable': 'Unable',
        },
        'tiles': [
          {'key': 'alerts', 'label': 'Alerts', 'count': 2},
          {'key': 'runs', 'label': 'Kitchen dispatches', 'count': _runs.length},
          {
            'key': 'custom_runs',
            'label': 'Custom runs',
            'count': _customRuns
                .where((r) => r['status'] != 'completed')
                .length,
          },
          {
            'key': 'boxes',
            'label': 'Middo boxes pending',
            'count': _pendingBoxes.length,
          },
          {
            'key': 'delivered',
            'label': 'Delivered orders',
            'count': _delivered.length,
          },
          {'key': 'cash', 'label': 'Cash on hand', 'count': _cashOnHand},
        ],
      };

  @override
  Future<Map<String, dynamic>> setShift(String status) async {
    _shift = status;
    _user = {..._user, 'rider_shift_status': status};
    return {
      'message': 'Shift set to $status.',
      'shift_status': status,
    };
  }

  @override
  Future<List<dynamic>> alerts() async => List<dynamic>.from(_alerts);

  @override
  Future<int> unreadAlertCount() async =>
      _alerts.where((a) => a['is_unread'] == true).length;

  @override
  Future<void> markAlertRead(int id) async {
    final i = _alerts.indexWhere((a) => a['id'] == id);
    if (i >= 0) {
      _alerts[i] = {
        ..._alerts[i],
        'is_unread': false,
        'read_at': DateTime.now().toIso8601String(),
      };
    }
  }

  @override
  Future<void> markAllAlertsRead() async {
    for (var i = 0; i < _alerts.length; i++) {
      _alerts[i] = {
        ..._alerts[i],
        'is_unread': false,
        'read_at': DateTime.now().toIso8601String(),
      };
    }
  }

  @override
  Future<List<dynamic>> runs() async => List<dynamic>.from(_runs);

  @override
  Future<Map<String, dynamic>> showRun(int id) async {
    final run = _runs.cast<Map<String, dynamic>?>().firstWhere(
          (r) => r?['id'] == id,
          orElse: () => null,
        );
    if (run == null) throw ApiException('Run #$id not found', statusCode: 404);
    return {'run': run};
  }

  @override
  Future<Map<String, dynamic>> pickupRun(int id) async {
    final i = _runs.indexWhere((r) => r['id'] == id);
    if (i < 0) throw ApiException('Run not found', statusCode: 404);
    _runs[i] = {
      ..._runs[i],
      'status': 'picked_up',
      'can_pickup': false,
      'can_deliver': true,
    };
    return {'message': 'Picked up run #$id.', 'run': _runs[i]};
  }

  @override
  Future<Map<String, dynamic>> sendDeliveryOtp(int id) async {
    final exists = _runs.any((r) => r['id'] == id);
    if (!exists) throw ApiException('Run not found', statusCode: 404);
    return {
      'message': 'Delivery OTP sent.',
      'debug_otp': '1234',
    };
  }

  @override
  Future<Map<String, dynamic>> deliverRun(
    int id, {
    required String otp,
    String? podPhotoPath,
  }) async {
    if (otp != '1234') {
      throw ApiException('Invalid OTP', statusCode: 422);
    }
    // Photo optional; mock always accepts when present.
    final i = _runs.indexWhere((r) => r['id'] == id);
    if (i < 0) throw ApiException('Run not found', statusCode: 404);
    final run = _runs[i];
    final cashDue = (run['cash_due'] as num?)?.toInt() ?? 0;
    final commission = (run['commission_amount'] as num?)?.toInt() ?? 0;
    _runs.removeAt(i);
    _delivered.insert(0, {
      'id': 600 + id,
      'menu_name': run['menu_name'],
      'receiver_name': run['receiver_name'],
      'area_name': run['area_name'],
      'delivered_at': DateTime.now().toIso8601String(),
      'cash_due': cashDue,
      'cash_collected': cashDue == 0,
      'can_collect_cash': cashDue > 0,
      'commission_open': commission,
      'projected_commission': commission,
      'projected_due_to_middo': cashDue > commission ? cashDue - commission : 0,
    });
    return {
      'message': 'Delivered run #$id.',
      if (podPhotoPath != null) 'pod_photo_received': true,
    };
  }

  @override
  Future<Map<String, dynamic>> pendingBoxes() async {
    final runGroups = [
      {
        'id': 55,
        'request_id': 55,
        'label': 'Warehouse staging #55',
        'title': 'Warehouse staging #55',
        'box_count': 2,
        'can_accept_all': true,
        'can_hand_all': false,
      },
    ];
    return {
      'boxes': List<dynamic>.from(_pendingBoxes),
      'run_groups': runGroups,
      'requests': runGroups,
    };
  }

  @override
  Future<void> acceptWarehouse(int boxId) async {
    _pendingBoxes.removeWhere((b) => b['id'] == boxId);
  }

  @override
  Future<void> handToKitchen(int boxId) async {
    _pendingBoxes.removeWhere((b) => b['id'] == boxId);
  }

  @override
  Future<void> acceptKitchenReturn(int boxId) async {
    final i = _pendingBoxes.indexWhere((b) => b['id'] == boxId);
    if (i >= 0) {
      _pendingBoxes[i] = {
        ..._pendingBoxes[i],
        'action': 'hand_to_ops',
        'action_label': 'Hand to ops',
        'can_accept_kitchen_return': false,
        'can_hand_to_ops': true,
      };
    }
  }

  @override
  Future<void> handToOps(int boxId) async {
    _pendingBoxes.removeWhere((b) => b['id'] == boxId);
  }

  @override
  Future<void> collectEmpty(int boxId) async {
    final i = _pendingBoxes.indexWhere((b) => b['id'] == boxId);
    if (i >= 0) {
      _pendingBoxes[i] = {
        ..._pendingBoxes[i],
        'action': 'hand_to_ops',
        'action_label': 'Hand to ops',
        'can_collect_empty': false,
        'can_hand_to_ops': true,
      };
    }
  }

  @override
  Future<void> acceptAllBoxes(int requestId) async {
    _pendingBoxes.removeWhere(
      (b) => b['request_id'] == requestId && b['can_accept_warehouse'] == true,
    );
  }

  @override
  Future<void> handAllBoxes(int requestId) async {
    _pendingBoxes.removeWhere((b) => b['request_id'] == requestId);
  }

  @override
  Future<List<dynamic>> deliveredOrders() async =>
      List<dynamic>.from(_delivered);

  @override
  Future<Map<String, dynamic>> collectCash(
    int orderId, {
    required int amount,
    String? notes,
  }) async {
    final i = _delivered.indexWhere((o) => o['id'] == orderId);
    if (i < 0) throw ApiException('Order not found', statusCode: 404);
    final due = (_delivered[i]['cash_due'] as num?)?.toInt() ?? 0;
    if (amount < due && (notes == null || notes.trim().isEmpty)) {
      throw ApiException('Notes required when amount is less than cash due');
    }
    final commission =
        (_delivered[i]['projected_commission'] as num?)?.toInt() ??
            (_delivered[i]['commission_open'] as num?)?.toInt() ??
            0;
    final dueMiddo = amount > commission ? amount - commission : 0;
    _delivered[i] = {
      ..._delivered[i],
      'cash_collected': amount >= due,
      'can_collect_cash': amount < due,
      'cash_due': amount >= due ? 0 : due - amount,
    };
    _cashOnHand += amount;
    _dueToMiddo += dueMiddo;
    if (dueMiddo > 0) {
      _eligibleOrders.removeWhere((o) => o['id'] == orderId);
      _eligibleOrders.insert(0, {
        'id': orderId,
        'menu_name': _delivered[i]['menu_name'],
        'due_to_middo': dueMiddo,
      });
    }
    return {
      'message': 'Collected ৳$amount.',
      'cash_on_hand': _cashOnHand,
      'due_to_middo': dueMiddo,
    };
  }

  @override
  Future<Map<String, dynamic>> cashHandovers() async => {
        'cash_on_hand': _cashOnHand,
        'due_to_middo': _dueToMiddo,
        'eligible_orders': List<dynamic>.from(_eligibleOrders),
        'handovers': List<dynamic>.from(_handovers),
      };

  @override
  Future<Map<String, dynamic>> createCashHandover({
    required List<int> orderIds,
    required String target,
    String? notes,
  }) async {
    if (orderIds.isEmpty) {
      throw ApiException('Select at least one order');
    }
    if (target != 'kitchen' && target != 'middo') {
      throw ApiException('Target must be kitchen or middo');
    }
    final selected = _eligibleOrders
        .where((o) => orderIds.contains((o['id'] as num).toInt()))
        .toList();
    if (selected.isEmpty) {
      throw ApiException('No eligible orders selected');
    }
    final amount = selected.fold<int>(
      0,
      (sum, o) => sum + ((o['due_to_middo'] as num?)?.toInt() ?? 0),
    );
    _dueToMiddo = (_dueToMiddo - amount).clamp(0, 1 << 30);
    _cashOnHand = (_cashOnHand - amount).clamp(0, 1 << 30);
    _eligibleOrders.removeWhere(
      (o) => orderIds.contains((o['id'] as num).toInt()),
    );
    final row = {
      'id': 80 + _handovers.length,
      'amount': amount,
      'target': target,
      'status': 'pending',
      'notes': notes,
      'order_ids': orderIds,
      'created_at': DateTime.now().toIso8601String(),
    };
    _handovers.insert(0, row);
    return {
      'message': 'Due handover #${row['id']} submitted for $target acceptance.',
      'handover': row,
      'cash_on_hand': _cashOnHand,
    };
  }

  @override
  Future<Map<String, dynamic>> account() async {
    final canRequest = _balance > 0 && _dueToMiddo == 0;
    return {
      'balance': _balance,
      'cash_on_hand': _dueToMiddo,
      'due_to_middo': _dueToMiddo,
      'receivable': _balance > 0 ? _balance : 0,
      'can_request_payment': canRequest,
      'has_complete_payout_method': true,
      'preferred_payout_channel': 'bkash',
      'statement': List<dynamic>.from(_statement),
      'withdrawals': List<dynamic>.from(_withdrawals),
    };
  }

  @override
  Future<Map<String, dynamic>> withdraw({String? notes}) async {
    if (_dueToMiddo > 0) {
      throw ApiException('Clear Due to Middo before withdrawing');
    }
    if (_balance <= 0) {
      throw ApiException('Nothing to withdraw');
    }
    final amount = _balance;
    _balance = 0;
    final row = {
      'id': 10 + _withdrawals.length,
      'amount': amount,
      'status': 'pending',
      'created_at': DateTime.now().toIso8601String(),
      'notes': notes,
    };
    _withdrawals.insert(0, row);
    return {
      'message': 'Withdrawal submitted.',
      'withdrawal': row,
      'balance': _balance,
    };
  }

  @override
  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> body) async {
    final methods = (body['payout_methods'] as Map?)?.cast<String, dynamic>() ??
        <String, dynamic>{};
    _user = {
      ..._user,
      if (body['email'] != null) 'email': body['email'],
      'preferred_payout_channel':
          methods['preferred'] ?? body['preferred_payout_channel'] ?? 'bkash',
      'has_complete_payout_method': true,
      'payout_methods': methods,
    };
    return {
      'message': 'Profile updated.',
      'user': _user,
      'account': await account(),
    };
  }

  @override
  Future<Map<String, dynamic>> sendPaymentLink(int orderId,
      {String? phone}) async {
    return {
      'message': 'Payment link sent to ${phone ?? '01710123456'}.',
      'payment_url': 'https://middo.test/pay/$orderId',
      'phone': phone ?? '01710123456',
      'sms_sent': true,
    };
  }

  @override
  Future<Map<String, dynamic>> updateRunEta(int runId,
      {required int etaMinutes}) async {
    final i = _runs.indexWhere((r) => r['id'] == runId);
    if (i < 0) throw ApiException('Run not found', statusCode: 404);
    final label = 'About $etaMinutes min';
    _runs[i] = {
      ..._runs[i],
      'eta_minutes': etaMinutes,
      'eta_label': label,
    };
    return {
      'message': 'ETA updated to about $etaMinutes minutes.',
      'run': _runs[i],
      'eta_minutes': etaMinutes,
      'eta_label': label,
    };
  }

  @override
  Future<List<dynamic>> customRuns() async =>
      List<dynamic>.from(_customRuns);

  @override
  Future<Map<String, dynamic>> startCustomRun(int id) async {
    final i = _customRuns.indexWhere((r) => r['id'] == id);
    if (i < 0) throw ApiException('Custom run not found', statusCode: 404);
    _customRuns[i] = {
      ..._customRuns[i],
      'status': 'started',
      'can_start': false,
      'can_complete': true,
    };
    return {'message': 'Custom run started.', 'custom_run': _customRuns[i]};
  }

  @override
  Future<Map<String, dynamic>> completeCustomRun(int id) async {
    final i = _customRuns.indexWhere((r) => r['id'] == id);
    if (i < 0) throw ApiException('Custom run not found', statusCode: 404);
    _customRuns[i] = {
      ..._customRuns[i],
      'status': 'completed',
      'can_start': false,
      'can_complete': false,
    };
    return {'message': 'Custom run completed.', 'custom_run': _customRuns[i]};
  }

  @override
  Future<Map<String, dynamic>> runsHistory({
    String period = 'this_month',
  }) async =>
      {
        'period': period,
        'label': switch (period) {
          'last_month' => 'Last month',
          'last_3_months' => 'Last 3 months',
          _ => 'This month',
        },
        'runs': [
          {
            'id': 90,
            'label': 'Lunch · Banani → Gulshan',
            'status': 'delivered',
            'delivered_at': '2026-09-05 13:10',
            'menu_name': 'Lunch Box',
            'quantity': 3,
          },
          {
            'id': 91,
            'label': 'Custom · Docs drop',
            'status': 'completed',
            'delivered_at': '2026-09-03 16:20',
            'menu_name': null,
            'quantity': 1,
          },
        ],
        'meta': {
          'current_page': 1,
          'last_page': 1,
          'total': 2,
        },
      };
}
