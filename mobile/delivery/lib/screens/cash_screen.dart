import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_ui.dart';
import '../widgets/skeleton.dart';

class CashScreen extends StatefulWidget {
  const CashScreen({super.key});

  @override
  State<CashScreen> createState() => _CashScreenState();
}

class _CashScreenState extends State<CashScreen> {
  Future<List<dynamic>>? _orders;
  Future<Map<String, dynamic>>? _handovers;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final repo = AppScope.of(context);
    _orders ??= repo.deliveredOrders();
    _handovers ??= repo.cashHandovers();
  }

  Future<void> _reload() async {
    final repo = AppScope.of(context);
    setState(() {
      _orders = repo.deliveredOrders();
      _handovers = repo.cashHandovers();
    });
    await Future.wait([
      _orders ?? Future.value(<dynamic>[]),
      _handovers ?? Future.value(<String, dynamic>{}),
    ]);
  }

  Future<void> _collectCash(Map<String, dynamic> order) async {
    final id = (order['id'] as num?)?.toInt() ?? 0;
    final due = (order['cash_due'] as num?)?.toInt() ?? 0;
    final commission = (order['commission_open'] as num?)?.toInt() ??
        (order['projected_commission'] as num?)?.toInt() ??
        0;
    final projectedDue = (order['projected_due_to_middo'] as num?)?.toInt() ??
        (due > commission ? due - commission : 0);

    final amountCtrl = TextEditingController(text: due > 0 ? '$due' : '');
    final notesCtrl = TextEditingController();
    var amountText = amountCtrl.text;

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setLocal) {
            final amount = int.tryParse(amountText.trim()) ?? 0;
            final duePreview =
                amount > commission ? amount - commission : 0;
            final short = amount > 0 && amount < due;

            return AlertDialog(
              title: Text('Collect cash · #$id'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Collection ৳$due − Commission ৳$commission = Due ৳$projectedDue',
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        fontSize: 13,
                        height: 1.35,
                      ),
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Amount (৳)',
                      controller: amountCtrl,
                      keyboardType: TextInputType.number,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                      ],
                      onChanged: (v) => setLocal(() => amountText = v),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Preview: Collection ৳$amount − Commission ৳$commission = Due ৳$duePreview',
                      style: const TextStyle(
                        color: MiddoColors.inkSoft,
                        fontWeight: FontWeight.w600,
                        fontSize: 12,
                      ),
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: short ? 'Notes (required)' : 'Notes (optional)',
                      controller: notesCtrl,
                      maxLines: 2,
                      onChanged: (_) => setLocal(() {}),
                    ),
                    if (short)
                      const Padding(
                        padding: EdgeInsets.only(top: 6),
                        child: Text(
                          'Notes are required when collecting less than cash due.',
                          style: TextStyle(
                            color: MiddoColors.orange,
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, false),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: () {
                    final a = int.tryParse(amountCtrl.text.trim()) ?? 0;
                    if (a > 0 && a < due && notesCtrl.text.trim().isEmpty) {
                      return;
                    }
                    Navigator.pop(ctx, true);
                  },
                  child: const Text('Collect'),
                ),
              ],
            );
          },
        );
      },
    );

    if (ok != true || !mounted) {
      amountCtrl.dispose();
      notesCtrl.dispose();
      return;
    }

    final amount = int.tryParse(amountCtrl.text.trim()) ?? 0;
    final notes = notesCtrl.text.trim();
    amountCtrl.dispose();
    notesCtrl.dispose();
    if (amount <= 0) {
      showDeliverySnack(context, 'Enter a valid amount.', error: true);
      return;
    }
    if (amount < due && notes.isEmpty) {
      showDeliverySnack(
        context,
        'Notes required when amount is less than cash due.',
        error: true,
      );
      return;
    }

    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).collectCash(
        id,
        amount: amount,
        notes: notes.isEmpty ? null : notes,
      );
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(context, res['message']?.toString() ?? 'Collected.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _createHandover(List<dynamic> eligible) async {
    if (eligible.isEmpty) {
      showDeliverySnack(context, 'No eligible orders to hand over.', error: true);
      return;
    }

    final selected = <int>{};
    var target = 'kitchen';
    final notesCtrl = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setLocal) {
            final totalDue = eligible.fold<int>(0, (sum, raw) {
              final o = (raw as Map).cast<String, dynamic>();
              final id = (o['id'] as num?)?.toInt() ?? 0;
              if (!selected.contains(id)) return sum;
              return sum + ((o['due_to_middo'] as num?)?.toInt() ?? 0);
            });

            return AlertDialog(
              title: const Text('Cash handover'),
              content: SizedBox(
                width: double.maxFinite,
                child: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'Select orders with Due to Middo, then choose kitchen or Middo.',
                        style: TextStyle(fontSize: 13, height: 1.35),
                      ),
                      const SizedBox(height: 10),
                      ...eligible.map((raw) {
                        final o = (raw as Map).cast<String, dynamic>();
                        final id = (o['id'] as num?)?.toInt() ?? 0;
                        final due = (o['due_to_middo'] as num?)?.toInt() ?? 0;
                        return CheckboxListTile(
                          contentPadding: EdgeInsets.zero,
                          dense: true,
                          value: selected.contains(id),
                          onChanged: (v) {
                            setLocal(() {
                              if (v == true) {
                                selected.add(id);
                              } else {
                                selected.remove(id);
                              }
                            });
                          },
                          title: Text(
                            '#$id · ${o['menu_name'] ?? ''}',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          subtitle: Text('Due ৳$due'),
                        );
                      }),
                      const SizedBox(height: 8),
                      const Text(
                        'TARGET',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.8,
                          color: MiddoColors.muted,
                        ),
                      ),
                      const SizedBox(height: 8),
                      SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(value: 'kitchen', label: Text('Kitchen')),
                          ButtonSegment(value: 'middo', label: Text('Middo')),
                        ],
                        selected: {target},
                        onSelectionChanged: (s) {
                          setLocal(() => target = s.first);
                        },
                      ),
                      const SizedBox(height: 12),
                      DeliveryDialogField(
                        label: 'Notes (optional)',
                        controller: notesCtrl,
                        maxLines: 2,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Total Due ৳$totalDue · ${selected.length} order(s)',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                    ],
                  ),
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.pop(ctx, false),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: selected.isEmpty
                      ? null
                      : () => Navigator.pop(ctx, true),
                  child: const Text('Submit'),
                ),
              ],
            );
          },
        );
      },
    );

    final notes = notesCtrl.text.trim();
    final orderIds = selected.toList();
    final chosenTarget = target;
    notesCtrl.dispose();

    if (ok != true || !mounted) return;
    if (orderIds.isEmpty) {
      showDeliverySnack(context, 'Select at least one order.', error: true);
      return;
    }

    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).createCashHandover(
        orderIds: orderIds,
        target: chosenTarget,
        notes: notes.isEmpty ? null : notes,
      );
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(context, res['message']?.toString() ?? 'Submitted.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<List<Object?>>(
          future: Future.wait([
            _orders ?? Future.value(<dynamic>[]),
            _handovers ?? Future.value(<String, dynamic>{}),
          ]),
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const ListSkeleton(rows: 4);
            }
            if (snap.hasError) {
              return DeliveryError(snap.error!, onRetry: _reload);
            }
            final orders = (snap.data?[0] as List?) ?? const [];
            final handoversData =
                (snap.data?[1] as Map?)?.cast<String, dynamic>() ??
                    const <String, dynamic>{};
            final cashOnHand =
                (handoversData['cash_on_hand'] as num?)?.toInt() ?? 0;
            final dueToMiddo =
                (handoversData['due_to_middo'] as num?)?.toInt() ?? cashOnHand;
            final eligible =
                (handoversData['eligible_orders'] as List?) ?? const [];
            final handovers =
                (handoversData['handovers'] as List?) ?? const [];

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                DeliveryPanel(
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            const Text(
                              'CASH ON HAND / DUE',
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.8,
                                color: MiddoColors.muted,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '৳$cashOnHand',
                              style: const TextStyle(
                                fontSize: 28,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            Text(
                              'Due to Middo ৳$dueToMiddo',
                              style: const TextStyle(
                                color: MiddoColors.inkSoft,
                                fontWeight: FontWeight.w600,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      ),
                      FilledButton(
                        onPressed: _busy || eligible.isEmpty
                            ? null
                            : () => _createHandover(eligible),
                        child: const Text('Handover'),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                const Text(
                  'Delivered — collect cash',
                  style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
                ),
                const SizedBox(height: 8),
                if (orders.isEmpty)
                  const DeliveryEmpty('No delivered orders yet.')
                else
                  ...orders.map((raw) {
                    final order = (raw as Map).cast<String, dynamic>();
                    final canCollect = order['can_collect_cash'] == true;
                    final cashDue = (order['cash_due'] as num?)?.toInt() ?? 0;
                    final commission =
                        (order['commission_open'] as num?)?.toInt() ??
                            (order['projected_commission'] as num?)?.toInt() ??
                            0;
                    final projectedDue =
                        (order['projected_due_to_middo'] as num?)?.toInt();
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: DeliveryPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              '#${order['id']} · ${order['menu_name'] ?? ''}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${order['receiver_name'] ?? ''} · ${order['area_name'] ?? ''}',
                              style: const TextStyle(
                                color: MiddoColors.inkSoft,
                                fontWeight: FontWeight.w600,
                                fontSize: 13,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              canCollect
                                  ? 'Cash due ৳$cashDue'
                                  : (order['cash_collected'] == true
                                      ? 'Cash collected'
                                      : 'No cash due'),
                              style: TextStyle(
                                color: canCollect
                                    ? MiddoColors.orange
                                    : MiddoColors.forest,
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                              ),
                            ),
                            if (canCollect && commission > 0) ...[
                              const SizedBox(height: 4),
                              Text(
                                'Collection ৳$cashDue − Commission ৳$commission = Due ৳${projectedDue ?? (cashDue - commission)}',
                                style: const TextStyle(
                                  color: MiddoColors.muted,
                                  fontSize: 12,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                            if (canCollect) ...[
                              const SizedBox(height: 10),
                              FilledButton(
                                onPressed:
                                    _busy ? null : () => _collectCash(order),
                                child: const Text('Collect cash'),
                              ),
                            ],
                          ],
                        ),
                      ),
                    );
                  }),
                const SizedBox(height: 12),
                const Text(
                  'Handovers',
                  style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
                ),
                const SizedBox(height: 8),
                if (handovers.isEmpty)
                  const Text(
                    'No handovers yet.',
                    style: TextStyle(color: MiddoColors.inkSoft),
                  )
                else
                  ...handovers.map((raw) {
                    final h = (raw as Map).cast<String, dynamic>();
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: DeliveryPanel(
                        child: Row(
                          children: [
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    '৳${h['amount'] ?? 0}'
                                    '${h['target'] != null ? ' · ${h['target']}' : ''}',
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                      fontSize: 16,
                                    ),
                                  ),
                                  if (h['notes'] != null)
                                    Text(
                                      h['notes'].toString(),
                                      style: const TextStyle(
                                        color: MiddoColors.inkSoft,
                                        fontSize: 12,
                                      ),
                                    ),
                                ],
                              ),
                            ),
                            DeliveryStatusChip(
                              h['status']?.toString() ?? '',
                              positive: h['status'] == 'accepted',
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
              ],
            );
          },
        ),
      ),
    );
  }
}
