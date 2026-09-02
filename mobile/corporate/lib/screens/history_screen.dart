import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';
import 'payment_webview_screen.dart';

class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  Future<List<CorporateOrder>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).history();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('History'),
      ),
      body: FutureBuilder<List<CorporateOrder>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const MiddoPageLoader(message: 'Loading history…');
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }
          final orders = snapshot.data!;
          return ListView(
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
              if (orders.isEmpty)
                const Text(
                  'No previous lunch history yet.',
                  style: TextStyle(
                    color: MiddoColors.inkSoft,
                    fontWeight: FontWeight.w600,
                  ),
                )
              else
                ...orders.map(
                  (order) => Opacity(
                    opacity: 0.92,
                    child: MealOrderCard(
                      order: order,
                      onTrack: () => context.go('/menu'),
                      onSecondary: () => context.push('/support/${order.id}'),
                      secondaryLabel: 'Feedback',
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
                ),
            ],
          );
        },
      ),
    );
  }
}
