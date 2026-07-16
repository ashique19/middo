class CorporateUser {
  const CorporateUser({
    required this.companyName,
    required this.email,
    required this.balance,
    required this.area,
  });

  final String companyName;
  final String email;
  final double balance;
  final String area;

  String get initial =>
      companyName.isEmpty ? 'M' : companyName.substring(0, 1).toUpperCase();
}

class MenuItem {
  const MenuItem({
    required this.id,
    required this.name,
    required this.description,
    required this.price,
    required this.imageAsset,
    required this.tags,
  });

  final String id;
  final String name;
  final String description;
  final double price;
  final String imageAsset;
  final List<String> tags;
}

enum OrderStatus { pending, confirmed, outForDelivery, delivered }

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
        OrderStatus.outForDelivery => 'Out for delivery',
        OrderStatus.delivered => 'Delivered',
      };
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
}

class DashboardMetrics {
  const DashboardMetrics({
    required this.activeOrders,
    required this.nextMealLabel,
    required this.nextDeliveryHint,
    required this.monthlySpend,
    required this.monthlySaved,
  });

  final int activeOrders;
  final String nextMealLabel;
  final String nextDeliveryHint;
  final double monthlySpend;
  final double monthlySaved;
}
