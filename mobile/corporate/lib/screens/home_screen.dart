import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/mock_repository.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final repo = MockRepository.instance;
    final user = repo.user;
    final metrics = repo.metrics;
    final upcoming = repo.upcomingOrders;

    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: MiddoColors.amberSoft,
                foregroundColor: MiddoColors.orange,
                child: Text(
                  user.initial,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(width: 10),
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    user.companyName,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  Text(
                    'Balance ${bdt.format(user.balance)}',
                    style: const TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                      color: MiddoColors.inkSoft,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            'Good morning',
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.8,
                ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Manage today’s office lunches from one place.',
            style: TextStyle(
              color: MiddoColors.inkSoft,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 16),
          Material(
            color: Colors.transparent,
            child: InkWell(
              onTap: () => context.go('/menu'),
              borderRadius: BorderRadius.circular(16),
              child: Ink(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    colors: [
                      MiddoColors.forest,
                      Color(0xFF2A5A3C),
                      Color(0xFFAB3F00),
                    ],
                    begin: Alignment.centerLeft,
                    end: Alignment.centerRight,
                  ),
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: MiddoColors.forestDeep),
                ),
                child: Row(
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.14),
                        borderRadius: BorderRadius.circular(14),
                      ),
                      child: const Icon(Icons.menu_rounded, color: Colors.white),
                    ),
                    const SizedBox(width: 12),
                    const Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Browse Menu',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 15,
                            ),
                          ),
                          SizedBox(height: 2),
                          Text(
                            'Explore today’s thalis & place an order',
                            style: TextStyle(
                              color: Colors.white70,
                              fontWeight: FontWeight.w600,
                              fontSize: 11,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: KpiCard(
                  label: 'Active orders',
                  value: '${metrics.activeOrders}',
                  hint: 'In production',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: KpiCard(
                  label: 'Next meal',
                  value: metrics.nextMealLabel,
                  hint: metrics.nextDeliveryHint,
                  dark: false,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          KpiCard(
            label: 'Monthly spend',
            value: bdt.format(metrics.monthlySpend),
            hint: 'Saved ~${bdt.format(metrics.monthlySaved)} this month',
          ),
          SectionHeader(
            title: 'Upcoming lunches',
            actionLabel: 'See all',
            onAction: () => context.go('/schedule'),
          ),
          ...upcoming.take(1).map(
                (order) => MealOrderCard(
                  order: order,
                  onTrack: () => context.push('/track/${order.id}'),
                  onSecondary: () => context.push('/support/${order.id}'),
                ),
              ),
          const SectionHeader(title: 'Quick tools'),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 10,
            mainAxisSpacing: 10,
            childAspectRatio: 1.35,
            children: [
              _QuickTile(
                icon: Icons.add_rounded,
                iconColor: MiddoColors.orange,
                title: 'Add Money',
                subtitle: 'Top up Middo balance',
                onTap: () => context.go('/wallet'),
              ),
              _QuickTile(
                icon: Icons.chat_bubble_outline_rounded,
                iconColor: MiddoColors.forest,
                title: 'Support',
                subtitle: 'Order help & complaints',
                onTap: () => context.push('/support/${upcoming.first.id}'),
              ),
              _QuickTile(
                icon: Icons.history_rounded,
                iconColor: MiddoColors.inkSoft,
                title: 'History',
                subtitle: 'Past office lunches',
                onTap: () => context.push('/history'),
              ),
              const _QuickTile(
                icon: Icons.inventory_2_outlined,
                iconColor: MiddoColors.orange,
                title: 'Return Box',
                subtitle: '0 Middo Boxes ready',
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _QuickTile extends StatelessWidget {
  const _QuickTile({
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.subtitle,
    this.onTap,
  });

  final IconData icon;
  final Color iconColor;
  final String title;
  final String subtitle;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: MiddoColors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: const BorderSide(color: MiddoColors.creamBorder),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Icon(icon, color: iconColor, size: 20),
              const Spacer(),
              Text(
                title,
                style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  fontSize: 11,
                  color: MiddoColors.muted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
