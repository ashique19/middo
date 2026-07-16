import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/mock_repository.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class HistoryScreen extends StatelessWidget {
  const HistoryScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final orders = MockRepository.instance.historyOrders;

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('History'),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 24),
        children: [
          const Text(
            'Recent office lunches this billing cycle.',
            style: TextStyle(
              color: MiddoColors.inkSoft,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 14),
          ...orders.map(
            (order) => Opacity(
              opacity: 0.92,
              child: MealOrderCard(
                order: order,
                onTrack: () => context.go('/menu'),
                onSecondary: () => context.push('/support/${order.id}'),
                secondaryLabel: 'Feedback',
              ),
            ),
          ),
        ],
      ),
    );
  }
}
