import '../models/models.dart';

/// Offline demo data used when `USE_MOCK=true`.
class MockRepository {
  MockRepository._();
  static final instance = MockRepository._();

  final user = const CorporateUser(
    companyName: 'Acme BD Ltd',
    mobile: '01310123452',
    email: 'corporate@middo.com',
    balance: 12450,
    area: 'Gulshan 1',
  );

  final metrics = const DashboardMetrics(
    activeOrders: 3,
    nextMealLabel: '12:00 PM',
    nextDeliveryHint: 'Delivery 11:30',
    monthlySpend: 48200,
    monthlySaved: 4820,
  );

  late final menu = <MenuItem>[
    const MenuItem(
      id: 'm1',
      name: 'Chicken Biryani Thali',
      description: 'Classic office thali with raita & dessert',
      price: 420,
      imageAsset: 'assets/images/menu-1.jpg',
      tags: ['Thalis', 'Protein'],
    ),
    const MenuItem(
      id: 'm2',
      name: 'Beef Tehari Combo',
      description: 'Fragrant tehari, salad, raita & dessert',
      price: 420,
      imageAsset: 'assets/images/menu-2.jpg',
      tags: ['Thalis', 'Protein'],
    ),
    const MenuItem(
      id: 'm3',
      name: 'Grilled Fish Bowl',
      description: 'Light lunch with greens & lemon rice',
      price: 390,
      imageAsset: 'assets/images/menu-3.jpg',
      tags: ['Light', 'Protein'],
    ),
    const MenuItem(
      id: 'm4',
      name: 'Veg Kichuri Thali',
      description: 'Comfort bowl with bhaji & boiled egg',
      price: 320,
      imageAsset: 'assets/images/menu-4.jpg',
      tags: ['Veg', 'Thalis'],
    ),
    const MenuItem(
      id: 'm5',
      name: 'Chicken Bhuna Plate',
      description: 'Office favorite with paratha & salad',
      price: 380,
      imageAsset: 'assets/images/menu-5.jpg',
      tags: ['Protein'],
    ),
    const MenuItem(
      id: 'm6',
      name: 'Paneer Butter Masala',
      description: 'Creamy paneer with butter naan',
      price: 360,
      imageAsset: 'assets/images/menu-6.jpg',
      tags: ['Veg'],
    ),
  ];

  List<CorporateOrder> get upcomingOrders => [
        CorporateOrder(
          id: '1842',
          menuItem: menu[0],
          deliveryDate: DateTime(2026, 7, 17),
          deliveryTime: '12:00 PM',
          quantity: 12,
          totalAmount: 5040,
          status: OrderStatus.confirmed,
          paid: true,
        ),
        CorporateOrder(
          id: '1843',
          menuItem: menu[5],
          deliveryDate: DateTime(2026, 7, 18),
          deliveryTime: '12:00 PM',
          quantity: 8,
          totalAmount: 2880,
          status: OrderStatus.pending,
          paid: true,
        ),
        CorporateOrder(
          id: '1844',
          menuItem: menu[2],
          deliveryDate: DateTime(2026, 7, 21),
          deliveryTime: '12:00 PM',
          quantity: 15,
          totalAmount: 5850,
          status: OrderStatus.confirmed,
          paid: true,
        ),
      ];

  List<CorporateOrder> get historyOrders => [
        CorporateOrder(
          id: '1830',
          menuItem: const MenuItem(
            id: 'm8',
            name: 'Mutton Kacchi',
            description: 'Weekend special for the team',
            price: 520,
            imageAsset: 'assets/images/menu-8.jpg',
            tags: ['Protein'],
          ),
          deliveryDate: DateTime(2026, 7, 15),
          deliveryTime: '12:00 PM',
          quantity: 10,
          totalAmount: 5200,
          status: OrderStatus.delivered,
          paid: true,
          isHistory: true,
        ),
        CorporateOrder(
          id: '1828',
          menuItem: const MenuItem(
            id: 'm9',
            name: 'Chicken Roast Thali',
            description: 'Roast, rice, salad & dessert',
            price: 400,
            imageAsset: 'assets/images/menu-9.jpg',
            tags: ['Thalis'],
          ),
          deliveryDate: DateTime(2026, 7, 14),
          deliveryTime: '12:00 PM',
          quantity: 14,
          totalAmount: 5600,
          status: OrderStatus.delivered,
          paid: true,
          isHistory: true,
        ),
      ];

  List<TrackEvent> trackEventsFor(String orderId) => [
        TrackEvent(
          title: 'Rider en route to office',
          description:
              'Courier has left Middo warehouse with sealed Middo Boxes.',
          at: DateTime(2026, 7, 17, 11, 18),
          isCurrent: true,
        ),
        TrackEvent(
          title: 'Packed at kitchen',
          description: 'Quality check passed · thermal boxes sealed.',
          at: DateTime(2026, 7, 17, 10, 42),
        ),
        TrackEvent(
          title: 'Kitchen accepted',
          description: 'Order assigned to partner kitchen for today’s run.',
          at: DateTime(2026, 7, 17, 8, 5),
        ),
        TrackEvent(
          title: 'Order confirmed',
          description:
              'Paid from Middo Balance · scheduled for desk delivery.',
          at: DateTime(2026, 7, 16, 16, 21),
        ),
      ];

  List<SupportMessage> supportThreadFor(String orderId) => [
        SupportMessage(
          fromSupport: false,
          category: 'Missing item',
          body:
              'Two boxes arrived without raita cups for the Banani floor.',
          at: DateTime(2026, 7, 17, 12, 40),
        ),
        SupportMessage(
          fromSupport: true,
          body:
              'Sorry about that — we’ve flagged the rider. A replacement will arrive within 25 minutes.',
          at: DateTime(2026, 7, 17, 12, 48),
        ),
      ];

  CorporateOrder orderById(String id) {
    return [...upcomingOrders, ...historyOrders]
        .firstWhere((o) => o.id == id, orElse: () => upcomingOrders.first);
  }

  MenuItem menuById(String id) {
    return menu.firstWhere((m) => m.id == id, orElse: () => menu.first);
  }
}
