class CorporateUser {
  const CorporateUser({
    required this.companyName,
    required this.mobile,
    required this.balance,
    this.email,
    this.area,
    this.address,
  });

  final String companyName;
  final String mobile;
  final String? email;
  final double balance;
  final String? area;
  final String? address;

  String get initial =>
      companyName.isEmpty ? 'M' : companyName.substring(0, 1).toUpperCase();

  factory CorporateUser.fromJson(Map<String, dynamic> json) {
    return CorporateUser(
      companyName: (json['company_name'] ?? 'Corporate Partner').toString(),
      mobile: (json['mobile'] ?? '').toString(),
      email: json['email']?.toString(),
      balance: (json['balance'] as num?)?.toDouble() ?? 0,
      area: json['area']?.toString(),
      address: json['address']?.toString(),
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
      price: (json['price'] as num?)?.toDouble() ?? 0,
      imageAsset: 'assets/images/menu-1.jpg',
      imageUrl: image,
      tags: (json['tags'] as List?)?.map((e) => e.toString()).toList() ??
          const ['Thalis'],
    );
  }
}

enum OrderStatus { pending, confirmed, processing, outForDelivery, delivered, cancelled, other }

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
    this.isHistory = false,
  });

  final String id;
  final MenuItem menuItem;
  final DateTime deliveryDate;
  final String deliveryTime;
  final int quantity;
  final double totalAmount;
  final OrderStatus status;
  final bool paid;
  final bool isHistory;

  String get statusLabel => switch (status) {
        OrderStatus.pending => 'Pending',
        OrderStatus.confirmed => 'Confirmed',
        OrderStatus.processing => 'Processing',
        OrderStatus.outForDelivery => 'Out for delivery',
        OrderStatus.delivered => 'Delivered',
        OrderStatus.cancelled => 'Cancelled',
        OrderStatus.other => 'Updated',
      };

  factory CorporateOrder.fromJson(Map<String, dynamic> json) {
    final menuJson = Map<String, dynamic>.from(json['menu_item'] as Map? ?? {});
    final statusRaw = (json['order_status'] ?? 'pending').toString();
    return CorporateOrder(
      id: json['id'].toString(),
      menuItem: MenuItem.fromJson(menuJson),
      deliveryDate: DateTime.tryParse(json['delivery_date']?.toString() ?? '') ??
          DateTime.now(),
      deliveryTime: (json['delivery_time'] ?? '12:00 PM').toString(),
      quantity: (json['quantity'] as num?)?.toInt() ?? 1,
      totalAmount: (json['total_amount'] as num?)?.toDouble() ?? 0,
      status: _parseStatus(statusRaw),
      paid: json['paid'] == true || json['payment_status'] == 'paid',
      isHistory: json['is_history'] == true,
    );
  }

  static OrderStatus _parseStatus(String raw) {
    return switch (raw) {
      'pending' => OrderStatus.pending,
      'confirmed' => OrderStatus.confirmed,
      'processing' => OrderStatus.processing,
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
      activeOrders: (json['active_orders'] as num?)?.toInt() ?? 0,
      nextMealLabel: (json['next_meal'] ?? 'None').toString(),
      nextDeliveryHint: (json['next_delivery_hint'] ?? '').toString(),
      monthlySpend: (json['monthly_spend'] as num?)?.toDouble() ?? 0,
      monthlySaved: (json['monthly_saved'] as num?)?.toDouble() ?? 0,
      boxesInCustody: (json['boxes_in_custody'] as num?)?.toInt() ?? 0,
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
  });

  final List<DateTime> dates;
  final bool isPastCutoff;
  final String cutoffLabel;
  final List<String> deliveryWindows;

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
    );
  }
}
