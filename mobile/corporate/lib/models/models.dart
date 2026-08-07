/// PHP/MySQL JSON often encodes numerics as strings — parse both shapes.
double _asDouble(dynamic value, [double fallback = 0]) {
  if (value == null) return fallback;
  if (value is num) return value.toDouble();
  return double.tryParse(value.toString()) ?? fallback;
}

int _asInt(dynamic value, [int fallback = 0]) {
  if (value == null) return fallback;
  if (value is num) return value.toInt();
  return int.tryParse(value.toString()) ?? fallback;
}

int? _asIntOrNull(dynamic value) {
  if (value == null || value.toString().trim().isEmpty) return null;
  if (value is num) return value.toInt();
  return int.tryParse(value.toString());
}

class CorporateUser {
  const CorporateUser({
    required this.companyName,
    required this.mobile,
    required this.balance,
    this.email,
    this.area,
    this.city,
    this.address,
    this.firstName,
    this.lastName,
    this.areaId,
    this.cityId,
  });

  final String companyName;
  final String mobile;
  final String? email;
  final double balance;
  final String? area;
  final String? city;
  final String? address;
  final String? firstName;
  final String? lastName;
  final int? areaId;
  final int? cityId;

  String get initial =>
      companyName.isEmpty ? 'M' : companyName.substring(0, 1).toUpperCase();

  String get receiverName {
    final parts = [firstName, lastName]
        .whereType<String>()
        .map((e) => e.trim())
        .where((e) => e.isNotEmpty)
        .toList();
    return parts.join(' ');
  }

  factory CorporateUser.fromJson(Map<String, dynamic> json) {
    return CorporateUser(
      companyName: (json['company_name'] ?? 'Corporate Partner').toString(),
      mobile: (json['mobile'] ?? '').toString(),
      email: json['email']?.toString(),
      balance: _asDouble(json['balance']),
      area: json['area']?.toString(),
      city: json['city']?.toString(),
      address: json['address']?.toString(),
      firstName: json['first_name']?.toString(),
      lastName: json['last_name']?.toString(),
      areaId: _asIntOrNull(json['area_id']),
      cityId: _asIntOrNull(json['city_id']),
    );
  }
}

class LocationArea {
  const LocationArea({required this.id, required this.name});

  final int id;
  final String name;

  factory LocationArea.fromJson(Map<String, dynamic> json) {
    return LocationArea(
      id: _asInt(json['id']),
      name: (json['name'] ?? '').toString(),
    );
  }
}

class LocationCity {
  const LocationCity({
    required this.id,
    required this.name,
    required this.areas,
  });

  final int id;
  final String name;
  final List<LocationArea> areas;

  factory LocationCity.fromJson(Map<String, dynamic> json) {
    return LocationCity(
      id: _asInt(json['id']),
      name: (json['name'] ?? '').toString(),
      areas: (json['areas'] as List? ?? [])
          .map((e) => LocationArea.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }
}

class MenuItem {
  const MenuItem({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    required this.imageAsset,
    required this.tags,
    this.imageUrl,
  });

  final String id;
  final String name;
  final String description;
  final double price;
  final String imageAsset;
  final String? imageUrl;
  final List<String> tags;

  String get image => imageUrl ?? imageAsset;
  bool get hasNetworkImage => imageUrl != null && imageUrl!.startsWith('http');

  factory MenuItem.fromJson(Map<String, dynamic> json) {
    final image = json['image']?.toString();
    return MenuItem(
      id: json['id'].toString(),
      name: (json['name'] ?? 'Meal').toString(),
      description: (json['description'] ?? '').toString(),
      price: _asDouble(json['price']),
      imageAsset: 'assets/images/menu-1.jpg',
      imageUrl: image,
      tags: (json['tags'] as List?)?.map((e) => e.toString()).toList() ??
          const ['Thalis'],
    );
  }
}

enum OrderStatus {
  pending,
  confirmed,
  processing,
  packed,
  outForDelivery,
  delivered,
  cancelled,
  other
}


class CorporateOrder {
  const CorporateOrder({
    required this.id,
    required this.menuItem,
    required this.deliveryDate,
    required this.deliveryTime,
    required this.quantity,
    required this.totalAmount,
    required this.status,
    required this.paid,
    this.amountPaid = 0,
    this.amountDue = 0,
    this.receiverName,
    this.receiverMobile,
    this.accountHolderName,
    this.hasSeparateReceiver = false,
    this.isHistory = false,
    this.paymentMethod,
    this.paymentMethodLabel,
    this.canDelete = false,
    this.canRequestCancel = false,
    this.cancelRequestPending = false,
  });

  final String id;
  final MenuItem menuItem;
  final DateTime deliveryDate;
  final String deliveryTime;
  final int quantity;
  final double totalAmount;
  final double amountPaid;
  final double amountDue;
  final OrderStatus status;
  final bool paid;
  final String? receiverName;
  final String? receiverMobile;
  final String? accountHolderName;
  final bool hasSeparateReceiver;
  final bool isHistory;
  final String? paymentMethod;
  final String? paymentMethodLabel;
  final bool canDelete;
  final bool canRequestCancel;
  final bool cancelRequestPending;

  String get statusLabel => switch (status) {
        OrderStatus.pending => 'Pending',
        OrderStatus.confirmed => 'Confirmed',
        OrderStatus.processing => 'Kitchen preparing',
        OrderStatus.packed => 'Packed',
        OrderStatus.outForDelivery => 'Out for delivery',
        OrderStatus.delivered => 'Delivered',
        OrderStatus.cancelled => 'Cancelled',
        OrderStatus.other => 'Updated',
      };

  factory CorporateOrder.fromJson(Map<String, dynamic> json) {
    final menuJson = Map<String, dynamic>.from(json['menu_item'] as Map? ?? {});
    final statusRaw = (json['order_status'] ?? 'pending').toString();
    final total = _asDouble(json['total_amount']);
    final paidAmount = _asDouble(json['amount_paid']);
    final due = json.containsKey('amount_due')
        ? _asDouble(json['amount_due'])
        : (total - paidAmount).clamp(0, total).toDouble();
    return CorporateOrder(
      id: json['id'].toString(),
      menuItem: MenuItem.fromJson(menuJson),
      deliveryDate: DateTime.tryParse(json['delivery_date']?.toString() ?? '') ??
          DateTime.now(),
      deliveryTime: (json['delivery_time'] ?? '12:00 PM').toString(),
      quantity: _asInt(json['quantity'], 1),
      totalAmount: total,
      amountPaid: paidAmount,
      amountDue: due,
      status: _parseStatus(statusRaw),
      paid: json['paid'] == true || json['payment_status'] == 'paid',
      receiverName: json['receiver_name']?.toString(),
      receiverMobile: json['receiver_mobile']?.toString(),
      accountHolderName: json['account_holder_name']?.toString(),
      hasSeparateReceiver: json['has_separate_receiver'] == true,
      isHistory: json['is_history'] == true,
      paymentMethod: json['payment_method']?.toString(),
      paymentMethodLabel: json['payment_method_label']?.toString(),
      canDelete: json['can_delete'] == true,
      canRequestCancel: json['can_request_cancel'] == true,
      cancelRequestPending: json['cancel_request_pending'] == true,
    );
  }

  static OrderStatus _parseStatus(String raw) {
    return switch (raw) {
      'pending' => OrderStatus.pending,
      'confirmed' => OrderStatus.confirmed,
      'processing' => OrderStatus.processing,
      'packed' => OrderStatus.packed,
      'on_the_way_to_delivery' => OrderStatus.outForDelivery,
      'delivered' || 'delivered_and_paid' => OrderStatus.delivered,
      'cancelled' => OrderStatus.cancelled,
      _ => OrderStatus.other,
    };
  }
}

class TrackEvent {
  const TrackEvent({
    required this.title,
    required this.description,
    required this.at,
    this.isCurrent = false,
  });

  final String title;
  final String description;
  final DateTime at;
  final bool isCurrent;

  factory TrackEvent.fromJson(Map<String, dynamic> json) {
    return TrackEvent(
      title: (json['title'] ?? 'Update').toString(),
      description: (json['description'] ?? '').toString(),
      at: DateTime.tryParse(json['at']?.toString() ?? '')?.toLocal() ??
          DateTime.now(),
      isCurrent: json['is_current'] == true,
    );
  }
}

class SupportMessage {
  const SupportMessage({
    required this.fromSupport,
    required this.body,
    required this.at,
    this.category,
  });

  final bool fromSupport;
  final String body;
  final DateTime at;
  final String? category;

  factory SupportMessage.fromJson(Map<String, dynamic> json) {
    return SupportMessage(
      fromSupport: json['from_support'] == true,
      body: (json['body'] ?? '').toString(),
      at: DateTime.tryParse(json['at']?.toString() ?? '')?.toLocal() ??
          DateTime.now(),
      category: json['category_label']?.toString() ?? json['category']?.toString(),
    );
  }
}

class DashboardMetrics {
  const DashboardMetrics({
    required this.activeOrders,
    required this.nextMealLabel,
    required this.nextDeliveryHint,
    required this.monthlySpend,
    required this.monthlySaved,
    this.boxesInCustody = 0,
  });

  final int activeOrders;
  final String nextMealLabel;
  final String nextDeliveryHint;
  final double monthlySpend;
  final double monthlySaved;
  final int boxesInCustody;

  factory DashboardMetrics.fromJson(Map<String, dynamic> json) {
    return DashboardMetrics(
      activeOrders: _asInt(json['active_orders']),
      nextMealLabel: (json['next_meal'] ?? 'None').toString(),
      nextDeliveryHint: (json['next_delivery_hint'] ?? '').toString(),
      monthlySpend: _asDouble(json['monthly_spend']),
      monthlySaved: _asDouble(json['monthly_saved']),
      boxesInCustody: _asInt(json['boxes_in_custody']),
    );
  }
}

class DashboardData {
  const DashboardData({
    required this.user,
    required this.metrics,
    required this.upcomingOrders,
    required this.recentOrders,
  });

  final CorporateUser user;
  final DashboardMetrics metrics;
  final List<CorporateOrder> upcomingOrders;
  final List<CorporateOrder> recentOrders;
}

class CheckoutMeta {
  const CheckoutMeta({
    required this.dates,
    required this.isPastCutoff,
    required this.cutoffLabel,
    required this.deliveryWindows,
    this.cities = const [],
  });

  final List<DateTime> dates;
  final bool isPastCutoff;
  final String cutoffLabel;
  final List<String> deliveryWindows;
  final List<LocationCity> cities;

  factory CheckoutMeta.fromJson(Map<String, dynamic> json) {
    return CheckoutMeta(
      dates: (json['dates'] as List? ?? [])
          .map((e) => DateTime.tryParse(e.toString()) ?? DateTime.now())
          .toList(),
      isPastCutoff: json['is_past_cutoff'] == true,
      cutoffLabel: (json['cutoff_label'] ?? '').toString(),
      deliveryWindows: (json['delivery_windows'] as List? ?? ['12:00 PM'])
          .map((e) => e.toString())
          .toList(),
      cities: (json['cities'] as List? ?? [])
          .map((e) => LocationCity.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }
}

class ReceiverDetails {
  const ReceiverDetails({
    required this.receiverName,
    required this.mobile,
    required this.address,
    required this.cityId,
    required this.areaId,
  });

  final String receiverName;
  final String mobile;
  final String address;
  final int cityId;
  final int areaId;
}

class PrepaymentQuote {
  const PrepaymentQuote({
    required this.required,
    required this.ratio,
    required this.amount,
    required this.cartTotal,
    required this.balance,
    required this.balanceSufficient,
    this.reason,
    this.message,
    this.activeOrders = 0,
    this.newOrders = 0,
    this.projectedActive = 0,
  });

  final bool required;
  final double ratio;
  final double amount;
  final double cartTotal;
  final double balance;
  final bool balanceSufficient;
  final String? reason;
  final String? message;
  final int activeOrders;
  final int newOrders;
  final int projectedActive;

  factory PrepaymentQuote.fromJson(Map<String, dynamic>? json) {
    if (json == null) {
      return const PrepaymentQuote(
        required: false,
        ratio: 0,
        amount: 0,
        cartTotal: 0,
        balance: 0,
        balanceSufficient: true,
      );
    }
    return PrepaymentQuote(
      required: json['required'] == true,
      ratio: _asDouble(json['ratio']),
      amount: _asDouble(json['amount']),
      cartTotal: _asDouble(json['cart_total']),
      balance: _asDouble(json['balance']),
      balanceSufficient: json['balance_sufficient'] != false,
      reason: json['reason']?.toString(),
      message: json['message']?.toString(),
      activeOrders: _asInt(json['active_orders']),
      newOrders: _asInt(json['new_orders']),
      projectedActive: _asInt(json['projected_active']),
    );
  }
}

class OrderOtpResult {
  const OrderOtpResult({
    this.debugOtp,
    required this.prepayment,
    this.codAllowed = false,
    this.paymentMethods = const ['balance', 'gateway'],
  });

  final String? debugOtp;
  final PrepaymentQuote prepayment;
  final bool codAllowed;
  final List<String> paymentMethods;
}

class GatewayPrepayResult {
  const GatewayPrepayResult({
    required this.paymentToken,
    required this.paymentUrl,
    required this.amount,
    required this.prepayment,
  });

  final String paymentToken;
  final String paymentUrl;
  final double amount;
  final PrepaymentQuote prepayment;
}

class WalletTopUpResult {
  const WalletTopUpResult({
    required this.paymentUrl,
    required this.token,
    required this.amount,
    required this.user,
  });

  final String paymentUrl;
  final String token;
  final double amount;
  final CorporateUser user;
}

class MiddoBoxSummary {
  const MiddoBoxSummary({
    required this.id,
    required this.qrCodeId,
    required this.boxModelType,
    required this.locationLabel,
    this.readyForPickup = false,
  });

  final int id;
  final String qrCodeId;
  final String boxModelType;
  final String locationLabel;
  final bool readyForPickup;

  factory MiddoBoxSummary.fromJson(Map<String, dynamic> json) {
    return MiddoBoxSummary(
      id: _asInt(json['id']),
      qrCodeId: (json['qr_code_id'] ?? '').toString(),
      boxModelType: (json['box_model_type'] ?? '').toString(),
      locationLabel: (json['location_label'] ?? 'At your office').toString(),
      readyForPickup: json['ready_for_pickup'] == true,
    );
  }
}

class BoxesCustodyData {
  const BoxesCustodyData({
    required this.count,
    required this.boxes,
    required this.message,
  });

  final int count;
  final List<MiddoBoxSummary> boxes;
  final String message;
}

class MealPackage {
  const MealPackage({
    required this.id,
    required this.name,
    required this.summary,
    required this.pricePerDay,
    required this.dietTag,
    required this.durationDays,
    required this.startDate,
    required this.endDate,
    required this.daysCount,
    this.thumbnail,
    this.days = const [],
  });

  final String id;
  final String name;
  final String summary;
  final int pricePerDay;
  final String dietTag;
  final int durationDays;
  final String startDate;
  final String endDate;
  final int daysCount;
  final String? thumbnail;
  final List<MealPackageDay> days;

  factory MealPackage.fromJson(Map<String, dynamic> json) {
    return MealPackage(
      id: json['id'].toString(),
      name: (json['name'] ?? 'Package').toString(),
      summary: (json['summary'] ?? '').toString(),
      pricePerDay: _asInt(json['price_per_day']),
      dietTag: (json['diet_tag'] ?? 'classic').toString(),
      durationDays: _asInt(json['duration_days'], 30),
      startDate: (json['start_date'] ?? '').toString(),
      endDate: (json['end_date'] ?? '').toString(),
      daysCount: _asInt(json['days_count']),
      thumbnail: json['thumbnail']?.toString(),
      days: (json['days'] as List? ?? [])
          .map((e) => MealPackageDay.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }
}

class MealPackageDay {
  const MealPackageDay({
    required this.date,
    required this.weekday,
    this.menuItem,
  });

  final String date;
  final int weekday;
  final MenuItem? menuItem;

  factory MealPackageDay.fromJson(Map<String, dynamic> json) {
    final menu = json['menu_item'];
    return MealPackageDay(
      date: (json['date'] ?? '').toString(),
      weekday: _asInt(json['weekday']),
      menuItem: menu is Map
          ? MenuItem.fromJson(Map<String, dynamic>.from(menu))
          : null,
    );
  }
}

class PackageQuote {
  const PackageQuote({
    required this.billableDays,
    required this.pricePerDay,
    required this.quantity,
    required this.totalAmount,
    required this.days,
    this.targetMonth = '',
    this.availableDays = 0,
    this.selections = const [],
  });

  final int billableDays;
  final int pricePerDay;
  final int quantity;
  final int totalAmount;
  final List<Map<String, dynamic>> days;
  final String targetMonth;
  final int availableDays;
  final List<Map<String, dynamic>> selections;

  factory PackageQuote.fromJson(Map<String, dynamic> json) {
    return PackageQuote(
      billableDays: _asInt(json['billable_days']),
      pricePerDay: _asInt(json['price_per_day']),
      quantity: _asInt(json['quantity'], 1),
      totalAmount: _asInt(json['total_amount']),
      days: (json['days'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
      targetMonth: (json['target_month'] ?? '').toString(),
      availableDays: _asInt(json['available_days']),
      selections: (json['selections'] as List? ?? [])
          .map((e) => Map<String, dynamic>.from(e as Map))
          .toList(),
    );
  }
}

class PackageMenuSelection {
  const PackageMenuSelection({
    required this.menuItemId,
    required this.dayCount,
    this.name,
  });

  final int menuItemId;
  final int dayCount;
  final String? name;

  Map<String, dynamic> toJson() => {
        'menu_item_id': menuItemId,
        'day_count': dayCount,
      };

  factory PackageMenuSelection.fromJson(Map<String, dynamic> json) {
    final menu = json['menu_item'];
    return PackageMenuSelection(
      menuItemId: _asInt(json['menu_item_id']),
      dayCount: _asInt(json['day_count']),
      name: menu is Map
          ? (menu['name'] ?? '').toString()
          : json['menu_item_name']?.toString(),
    );
  }
}

class PackageSubscription {
  const PackageSubscription({
    required this.id,
    required this.quantity,
    required this.billableDays,
    required this.pricePerDay,
    required this.totalAmount,
    required this.amountPaid,
    required this.status,
    required this.startDate,
    required this.endDate,
    this.package,
    this.orders = const [],
    this.omittedWeekdays = const [],
    this.scheduleStatus = 'scheduled',
    this.targetMonth,
    this.selections = const [],
  });

  final String id;
  final MealPackage? package;
  final int quantity;
  final int billableDays;
  final int pricePerDay;
  final int totalAmount;
  final int amountPaid;
  final String status;
  final String startDate;
  final String endDate;
  final List<CorporateOrder> orders;
  final List<int> omittedWeekdays;
  final String scheduleStatus;
  final String? targetMonth;
  final List<PackageMenuSelection> selections;

  String get name => package?.name ?? 'Package';

  bool get isAwaitingSchedule => scheduleStatus == 'awaiting_schedule';

  factory PackageSubscription.fromJson(Map<String, dynamic> json) {
    final pkg = json['package'];
    return PackageSubscription(
      id: json['id'].toString(),
      package: pkg is Map
          ? MealPackage.fromJson(Map<String, dynamic>.from(pkg))
          : null,
      quantity: _asInt(json['quantity'], 1),
      billableDays: _asInt(json['billable_days']),
      pricePerDay: _asInt(json['price_per_day']),
      totalAmount: _asInt(json['total_amount']),
      amountPaid: _asInt(json['amount_paid']),
      status: (json['status'] ?? 'active').toString(),
      startDate: (json['start_date'] ?? '').toString(),
      endDate: (json['end_date'] ?? '').toString(),
      omittedWeekdays: (json['omitted_weekdays'] as List? ?? [])
          .map((e) => _asInt(e))
          .toList(),
      scheduleStatus: (json['schedule_status'] ?? 'scheduled').toString(),
      targetMonth: json['target_month']?.toString(),
      selections: (json['selections'] as List? ?? [])
          .map(
            (e) => PackageMenuSelection.fromJson(
              Map<String, dynamic>.from(e as Map),
            ),
          )
          .toList(),
      orders: (json['orders'] as List? ?? [])
          .map((e) => CorporateOrder.fromJson(Map<String, dynamic>.from(e as Map)))
          .toList(),
    );
  }
}

