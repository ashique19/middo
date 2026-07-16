import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/mock_repository.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class ScheduleScreen extends StatelessWidget {
  const ScheduleScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final orders = MockRepository.instance.upcomingOrders;

    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
        children: [
          Text(
            'Scheduled',
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.8,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            '${orders.length} upcoming orders on your calendar.',
            style: const TextStyle(
              color: MiddoColors.inkSoft,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 14),
          FilledButton(
            onPressed: () => context.go('/menu'),
            child: const Text('Place New Order'),
          ),
          const SizedBox(height: 16),
          ...orders.map(
            (order) => MealOrderCard(
              order: order,
              onTrack: () => context.push('/track/${order.id}'),
              onSecondary: () {},
              secondaryLabel: 'Edit',
            ),
          ),
        ],
      ),
    );
  }
}
