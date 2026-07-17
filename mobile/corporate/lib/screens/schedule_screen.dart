import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/tab_scroll_bus.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class ScheduleScreen extends StatefulWidget {
  const ScheduleScreen({super.key});

  @override
  State<ScheduleScreen> createState() => _ScheduleScreenState();
}

class _ScheduleScreenState extends State<ScheduleScreen> {
  static const _tabIndex = 2;

  Future<List<CorporateOrder>>? _future;
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    TabScrollBus.instance.register(_tabIndex, _scrollController);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).scheduled();
  }

  @override
  void dispose() {
    TabScrollBus.instance.unregister(_tabIndex, _scrollController);
    _scrollController.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final next = AppScope.of(context).scheduled();
    setState(() => _future = next);
    await next;
  }

  Future<void> _openEdit(CorporateOrder order) async {
    if (order.status != OrderStatus.pending) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Only pending orders can be edited.'),
        ),
      );
      return;
    }

    final changed = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      backgroundColor: MiddoColors.cream,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(22)),
      ),
      builder: (context) => _EditOrderSheet(order: order),
    );

    if (changed == true && mounted) {
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: FutureBuilder<List<CorporateOrder>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }

          final orders = snapshot.data!;
          return RefreshIndicator(
            onRefresh: _reload,
            child: ListView(
              controller: _scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
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
                if (orders.isEmpty)
                  const Text(
                    'No upcoming lunch schedules found.',
                    style: TextStyle(
                      color: MiddoColors.inkSoft,
                      fontWeight: FontWeight.w600,
                    ),
                  )
                else
                  ...orders.map(
                    (order) => MealOrderCard(
                      order: order,
                      onTrack: () => context.push('/track/${order.id}'),
                      onSecondary: () => _openEdit(order),
                      secondaryLabel: 'Edit',
                    ),
                  ),
              ],
            ),
          );
        },
      ),
    );
  }
}

class _EditOrderSheet extends StatefulWidget {
  const _EditOrderSheet({required this.order});

  final CorporateOrder order;

  @override
  State<_EditOrderSheet> createState() => _EditOrderSheetState();
}

class _EditOrderSheetState extends State<_EditOrderSheet> {
  late int _quantity;
  bool _saving = false;
  bool _cancelling = false;

  @override
  void initState() {
    super.initState();
    _quantity = widget.order.quantity;
  }

  Future<void> _save() async {
    setState(() => _saving = true);
    try {
      await AppScope.of(context).updateOrder(
        orderId: widget.order.id,
        quantity: _quantity,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order quantity updated'),
          backgroundColor: MiddoColors.forest,
        ),
      );
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _cancelOrder() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Cancel this order?'),
        content: Text(
          'This removes the ${DateFormat('MMM d').format(widget.order.deliveryDate)} lunch and credits ${bdt.format(widget.order.totalAmount)} back to Middo Balance. To change date or meal, cancel and place a new order.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Keep order'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: MiddoColors.orangeDeep),
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Cancel order'),
          ),
        ],
      ),
    );

    if (confirmed != true || !mounted) return;

    setState(() => _cancelling = true);
    try {
      await AppScope.of(context).cancelOrder(widget.order.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Order cancelled · balance credited'),
          backgroundColor: MiddoColors.forest,
        ),
      );
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _cancelling = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final order = widget.order;
    final unit = order.menuItem.price;
    final total = unit * _quantity;
    final busy = _saving || _cancelling;

    return Padding(
      padding: EdgeInsets.fromLTRB(
        20,
        16,
        20,
        16 + MediaQuery.viewInsetsOf(context).bottom,
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
            child: Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: MiddoColors.creamBorder,
                borderRadius: BorderRadius.circular(99),
              ),
            ),
          ),
          const SizedBox(height: 14),
          Text(
            'Edit order',
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            '${order.menuItem.name} · ${DateFormat('EEE, MMM d').format(order.deliveryDate)} · ${order.deliveryTime}',
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w600,
              color: MiddoColors.inkSoft,
            ),
          ),
          const SizedBox(height: 18),
          const Text(
            'Quantity',
            style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              _QtyButton(
                icon: Icons.remove_rounded,
                onPressed: busy || _quantity <= 1
                    ? null
                    : () => setState(() => _quantity--),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 18),
                child: Text(
                  '$_quantity',
                  style: const TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              _QtyButton(
                icon: Icons.add_rounded,
                onPressed: busy || _quantity >= 5
                    ? null
                    : () => setState(() => _quantity++),
              ),
              const Spacer(),
              Text(
                bdt.format(total),
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w800,
                  color: MiddoColors.orange,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          const Text(
            'Date, time, and meal can’t be changed here — cancel and place a new order instead.',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: MiddoColors.muted,
            ),
          ),
          const SizedBox(height: 18),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: MiddoColors.forest,
              padding: const EdgeInsets.symmetric(vertical: 16),
            ),
            onPressed: busy || _quantity == order.quantity ? null : _save,
            child: Text(_saving ? 'Saving…' : 'Save quantity'),
          ),
          const SizedBox(height: 8),
          OutlinedButton(
            style: OutlinedButton.styleFrom(
              foregroundColor: MiddoColors.orangeDeep,
              side: const BorderSide(color: MiddoColors.orangeDeep),
              padding: const EdgeInsets.symmetric(vertical: 14),
            ),
            onPressed: busy ? null : _cancelOrder,
            child: Text(_cancelling ? 'Cancelling…' : 'Cancel order'),
          ),
          const SizedBox(height: 8),
        ],
      ),
    );
  }
}

class _QtyButton extends StatelessWidget {
  const _QtyButton({required this.icon, required this.onPressed});

  final IconData icon;
  final VoidCallback? onPressed;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 44,
      height: 44,
      child: OutlinedButton(
        onPressed: onPressed,
        style: OutlinedButton.styleFrom(
          padding: EdgeInsets.zero,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        child: Icon(icon, size: 20),
      ),
    );
  }
}
