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

  Future<Map<String, dynamic>> dashboard();

  Future<List<dynamic>> alerts();

  /// Active order groups (kitchen-owned), each with nested `orders`.
  Future<List<dynamic>> activeOrderGroups();

  Future<List<dynamic>> orderGroups();

  Future<Map<String, dynamic>> menusToday();

  Future<Map<String, dynamic>> shoppingList();

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
  Future<Map<String, dynamic>> dashboard() => _client.get('/dashboard');

  @override
  Future<List<dynamic>> alerts() async {
    final data = await _client.get('/alerts');
    return (data['alerts'] as List?) ?? (data['data'] as List?) ?? const [];
  }

  @override
  Future<List<dynamic>> activeOrderGroups() async {
    final data = await _client.get('/orders/active');
    return (data['groups'] as List?) ?? const [];
  }

  @override
  Future<List<dynamic>> orderGroups() async {
    final data = await _client.get('/order-groups');
    return (data['groups'] as List?) ?? const [];
  }

  @override
  Future<Map<String, dynamic>> menusToday() => _client.get('/menus/today');

  @override
  Future<Map<String, dynamic>> shoppingList() =>
      _client.get('/prep/shopping-list');

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
    'role': 'kitchen',
  };

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
  Future<Map<String, dynamic>> dashboard() async => {
        'tiles': [
          {'key': 'alerts', 'label': 'Alerts', 'count': 1},
          {'key': 'preparing', 'label': 'Preparing', 'count': 2},
          {'key': 'ready_for_pickup', 'label': 'Ready for pickup', 'count': 1},
          {'key': 'active_orders', 'label': 'My active orders', 'count': 3},
          {'key': 'claimable_groups', 'label': 'Middo order groups', 'count': 2},
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
        },
      ];

  @override
  Future<List<dynamic>> activeOrderGroups() async => [
        {
          'id': 101,
          'name': 'GRP-ACTIVE',
          'menu_name': 'Lunch Box',
          'total_quantity': 4,
          'orders': [
            {
              'id': 201,
              'order_status': 'processing',
              'quantity': 2,
              'area_name': 'Gulshan',
            },
          ],
        },
      ];

  @override
  Future<List<dynamic>> orderGroups() async => [
        {
          'id': 11,
          'name': 'GRP-DEMO',
          'menu_name': 'Lunch Box',
          'total_quantity': 6,
          'can_accept': true,
        },
      ];

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
  Future<void> registerDeviceToken({
    required String token,
    required String platform,
    String? deviceName,
  }) async {}

  @override
  Future<void> unregisterDeviceToken({required String token}) async {}
}
