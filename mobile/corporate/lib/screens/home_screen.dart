import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/middo_haptics.dart';
import '../data/tab_scroll_bus.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';
import 'payment_result_screen.dart';
import 'payment_webview_screen.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  static const _tabIndex = 0;

  Future<DashboardData>? _future;
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    TabScrollBus.instance.register(_tabIndex, _scrollController);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).dashboard();
  }

  @override
  void dispose() {
    TabScrollBus.instance.unregister(_tabIndex, _scrollController);
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final next = AppScope.of(context).dashboard();
    setState(() => _future = next);
    await next;
  }

  String _greeting(CorporateUser user) {
    final hour = DateTime.now().hour;
    final part = hour < 12
        ? 'Good morning'
        : (hour < 17 ? 'Good afternoon' : 'Good evening');
    final first = (user.firstName ?? '').trim();
    if (first.isEmpty) return part;
    return '$part, $first';
  }

  String _nextDayHint(CheckoutMeta? meta) {
    if (meta == null || meta.dates.isEmpty) {
      return 'Browse today’s menu and schedule desk delivery.';
    }
    final first = meta.dates.first;
    final label = DateFormat('EEE, MMM d').format(first);
    return 'Next open day: $label · tap to order';
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<DashboardData>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const HomeSkeleton();
        }
        if (snapshot.hasError) {
          return MiddoEmptyState(
            icon: Icons.cloud_off_rounded,
            title: 'Couldn’t load home',
            message: snapshot.error.toString(),
            actionLabel: 'Retry',
            onAction: _reload,
          );
        }

        final data = snapshot.data!;
        final user = data.user;
        final metrics = data.metrics;
        final upcoming = data.upcomingOrders;
        final recent = data.recentOrders;

        return RefreshIndicator(
          onRefresh: _reload,
          child: ListView(
            controller: _scrollController,
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
            children: [
              Text(
                _greeting(user),
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      fontWeight: FontWeight.w900,
                      letterSpacing: -0.9,
                      height: 1.15,
                    ),
              ),
              const SizedBox(height: 6),
              Text(
                user.companyName,
                style: const TextStyle(
                  color: MiddoColors.inkSoft,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 18),
              Material(
                color: Colors.transparent,
                child: InkWell(
                  onTap: () {
                    MiddoHaptics.selection();
                    context.go('/menu');
                  },
                  borderRadius: BorderRadius.circular(22),
                  child: Ink(
                    height: 148,
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(22),
                      gradient: const LinearGradient(
                        colors: [
                          MiddoColors.forest,
                          Color(0xFF2A5A3C),
                          Color(0xFF8B3A00),
                        ],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                    ),
                    child: Stack(
                      children: [
                        Positioned(
                          right: -18,
                          bottom: -24,
                          child: Icon(
                            Icons.lunch_dining_rounded,
                            size: 140,
                            color: Colors.white.withValues(alpha: 0.08),
                          ),
                        ),
                        Padding(
                          padding: const EdgeInsets.fromLTRB(20, 22, 20, 20),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Order lunch',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                  fontSize: 24,
                                  letterSpacing: -0.6,
                                ),
                              ),
                              const SizedBox(height: 6),
                              FutureBuilder<CheckoutMeta>(
                                future: AppScope.of(context).checkoutMeta(),
                                builder: (context, metaSnap) {
                                  return Text(
                                    _nextDayHint(metaSnap.data),
                                    style: const TextStyle(
                                      color: Colors.white70,
                                      fontWeight: FontWeight.w600,
                                      fontSize: 13,
                                      height: 1.35,
                                    ),
                                  );
                                },
                              ),
                              const Spacer(),
                              const Row(
                                children: [
                                  Text(
                                    'Open menu',
                                    style: TextStyle(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 13,
                                    ),
                                  ),
                                  SizedBox(width: 4),
                                  Icon(
                                    Icons.arrow_forward_rounded,
                                    color: Colors.white,
                                    size: 18,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              SingleChildScrollView(
                scrollDirection: Axis.horizontal,
                child: Row(
                  children: [
                    _MetricChip(
                      label: 'Active',
                      value: '${metrics.activeOrders}',
                      accent: MiddoColors.forest,
                    ),
                    const SizedBox(width: 8),
                    _MetricChip(
                      label: 'Next meal',
                      value: metrics.nextMealLabel,
                      accent: MiddoColors.orange,
                    ),
                    const SizedBox(width: 8),
                    _MetricChip(
                      label: 'This month',
                      value: bdt.format(metrics.monthlySpend),
                      accent: MiddoColors.inkSoft,
                    ),
                  ],
                ),
              ),
              if (metrics.monthlySaved > 0) ...[
                const SizedBox(height: 8),
                Text(
                  'Saved ~${bdt.format(metrics.monthlySaved)} this month',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: MiddoColors.muted,
                  ),
                ),
              ],
              if (recent.isNotEmpty) ...[
                const SectionHeader(title: 'Order again'),
                SizedBox(
                  height: 118,
                  child: ListView.separated(
                    scrollDirection: Axis.horizontal,
                    itemCount: recent.take(6).length,
                    separatorBuilder: (_, __) => const SizedBox(width: 10),
                    itemBuilder: (context, index) {
                      final order = recent[index];
                      return _OrderAgainCard(
                        order: order,
                        onTap: () {
                          MiddoHaptics.selection();
                          context.push('/checkout/${order.menuItem.id}');
                        },
                      );
                    },
                  ),
                ),
              ],
              SectionHeader(
                title: 'Upcoming',
                actionLabel: 'Schedule',
                onAction: () => context.go('/schedule'),
              ),
              if (upcoming.isEmpty)
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: MiddoColors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: MiddoColors.creamBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'No upcoming lunches yet.',
                        style: TextStyle(
                          color: MiddoColors.inkSoft,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                      const SizedBox(height: 10),
                      OutlinedButton(
                        onPressed: () {
                          MiddoHaptics.selection();
                          context.go('/menu');
                        },
                        child: const Text('Browse menu'),
                      ),
                    ],
                  ),
                )
              else
                ...upcoming.take(3).map(
                      (order) => MealOrderCard(
                        order: order,
                        onTrack: () => context.push('/track/${order.id}'),
                        onSecondary: () =>
                            context.push('/support/${order.id}'),
                        onPay: order.canPayOnline &&
                                order.onlinePaymentUrl != null
                            ? () async {
                                final paid = await PaymentWebViewScreen.open(
                                  context,
                                  paymentUrl: order.onlinePaymentUrl!,
                                  title: 'Make payment',
                                );
                                if (!context.mounted) return;
                                await PaymentResultScreen.open(
                                  context,
                                  success: paid,
                                  title: 'Order payment',
                                  primaryLabel: paid ? 'Track order' : 'Try again',
                                  primaryRoute:
                                      paid ? '/track/${order.id}' : '/home',
                                );
                                if (paid && context.mounted) await _reload();
                              }
                            : null,
                      ),
                    ),
              const SizedBox(height: 8),
              Material(
                color: MiddoColors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: MiddoColors.creamBorder),
                ),
                child: InkWell(
                  onTap: () {
                    MiddoHaptics.selection();
                    context.go('/wallet');
                  },
                  borderRadius: BorderRadius.circular(16),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 16,
                      vertical: 14,
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: MiddoColors.amberSoft,
                            borderRadius: BorderRadius.circular(12),
                          ),
                          child: const Icon(
                            Icons.add_rounded,
                            color: MiddoColors.orange,
                          ),
                        ),
                        const SizedBox(width: 12),
                        const Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Top up Middo Balance',
                                style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 14,
                                ),
                              ),
                              SizedBox(height: 2),
                              Text(
                                'Pay for lunches without leaving the app',
                                style: TextStyle(
                                  fontWeight: FontWeight.w600,
                                  fontSize: 11,
                                  color: MiddoColors.muted,
                                ),
                              ),
                            ],
                          ),
                        ),
                        const Icon(
                          Icons.chevron_right_rounded,
                          color: MiddoColors.muted,
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

class _OrderAgainCard extends StatelessWidget {
  const _OrderAgainCard({required this.order, required this.onTap});

  final CorporateOrder order;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: MiddoColors.white,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Container(
          width: 200,
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(color: MiddoColors.creamBorder),
          ),
          child: Row(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(12),
                child: SizedBox(
                  width: 64,
                  height: 88,
                  child: MealImage(item: order.menuItem, height: 88, width: 64),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      order.menuItem.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 13,
                        height: 1.2,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      bdt.format(order.menuItem.price),
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: MiddoColors.orange,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 4),
                    const Text(
                      'Reorder',
                      style: TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 11,
                        color: MiddoColors.forest,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _MetricChip extends StatelessWidget {
  const _MetricChip({
    required this.label,
    required this.value,
    required this.accent,
  });

  final String label;
  final String value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minWidth: 108),
      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
      decoration: BoxDecoration(
        color: MiddoColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: MiddoColors.creamBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.5,
              color: MiddoColors.muted,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.4,
              color: accent,
            ),
          ),
        ],
      ),
    );
  }
}
