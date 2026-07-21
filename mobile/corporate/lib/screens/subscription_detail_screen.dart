import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class SubscriptionDetailScreen extends StatefulWidget {
  const SubscriptionDetailScreen({super.key, required this.subscriptionId});

  final String subscriptionId;

  @override
  State<SubscriptionDetailScreen> createState() =>
      _SubscriptionDetailScreenState();
}

class _SubscriptionDetailScreenState extends State<SubscriptionDetailScreen> {
  Future<PackageSubscription>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).myPackageShow(widget.subscriptionId);
  }

  Future<void> _reload() async {
    setState(() {
      _future = AppScope.of(context).myPackageShow(widget.subscriptionId);
    });
  }

  Future<void> _skip(CorporateOrder order) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Skip this day?'),
        content: Text(
          '৳${order.amountPaid.toStringAsFixed(0)} will be credited to your Middo Balance.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Skip day'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    try {
      final result = await AppScope.of(context).skipPackageDay(order.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Skipped. ৳${result.refundedAmount} credited. Balance ৳${result.balance.toStringAsFixed(0)}',
          ),
        ),
      );
      await _reload();
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MiddoColors.cream,
      appBar: AppBar(
        title: const Text('My package'),
        backgroundColor: MiddoColors.cream,
        foregroundColor: MiddoColors.ink,
        elevation: 0,
      ),
      body: FutureBuilder<PackageSubscription>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const MiddoPageLoader(message: 'Loading subscription…');
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }
          final sub = snapshot.data!;
          return ListView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
            children: [
              Text(
                sub.name,
                style: const TextStyle(
                  fontWeight: FontWeight.w900,
                  fontSize: 24,
                ),
              ),
              const SizedBox(height: 6),
              Text(
                sub.isAwaitingSchedule
                    ? '${sub.targetMonth ?? sub.startDate} · awaiting schedule'
                    : '${sub.startDate} – ${sub.endDate} · ${sub.status}',
                style: TextStyle(
                  color: MiddoColors.muted,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 14),
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: MiddoColors.forest,
                  borderRadius: BorderRadius.circular(18),
                ),
                child: Text(
                  'Paid ৳${sub.amountPaid} · ${sub.billableDays} days · qty ${sub.quantity}',
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              if (sub.selections.isNotEmpty) ...[
                const SizedBox(height: 14),
                const Text(
                  'Menu selection',
                  style: TextStyle(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 8),
                for (final sel in sub.selections)
                  ListTile(
                    dense: true,
                    contentPadding: EdgeInsets.zero,
                    title: Text(sel.name ?? 'Menu #${sel.menuItemId}'),
                    trailing: Text(
                      '${sel.dayCount} days',
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ),
              ],
              const SizedBox(height: 10),
              Text(
                sub.isAwaitingSchedule
                    ? 'Prepaid. Middo operations will assign exact delivery dates next.'
                    : 'Skip pending days before cutoff to get wallet credit.',
                style: TextStyle(
                  color: MiddoColors.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                ),
              ),
              const SizedBox(height: 16),
              if (sub.orders.isEmpty && sub.isAwaitingSchedule)
                const Padding(
                  padding: EdgeInsets.symmetric(vertical: 24),
                  child: Text(
                    'Dates not scheduled yet.',
                    textAlign: TextAlign.center,
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
              ...sub.orders.map((order) {
                final canSkip = order.status == OrderStatus.pending;
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text(order.menuItem.name),
                    subtitle: Text(
                      '${order.deliveryDate.toLocal().toString().substring(0, 10)} · ৳${order.totalAmount.toStringAsFixed(0)} · ${order.statusLabel}',
                    ),
                    trailing: canSkip
                        ? TextButton(
                            onPressed: () => _skip(order),
                            child: const Text('Skip'),
                          )
                        : null,
                  ),
                );
              }),
            ],
          );
        },
      ),
    );
  }
}
