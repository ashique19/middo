import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/tab_scroll_bus.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';
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

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<DashboardData>(
      future: _future,
      builder: (context, snapshot) {
        if (snapshot.connectionState != ConnectionState.done) {
          return const MiddoPageLoader(message: 'Loading home…');
        }
        if (snapshot.hasError) {
          return _ErrorState(
            message: snapshot.error.toString(),
            onRetry: _reload,
          );
        }

        final data = snapshot.data!;
        final user = data.user;
        final metrics = data.metrics;
        final upcoming = data.upcomingOrders;

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
                  onTap: () => context.go('/menu'),
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
                        const Padding(
                          padding: EdgeInsets.fromLTRB(20, 22, 20, 20),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Order lunch',
                                style: TextStyle(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                  fontSize: 24,
                                  letterSpacing: -0.6,
                                ),
                              ),
                              SizedBox(height: 6),
                              Text(
                                'Browse today’s menu and schedule desk delivery.',
                                style: TextStyle(
                                  color: Colors.white70,
                                  fontWeight: FontWeight.w600,
                                  fontSize: 13,
                                  height: 1.35,
                                ),
                              ),
                              Spacer(),
                              Row(
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
                  child: const Text(
                    'No upcoming lunches yet. Open the menu to schedule your next meal.',
                    style: TextStyle(
                      color: MiddoColors.inkSoft,
                      fontWeight: FontWeight.w600,
                      height: 1.35,
                    ),
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
                            ? () {
                                PaymentWebViewScreen.open(
                                  context,
                                  paymentUrl: order.onlinePaymentUrl!,
                                  title: 'Make payment',
                                );
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
                  onTap: () => context.go('/wallet'),
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

class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(
              message,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontWeight: FontWeight.w600,
                color: MiddoColors.inkSoft,
              ),
            ),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}
