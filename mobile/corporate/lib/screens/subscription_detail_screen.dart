import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

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

  Future<void> _requestCancel(CorporateOrder order) async {
    final controller = TextEditingController();
    final reason = await showDialog<String>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Request cancel'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Middo operations will review and refund if approved.',
              style: TextStyle(color: MiddoColors.muted, fontSize: 13),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller,
              maxLines: 3,
              decoration: const InputDecoration(
                labelText: 'Reason',
                border: OutlineInputBorder(),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Back'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, controller.text.trim()),
            child: const Text('Send request'),
          ),
        ],
      ),
    );
    if (reason == null || !mounted) return;
    if (reason.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a reason for the cancel request.')),
      );
      return;
    }

    try {
      await AppScope.of(context).requestCancelPackageDay(order.id, reason);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Cancel request sent to Middo operations.')),
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
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          tooltip: 'Back',
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/subscriptions');
            }
          },
        ),
        actions: [
          IconButton(
            tooltip: 'Home',
            onPressed: () => context.go('/home'),
            icon: const Icon(Icons.home_outlined),
          ),
        ],
      ),
      body: FutureBuilder<PackageSubscription>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const MiddoPageLoader(message: 'Loading subscription…');
          }
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(snapshot.error.toString()),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: () => context.go('/home'),
                      child: const Text('Go home'),
                    ),
                  ],
                ),
              ),
            );
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
              const SizedBox(height: 14),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => context.go('/schedule'),
                      child: const Text('Schedule'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: FilledButton(
                      style: FilledButton.styleFrom(
                        backgroundColor: MiddoColors.forest,
                      ),
                      onPressed: () => context.go('/home'),
                      child: const Text('Go home'),
                    ),
                  ),
                ],
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
                    : 'Need to cancel a day? Request cancel — Middo operations will review.',
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
                Widget? trailing;
                if (order.cancelRequestPending) {
                  trailing = const Text(
                    'Requested',
                    style: TextStyle(
                      fontWeight: FontWeight.w800,
                      color: Color(0xFF92400E),
                      fontSize: 12,
                    ),
                  );
                } else if (order.canRequestCancel) {
                  trailing = TextButton(
                    onPressed: () => _requestCancel(order),
                    child: const Text('Request cancel'),
                  );
                }

                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text(order.menuItem.name),
                    subtitle: Text(
                      '${order.deliveryDate.toLocal().toString().substring(0, 10)} · ৳${order.totalAmount.toStringAsFixed(0)} · ${order.statusLabel}',
                    ),
                    trailing: trailing,
                    onTap: () => context.push('/track/${order.id}'),
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
