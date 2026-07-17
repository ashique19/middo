import '../models/models.dart';
import 'api_client.dart';
import 'api_config.dart';
import 'auth_store.dart';
import 'mock_repository.dart';

abstract class CorporateRepository {
  Future<CorporateUser> login({
    required String mobile,
    required String password,
  });

  Future<CorporateUser> register({
    required String firstName,
    required String lastName,
    required String mobile,
    required String password,
    required String passwordConfirmation,
    required String companyName,
    required String address,
    required int cityId,
    required int areaId,
  });

  Future<String?> forgotPassword({required String mobile});

  Future<void> resetPassword({
    required String mobile,
    required String otp,
    required String password,
    required String passwordConfirmation,
  });

  Future<List<LocationCity>> locations();

  Future<void> logout();

  Future<CorporateUser> me();

  Future<CorporateUser> updateProfile({
    required String firstName,
    required String lastName,
    required String mobile,
    String? email,
    String? address,
    required int cityId,
    required int areaId,
  });

  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  });

  Future<void> registerDeviceToken({
    required String token,
    String platform = 'android',
    String? deviceName,
  });

  Future<void> unregisterDeviceToken({required String token});

  Future<DashboardData> dashboard();

  Future<List<MenuItem>> menu();

  Future<CheckoutMeta> checkoutMeta();

  Future<List<CorporateOrder>> scheduled();

  Future<List<CorporateOrder>> history();

  Future<OrderOtpResult> sendOrderOtp({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    String deliveryTime = '12:00 PM',
  });

  Future<GatewayPrepayResult> createGatewayPrepay({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    String deliveryTime = '12:00 PM',
  });

  Future<List<CorporateOrder>> placeOrder({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    required String otp,
    String deliveryTime = '12:00 PM',
    String? paymentMethod,
    String? paymentToken,
  });

  Future<CorporateOrder> updateOrder({
    required String orderId,
    required int quantity,
  });

  Future<void> cancelOrder(String orderId);

  Future<({CorporateOrder order, List<TrackEvent> events})> track(String orderId);

  Future<({CorporateOrder order, List<SupportMessage> messages, bool hasExisting})>
      supportThread(String orderId);

  Future<void> submitSupport({
    required String orderId,
    required String category,
    required String message,
  });

  Future<CorporateUser> topUp(double amount);

  MenuItem menuById(String id);
}

class ApiCorporateRepository implements CorporateRepository {
  ApiCorporateRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;
  final Map<String, MenuItem> _menuCache = {};

  @override
  Future<CorporateUser> login({
    required String mobile,
    required String password,
  }) async {
    final json = await _client.post(
      '/login',
      auth: false,
      body: {
        'mobile': mobile,
        'password': password,
        'device_name': 'middo-corporate-flutter',
      },
    );
    final token = json['token']?.toString();
    if (token == null || token.isEmpty) {
      throw ApiException('Login succeeded but no token was returned.');
    }
    await AuthStore.instance.saveToken(token);
    return CorporateUser.fromJson(
      Map<String, dynamic>.from(json['user'] as Map),
    );
  }

  @override
  Future<CorporateUser> register({
    required String firstName,
    required String lastName,
    required String mobile,
    required String password,
    required String passwordConfirmation,
    required String companyName,
    required String address,
    required int cityId,
    required int areaId,
  }) async {
    final json = await _client.post(
      '/register',
      auth: false,
      body: {
        'first_name': firstName,
        'last_name': lastName,
        'mobile': mobile,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'company_name': companyName,
        'address': address,
        'city_id': cityId,
        'area_id': areaId,
        'device_name': 'middo-corporate-flutter',
      },
    );
    final token = json['token']?.toString();
    if (token == null || token.isEmpty) {
      throw ApiException('Signup succeeded but no token was returned.');
    }
    await AuthStore.instance.saveToken(token);
    return CorporateUser.fromJson(
      Map<String, dynamic>.from(json['user'] as Map),
    );
  }

  @override
  Future<String?> forgotPassword({required String mobile}) async {
    final json = await _client.post(
      '/forgot-password',
      auth: false,
      body: {'mobile': mobile},
    );
    return json['debug_otp']?.toString();
  }

  @override
  Future<void> resetPassword({
    required String mobile,
    required String otp,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _client.post(
      '/reset-password',
      auth: false,
      body: {
        'mobile': mobile,
        'otp': otp,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
  }

  @override
  Future<List<LocationCity>> locations() async {
    final json = await _client.get('/locations', auth: false);
    return (json['cities'] as List? ?? [])
        .map((e) => LocationCity.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }

  @override
  Future<void> logout() async {
    try {
      await _client.post('/logout');
    } catch (_) {
      // Still clear local session if API is unreachable.
    }
    await AuthStore.instance.clear();
  }

  @override
  Future<CorporateUser> me() async {
    final json = await _client.get('/me');
    return CorporateUser.fromJson(
      Map<String, dynamic>.from(json['user'] as Map),
    );
  }

  @override
  Future<CorporateUser> updateProfile({
    required String firstName,
    required String lastName,
    required String mobile,
    String? email,
    String? address,
    required int cityId,
    required int areaId,
  }) async {
    final json = await _client.patch('/profile', body: {
      'first_name': firstName,
      'last_name': lastName,
      'mobile': mobile,
      'email': email,
      'address': address,
      'city_id': cityId,
      'area_id': areaId,
    });
    return CorporateUser.fromJson(
      Map<String, dynamic>.from(json['user'] as Map),
    );
  }

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
    String platform = 'android',
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
  Future<DashboardData> dashboard() async {
    final json = await _client.get('/dashboard');
    return DashboardData(
      user: CorporateUser.fromJson(
        Map<String, dynamic>.from(json['user'] as Map),
      ),
      metrics: DashboardMetrics.fromJson(
        Map<String, dynamic>.from(json['metrics'] as Map),
      ),
      upcomingOrders: _orders(json['upcoming_orders']),
      recentOrders: _orders(json['recent_orders']),
    );
  }

  @override
  Future<List<MenuItem>> menu() async {
    final json = await _client.get('/menu');
    final items = (json['items'] as List? ?? [])
        .map((e) => MenuItem.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
    _menuCache
      ..clear()
      ..addEntries(items.map((e) => MapEntry(e.id, e)));
    return items;
  }

  @override
  Future<CheckoutMeta> checkoutMeta() async {
    final json = await _client.get('/menu');
    return CheckoutMeta.fromJson(
      Map<String, dynamic>.from(json['checkout_meta'] as Map? ?? {}),
    );
  }

  @override
  Future<List<CorporateOrder>> scheduled() async {
    final json = await _client.get('/orders/scheduled');
    return _orders(json['orders']);
  }

  @override
  Future<List<CorporateOrder>> history() async {
    final json = await _client.get('/orders/history');
    return _orders(json['orders']);
  }

  List<Map<String, Object>> _datePayload(Map<DateTime, int> quantities) {
    return quantities.entries
        .where((e) => e.value > 0)
        .map(
          (e) => {
            'date':
                '${e.key.year.toString().padLeft(4, '0')}-${e.key.month.toString().padLeft(2, '0')}-${e.key.day.toString().padLeft(2, '0')}',
            'quantity': e.value,
          },
        )
        .toList();
  }

  Map<String, Object?> _orderBody({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    required String deliveryTime,
    String? otp,
    String? paymentMethod,
    String? paymentToken,
  }) {
    return {
      'menu_item_id': int.tryParse(menuItemId) ?? menuItemId,
      'delivery_time': deliveryTime,
      'dates': _datePayload(quantities),
      'receiver_name': receiver.receiverName,
      'mobile': receiver.mobile,
      'address': receiver.address,
      'city_id': receiver.cityId,
      'area_id': receiver.areaId,
      if (otp != null) 'otp': otp,
      if (paymentMethod != null) 'payment_method': paymentMethod,
      if (paymentToken != null) 'payment_token': paymentToken,
    };
  }

  @override
  Future<OrderOtpResult> sendOrderOtp({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    String deliveryTime = '12:00 PM',
  }) async {
    final json = await _client.post(
      '/orders/send-otp',
      body: _orderBody(
        menuItemId: menuItemId,
        quantities: quantities,
        receiver: receiver,
        deliveryTime: deliveryTime,
      ),
    );
    return OrderOtpResult(
      debugOtp: json['debug_otp']?.toString(),
      prepayment: PrepaymentQuote.fromJson(
        json['prepayment'] is Map
            ? Map<String, dynamic>.from(json['prepayment'] as Map)
            : null,
      ),
    );
  }

  @override
  Future<GatewayPrepayResult> createGatewayPrepay({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    String deliveryTime = '12:00 PM',
  }) async {
    final json = await _client.post(
      '/orders/gateway-prepay',
      body: _orderBody(
        menuItemId: menuItemId,
        quantities: quantities,
        receiver: receiver,
        deliveryTime: deliveryTime,
      ),
    );
    final amountRaw = json['amount'];
    final amount = amountRaw is num
        ? amountRaw.toDouble()
        : double.tryParse('$amountRaw') ?? 0;
    return GatewayPrepayResult(
      paymentToken: json['payment_token']?.toString() ?? '',
      paymentUrl: json['payment_url']?.toString() ?? '',
      amount: amount,
      prepayment: PrepaymentQuote.fromJson(
        json['prepayment'] is Map
            ? Map<String, dynamic>.from(json['prepayment'] as Map)
            : null,
      ),
    );
  }

  @override
  Future<List<CorporateOrder>> placeOrder({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    required String otp,
    String deliveryTime = '12:00 PM',
    String? paymentMethod,
    String? paymentToken,
  }) async {
    final json = await _client.post(
      '/orders',
      body: _orderBody(
        menuItemId: menuItemId,
        quantities: quantities,
        receiver: receiver,
        deliveryTime: deliveryTime,
        otp: otp,
        paymentMethod: paymentMethod,
        paymentToken: paymentToken,
      ),
    );
    return _orders(json['orders']);
  }

  @override
  Future<CorporateOrder> updateOrder({
    required String orderId,
    required int quantity,
  }) async {
    final json = await _client.patch('/orders/$orderId', body: {
      'quantity': quantity,
    });
    return CorporateOrder.fromJson(
      Map<String, dynamic>.from(json['order'] as Map),
    );
  }

  @override
  Future<void> cancelOrder(String orderId) async {
    await _client.delete('/orders/$orderId');
  }

  @override
  Future<({CorporateOrder order, List<TrackEvent> events})> track(
    String orderId,
  ) async {
    final json = await _client.get('/orders/$orderId/track');
    return (
      order: CorporateOrder.fromJson(
        Map<String, dynamic>.from(json['order'] as Map),
      ),
      events: (json['events'] as List? ?? [])
          .map((e) => TrackEvent.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }

  @override
  Future<({CorporateOrder order, List<SupportMessage> messages, bool hasExisting})>
      supportThread(String orderId) async {
    final json = await _client.get('/orders/$orderId/support');
    return (
      order: CorporateOrder.fromJson(
        Map<String, dynamic>.from(json['order'] as Map),
      ),
      messages: (json['messages'] as List? ?? [])
          .map(
            (e) => SupportMessage.fromJson(Map<String, dynamic>.from(e as Map)),
          )
          .toList(),
      hasExisting: json['has_existing_complaint'] == true,
    );
  }

  @override
  Future<void> submitSupport({
    required String orderId,
    required String category,
    required String message,
  }) async {
    await _client.post('/orders/$orderId/support', body: {
      'category': category,
      'message': message,
    });
  }

  @override
  Future<CorporateUser> topUp(double amount) async {
    final json = await _client.post('/wallet/top-up', body: {
      'amount': amount,
    });
    return CorporateUser.fromJson(
      Map<String, dynamic>.from(json['user'] as Map),
    );
  }

  @override
  MenuItem menuById(String id) {
    return _menuCache[id] ??
        MenuItem(
          id: id,
          name: 'Selected meal',
          description: '',
          price: 0,
          imageAsset: 'assets/images/menu-1.jpg',
          tags: const [],
        );
  }

  List<CorporateOrder> _orders(dynamic raw) {
    return (raw as List? ?? [])
        .map((e) => CorporateOrder.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();
  }
}

class MockCorporateRepository implements CorporateRepository {
  MockCorporateRepository() : _mock = MockRepository.instance;

  final MockRepository _mock;

  @override
  Future<CorporateUser> login({
    required String mobile,
    required String password,
  }) async {
    await AuthStore.instance.saveToken('mock-token');
    return _mock.user;
  }

  @override
  Future<CorporateUser> register({
    required String firstName,
    required String lastName,
    required String mobile,
    required String password,
    required String passwordConfirmation,
    required String companyName,
    required String address,
    required int cityId,
    required int areaId,
  }) async {
    await AuthStore.instance.saveToken('mock-token');
    return CorporateUser(
      companyName: companyName,
      mobile: mobile,
      balance: 0,
      address: address,
      firstName: firstName,
      lastName: lastName,
      cityId: cityId,
      areaId: areaId,
    );
  }

  @override
  Future<String?> forgotPassword({required String mobile}) async => '1234';

  @override
  Future<void> resetPassword({
    required String mobile,
    required String otp,
    required String password,
    required String passwordConfirmation,
  }) async {
    if (otp != '1234') {
      throw ApiException('Invalid or expired reset code.');
    }
  }

  @override
  Future<List<LocationCity>> locations() async {
    final meta = await checkoutMeta();
    return meta.cities;
  }

  @override
  Future<void> logout() => AuthStore.instance.clear();

  @override
  Future<CorporateUser> me() async => _mock.user;

  @override
  Future<CorporateUser> updateProfile({
    required String firstName,
    required String lastName,
    required String mobile,
    String? email,
    String? address,
    required int cityId,
    required int areaId,
  }) async {
    return CorporateUser(
      companyName: '$firstName $lastName'.trim(),
      mobile: mobile,
      email: email,
      balance: _mock.user.balance,
      address: address,
      firstName: firstName,
      lastName: lastName,
      cityId: cityId,
      areaId: areaId,
    );
  }

  @override
  Future<void> changePassword({
    required String currentPassword,
    required String password,
    required String passwordConfirmation,
  }) async {
    if (currentPassword.isEmpty || password.length < 8) {
      throw ApiException('Unable to change password.');
    }
  }

  @override
  Future<void> registerDeviceToken({
    required String token,
    String platform = 'android',
    String? deviceName,
  }) async {}

  @override
  Future<void> unregisterDeviceToken({required String token}) async {}

  @override
  Future<DashboardData> dashboard() async {
    return DashboardData(
      user: _mock.user,
      metrics: _mock.metrics,
      upcomingOrders: _mock.upcomingOrders,
      recentOrders: _mock.historyOrders,
    );
  }

  @override
  Future<List<MenuItem>> menu() async => _mock.menu;

  @override
  Future<CheckoutMeta> checkoutMeta() async {
    final base = DateTime(2026, 7, 17);
    return CheckoutMeta(
      dates: [
        base,
        base.add(const Duration(days: 1)),
        base.add(const Duration(days: 3)),
        base.add(const Duration(days: 4)),
        base.add(const Duration(days: 5)),
        base.add(const Duration(days: 6)),
      ],
      isPastCutoff: false,
      cutoffLabel: '3:28 PM',
      deliveryWindows: const ['12:00 PM', '11:30 AM'],
      cities: const [
        LocationCity(
          id: 1,
          name: 'Dhaka',
          areas: [
            LocationArea(id: 1, name: 'Gulshan 1'),
            LocationArea(id: 2, name: 'Banani'),
          ],
        ),
      ],
    );
  }

  @override
  Future<List<CorporateOrder>> scheduled() async => _mock.upcomingOrders;

  @override
  Future<List<CorporateOrder>> history() async => _mock.historyOrders;

  @override
  Future<OrderOtpResult> sendOrderOtp({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    String deliveryTime = '12:00 PM',
  }) async {
    return const OrderOtpResult(
      debugOtp: '1234',
      prepayment: PrepaymentQuote(
        required: false,
        ratio: 0,
        amount: 0,
        cartTotal: 0,
        balance: 10000,
        balanceSufficient: true,
      ),
    );
  }

  @override
  Future<GatewayPrepayResult> createGatewayPrepay({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    String deliveryTime = '12:00 PM',
  }) async {
    return const GatewayPrepayResult(
      paymentToken: 'mock-token',
      paymentUrl: 'https://example.com/pay',
      amount: 0,
      prepayment: PrepaymentQuote(
        required: false,
        ratio: 0,
        amount: 0,
        cartTotal: 0,
        balance: 10000,
        balanceSufficient: true,
      ),
    );
  }

  @override
  Future<List<CorporateOrder>> placeOrder({
    required String menuItemId,
    required Map<DateTime, int> quantities,
    required ReceiverDetails receiver,
    required String otp,
    String deliveryTime = '12:00 PM',
    String? paymentMethod,
    String? paymentToken,
  }) async =>
      _mock.upcomingOrders;

  @override
  Future<CorporateOrder> updateOrder({
    required String orderId,
    required int quantity,
  }) async {
    final order = _mock.orderById(orderId);
    return CorporateOrder(
      id: order.id,
      menuItem: order.menuItem,
      deliveryDate: order.deliveryDate,
      deliveryTime: order.deliveryTime,
      quantity: quantity,
      totalAmount: order.menuItem.price * quantity,
      status: order.status,
      paid: order.paid,
      isHistory: order.isHistory,
    );
  }

  @override
  Future<void> cancelOrder(String orderId) async {}

  @override
  Future<({CorporateOrder order, List<TrackEvent> events})> track(
    String orderId,
  ) async {
    return (
      order: _mock.orderById(orderId),
      events: _mock.trackEventsFor(orderId),
    );
  }

  @override
  Future<({CorporateOrder order, List<SupportMessage> messages, bool hasExisting})>
      supportThread(String orderId) async {
    return (
      order: _mock.orderById(orderId),
      messages: _mock.supportThreadFor(orderId),
      hasExisting: true,
    );
  }

  @override
  Future<void> submitSupport({
    required String orderId,
    required String category,
    required String message,
  }) async {}

  @override
  Future<CorporateUser> topUp(double amount) async => _mock.user;

  @override
  MenuItem menuById(String id) => _mock.menuById(id);
}

CorporateRepository createCorporateRepository() {
  if (ApiConfig.useMock) {
    return MockCorporateRepository();
  }
  return ApiCorporateRepository();
}
