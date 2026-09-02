import 'api_client.dart';
import 'api_config.dart';
import 'auth_store.dart';

abstract class KitchenRepository {
  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
    String deviceName = 'kitchen-app',
  });

  Future<void> logout();

  Future<Map<String, dynamic>> me();

  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> body);

  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  });

  Future<Map<String, dynamic>> dashboard();

  Future<List<dynamic>> alerts();

  Future<void> markAlertRead(int id);

  Future<void> markAllAlertsRead();

  /// Active order groups (kitchen-owned), each with nested `orders`.
  Future<List<dynamic>> activeOrderGroups();

  Future<Map<String, dynamic>> orderGroupsPayload();

  Future<List<dynamic>> orderGroups();

  Future<Map<String, dynamic>> acceptOrderGroup(int id);

  Future<void> declineOrderGroup(int id, {required String reason});

  Future<void> releaseOrderGroup(int id);

  Future<void> reportShortage(int id, {required String reason});

  Future<Map<String, dynamic>> markGroupReady(int id);

  Future<Map<String, dynamic>> showOrder(int id);

  Future<Map<String, dynamic>> markOrderReady(int id);

  Future<Map<String, dynamic>> dispatchOptions(int id);

  Future<Map<String, dynamic>> dispatchOrder(int id, {required List<int> boxIds});

  Future<Map<String, dynamic>> menusToday();

  Future<Map<String, dynamic>> shoppingList();

  Future<Map<String, dynamic>> boxesAtKitchen();

  Future<Map<String, dynamic>> incomingBoxes();

  Future<void> receiveBox(int id);

  Future<void> markBoxDamaged(int id, {String? notes});

  Future<void> sendBoxToWarehouse(int id);

  Future<Map<String, dynamic>> requestBoxes({
    required int quantity,
    String? note,
  });

  Future<void> cancelBoxRequest(int id);

  Future<Map<String, dynamic>> account();

  Future<Map<String, dynamic>> requestWithdrawal({
    String? notes,
    String? payoutChannel,
  });

  Future<Map<String, dynamic>> transferToMiddo({
    required int amount,
    required String proofPath,
    String? reference,
    String? notes,
  });

  Future<Map<String, dynamic>> cashHandovers();

  Future<void> acceptCashHandover(int id);

  Future<void> rejectCashHandover(int id);

  Future<List<dynamic>> complaints();

  Future<Map<String, dynamic>> showComplaint(int id);

  Future<void> registerDeviceToken({
    required String token,
    required String platform,
    String? deviceName,
  });

  Future<void> unregisterDeviceToken({required String token});
}

KitchenRepository createKitchenRepository() {
  if (ApiConfig.useMock) {
    return MockKitchenRepository();
  }
  return ApiKitchenRepository(ApiClient());
}

class ApiKitchenRepository implements KitchenRepository {
  ApiKitchenRepository(this._client);

  final ApiClient _client;

  @override
  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
    String deviceName = 'kitchen-app',
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
  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> body) =>
      _client.patch('/profile', body: body);

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
  Future<Map<String, dynamic>> dashboard() => _client.get('/dashboard');

  @override
  Future<List<dynamic>> alerts() async {
    final data = await _client.get('/alerts');
    return (data['alerts'] as List?) ?? (data['data'] as List?) ?? const [];
  }

  @override
  Future<void> markAlertRead(int id) async {
    await _client.patch('/alerts/$id/read');
  }

  @override
  Future<void> markAllAlertsRead() async {
    await _client.post('/alerts/read-all');
  }

  @override
  Future<List<dynamic>> activeOrderGroups() async {
    final data = await _client.get('/orders/active');
    return (data['groups'] as List?) ?? const [];
  }

  @override
  Future<Map<String, dynamic>> orderGroupsPayload() =>
      _client.get('/order-groups');

  @override
  Future<List<dynamic>> orderGroups() async {
    final data = await orderGroupsPayload();
    return (data['groups'] as List?) ?? const [];
  }

  @override
  Future<Map<String, dynamic>> acceptOrderGroup(int id) =>
      _client.post('/order-groups/$id/accept');

  @override
  Future<void> declineOrderGroup(int id, {required String reason}) async {
    await _client.post('/order-groups/$id/decline', body: {'reason': reason});
  }

  @override
  Future<void> releaseOrderGroup(int id) async {
    await _client.post('/order-groups/$id/release');
  }

  @override
  Future<void> reportShortage(int id, {required String reason}) async {
    await _client
        .post('/order-groups/$id/shortage', body: {'reason': reason});
  }

  @override
  Future<Map<String, dynamic>> markGroupReady(int id) =>
      _client.post('/order-groups/$id/ready');

  @override
  Future<Map<String, dynamic>> showOrder(int id) => _client.get('/orders/$id');

  @override
  Future<Map<String, dynamic>> markOrderReady(int id) =>
      _client.post('/orders/$id/ready');

  @override
  Future<Map<String, dynamic>> dispatchOptions(int id) =>
      _client.get('/orders/$id/dispatch-options');

  @override
  Future<Map<String, dynamic>> dispatchOrder(
    int id, {
    required List<int> boxIds,
  }) =>
      _client.post('/orders/$id/dispatch', body: {'box_ids': boxIds});

  @override
  Future<Map<String, dynamic>> menusToday() => _client.get('/menus/today');

  @override
  Future<Map<String, dynamic>> shoppingList() =>
      _client.get('/prep/shopping-list');

  @override
  Future<Map<String, dynamic>> boxesAtKitchen() =>
      _client.get('/boxes/at-kitchen');

  @override
  Future<Map<String, dynamic>> incomingBoxes() =>
      _client.get('/boxes/incoming');

  @override
  Future<void> receiveBox(int id) async {
    await _client.post('/boxes/$id/receive');
  }

  @override
  Future<void> markBoxDamaged(int id, {String? notes}) async {
    await _client.post('/boxes/$id/damage', body: {
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    });
  }

  @override
  Future<void> sendBoxToWarehouse(int id) async {
    await _client.post('/boxes/$id/send-to-warehouse');
  }

  @override
  Future<Map<String, dynamic>> requestBoxes({
    required int quantity,
    String? note,
  }) =>
      _client.post('/boxes/request', body: {
        'quantity': quantity,
        if (note != null && note.isNotEmpty) 'note': note,
      });

  @override
  Future<void> cancelBoxRequest(int id) async {
    await _client.post('/boxes/requests/$id/cancel');
  }

  @override
  Future<Map<String, dynamic>> account() => _client.get('/account');

  @override
  Future<Map<String, dynamic>> requestWithdrawal({
    String? notes,
    String? payoutChannel,
  }) =>
      _client.post('/account/withdraw', body: {
        if (notes != null && notes.isNotEmpty) 'notes': notes,
        if (payoutChannel != null) 'payout_channel': payoutChannel,
      });

  @override
  Future<Map<String, dynamic>> transferToMiddo({
    required int amount,
    required String proofPath,
    String? reference,
    String? notes,
  }) =>
      _client.postMultipart(
        '/account/transfer-to-middo',
        fields: {
          'amount': '$amount',
          if (reference != null && reference.isNotEmpty) 'reference': reference,
          if (notes != null && notes.isNotEmpty) 'notes': notes,
        },
        fileField: 'proof',
        filePath: proofPath,
      );

  @override
  Future<Map<String, dynamic>> cashHandovers() =>
      _client.get('/cash-handovers');

  @override
  Future<void> acceptCashHandover(int id) async {
    await _client.post('/cash-handovers/$id/accept');
  }

  @override
  Future<void> rejectCashHandover(int id) async {
    await _client.post('/cash-handovers/$id/reject');
  }

  @override
  Future<List<dynamic>> complaints() async {
    final data = await _client.get('/complaints');
    return (data['complaints'] as List?) ?? const [];
  }

  @override
  Future<Map<String, dynamic>> showComplaint(int id) =>
      _client.get('/complaints/$id');

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
}

class MockKitchenRepository implements KitchenRepository {
  Map<String, dynamic> _user = {
    'id': 1,
    'first_name': 'Gulshan',
    'last_name': 'Kitchen',
    'mobile': '01310123453',
    'email': 'kitchen@middo.test',
    'address': 'Demo kitchen',
    'city_id': 1,
    'area_id': 1,
    'city': 'Dhaka',
    'area': 'Gulshan',
    'role': 'kitchen',
  };

  int _balance = 2500;

  @override
  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
    String deviceName = 'kitchen-app',
  }) async {
    await AuthStore.instance.saveToken('mock-kitchen-token');
    return {'token': 'mock-kitchen-token', 'user': _user};
  }

  @override
  Future<void> logout() async {
    await AuthStore.instance.clear();
  }

  @override
  Future<Map<String, dynamic>> me() async => {'user': _user};

  @override
  Future<Map<String, dynamic>> updateProfile(Map<String, dynamic> body) async {
    _user = {..._user, ...body};
    return {'user': _user, 'message': 'Profile updated.'};
  }

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {}

  @override
  Future<Map<String, dynamic>> dashboard() async => {
        'tiles': [
          {'key': 'alerts', 'label': 'Alerts', 'count': 1},
          {'key': 'preparing', 'label': 'Preparing', 'count': 2},
          {'key': 'ready_for_pickup', 'label': 'Ready for pickup', 'count': 1},
          {'key': 'active_orders', 'label': 'My active orders', 'count': 3},
          {'key': 'claimable_groups', 'label': 'Middo order groups', 'count': 2},
          {'key': 'boxes_in_stock', 'label': 'Boxes in Stock', 'count': 12},
        ],
        'insufficient_box_stock': false,
        'ops_incoming_notices': <dynamic>[],
        'capacity': {
          'open_groups': 1,
          'allowed_open_groups': 3,
          'remaining_slots': 2,
          'sendable_boxes': 12,
        },
      };

  @override
  Future<List<dynamic>> alerts() async => [
        {
          'id': 1,
          'type': 'group_assigned',
          'title': 'Order group assigned',
          'body': 'GRP-DEMO was assigned to your kitchen.',
          'read_at': null,
          'is_unread': true,
          'order_group_id': 11,
        },
      ];

  @override
  Future<void> markAlertRead(int id) async {}

  @override
  Future<void> markAllAlertsRead() async {}

  @override
  Future<List<dynamic>> activeOrderGroups() async => [
        {
          'id': 101,
          'name': 'GRP-ACTIVE',
          'menu_name': 'Lunch Box',
          'total_quantity': 4,
          'can_mark_group_ready': true,
          'can_release': true,
          'can_report_shortage': true,
          'orders': [
            {
              'id': 201,
              'order_status': 'processing',
              'quantity': 2,
              'area_name': 'Gulshan',
              'menu_name': 'Lunch Box',
              'can_mark_ready': true,
              'can_dispatch': false,
            },
            {
              'id': 202,
              'order_status': 'rider_assigned',
              'quantity': 2,
              'area_name': 'Banani',
              'menu_name': 'Lunch Box',
              'rider_name': 'Demo Rider',
              'can_mark_ready': false,
              'can_dispatch': true,
            },
          ],
        },
      ];

  @override
  Future<Map<String, dynamic>> orderGroupsPayload() async => {
        'groups': await orderGroups(),
        'capacity': {
          'remaining_slots': 2,
          'sendable_boxes': 12,
          'at_capacity': false,
        },
      };

  @override
  Future<List<dynamic>> orderGroups() async => [
        {
          'id': 11,
          'name': 'GRP-DEMO',
          'menu_name': 'Lunch Box',
          'total_quantity': 6,
          'can_accept': true,
          'needs_more_boxes': false,
          'accept_window': {'label': 'Open', 'is_open': true},
        },
      ];

  @override
  Future<Map<String, dynamic>> acceptOrderGroup(int id) async => {
        'message': 'Accepted GRP-DEMO.',
      };

  @override
  Future<void> declineOrderGroup(int id, {required String reason}) async {}

  @override
  Future<void> releaseOrderGroup(int id) async {}

  @override
  Future<void> reportShortage(int id, {required String reason}) async {}

  @override
  Future<Map<String, dynamic>> markGroupReady(int id) async => {
        'message': 'Marked 2 order(s) ready.',
        'marked': 2,
      };

  @override
  Future<Map<String, dynamic>> showOrder(int id) async => {
        'order': {
          'id': id,
          'order_status': 'processing',
          'quantity': 2,
          'area_name': 'Gulshan',
          'menu_name': 'Lunch Box',
          'can_mark_ready': true,
          'can_dispatch': false,
        },
      };

  @override
  Future<Map<String, dynamic>> markOrderReady(int id) async => {
        'message': 'Order #$id marked ready.',
      };

  @override
  Future<Map<String, dynamic>> dispatchOptions(int id) async => {
        'can_dispatch': true,
        'required_quantity': 2,
        'available_boxes': [
          {'id': 1, 'qr_code_id': 'BOX-001'},
          {'id': 2, 'qr_code_id': 'BOX-002'},
          {'id': 3, 'qr_code_id': 'BOX-003'},
        ],
        'order': {'id': id, 'quantity': 2},
      };

  @override
  Future<Map<String, dynamic>> dispatchOrder(
    int id, {
    required List<int> boxIds,
  }) async =>
      {'message': 'Order #$id packed and dispatched.'};

  @override
  Future<Map<String, dynamic>> menusToday() async => {
        'menus': [
          {'id': 1, 'name': 'Lunch Box', 'total_qty': 8, 'order_count': 4},
        ],
      };

  @override
  Future<Map<String, dynamic>> shoppingList() async => {
        'delivery_date': '2026-08-30',
        'ingredients': [
          {'name': 'Chicken', 'quantity': 4, 'unit': 'kg'},
          {'name': 'Rice', 'quantity': 6, 'unit': 'kg'},
        ],
      };

  @override
  Future<Map<String, dynamic>> boxesAtKitchen() async => {
        'count': 3,
        'boxes': [
          {'id': 1, 'qr_code_id': 'BOX-001', 'asset_status': 'active'},
          {'id': 2, 'qr_code_id': 'BOX-002', 'asset_status': 'active'},
          {'id': 3, 'qr_code_id': 'BOX-003', 'asset_status': 'active'},
        ],
      };

  @override
  Future<Map<String, dynamic>> incomingBoxes() async => {
        'boxes': [
          {
            'id': 9,
            'qr_code_id': 'BOX-IN-9',
            'can_receive': true,
            'latest_action': 'handed_to_kitchen',
          },
        ],
      };

  @override
  Future<void> receiveBox(int id) async {}

  @override
  Future<void> markBoxDamaged(int id, {String? notes}) async {}

  @override
  Future<void> sendBoxToWarehouse(int id) async {}

  @override
  Future<Map<String, dynamic>> requestBoxes({
    required int quantity,
    String? note,
  }) async =>
      {
        'message': 'Requested $quantity boxes.',
        'request': {'id': 55, 'quantity': quantity, 'status': 'pending'},
      };

  @override
  Future<void> cancelBoxRequest(int id) async {}

  @override
  Future<Map<String, dynamic>> account() async => {
        'balance': _balance,
        'receivable': _balance > 0 ? _balance : 0,
        'payable_to_middo': _balance < 0 ? -_balance : 0,
        'has_complete_payout_method': true,
        'preferred_payout_channel': 'bkash',
      };

  @override
  Future<Map<String, dynamic>> requestWithdrawal({
    String? notes,
    String? payoutChannel,
  }) async {
    final amount = _balance;
    _balance = 0;
    return {
      'message': 'Withdrawal submitted.',
      'withdrawal': {'id': 1, 'amount': amount, 'status': 'pending'},
      'balance': _balance,
    };
  }

  @override
  Future<Map<String, dynamic>> transferToMiddo({
    required int amount,
    required String proofPath,
    String? reference,
    String? notes,
  }) async =>
      {
        'message': 'Transfer submitted.',
        'transfer': {'id': 1, 'amount': amount, 'status': 'pending'},
      };

  @override
  Future<Map<String, dynamic>> cashHandovers() async => {
        'wallet_balance': _balance,
        'handovers': [
          {
            'id': 77,
            'amount': 500,
            'status': 'pending',
            'rider_name': 'Demo Rider',
            'rider_mobile': '01700000000',
            'item_count': 2,
          },
        ],
      };

  @override
  Future<void> acceptCashHandover(int id) async {
    _balance -= 500;
  }

  @override
  Future<void> rejectCashHandover(int id) async {}

  @override
  Future<List<dynamic>> complaints() async => [
        {
          'id': 3,
          'category_label': 'Food quality',
          'message': 'Too spicy',
          'status': 'open',
          'order_id': 201,
          'menu_name': 'Lunch Box',
        },
      ];

  @override
  Future<Map<String, dynamic>> showComplaint(int id) async => {
        'complaint': {
          'id': id,
          'category_label': 'Food quality',
          'status': 'open',
          'order_id': 201,
          'menu_name': 'Lunch Box',
          'thread': [
            {
              'id': id,
              'message': 'Too spicy',
              'created_by_name': 'Corporate',
              'is_root': true,
            },
          ],
        },
      };

  @override
  Future<void> registerDeviceToken({
    required String token,
    required String platform,
    String? deviceName,
  }) async {}

  @override
  Future<void> unregisterDeviceToken({required String token}) async {}
}
