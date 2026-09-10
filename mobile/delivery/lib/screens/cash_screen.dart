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
    final amountCtrl = TextEditingController(text: due > 0 ? '$due' : '');
    final notesCtrl = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: Text('Collect cash · #$id'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DeliveryDialogField(
                label: 'Amount (৳)',
                controller: amountCtrl,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              ),
              const SizedBox(height: 12),
              DeliveryDialogField(
                label: 'Notes (optional)',
                controller: notesCtrl,
                maxLines: 2,
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Collect'),
            ),
          ],
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

  Future<void> _createHandover(int cashOnHand) async {
    final amountCtrl = TextEditingController(
      text: cashOnHand > 0 ? '$cashOnHand' : '',
    );
    final notesCtrl = TextEditingController();

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return AlertDialog(
          title: const Text('Cash handover'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              DeliveryDialogField(
                label: 'Amount (৳)',
                controller: amountCtrl,
                keyboardType: TextInputType.number,
                inputFormatters: [FilteringTextInputFormatter.digitsOnly],
              ),
              const SizedBox(height: 12),
              DeliveryDialogField(
                label: 'Notes (optional)',
                controller: notesCtrl,
                maxLines: 2,
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(ctx, false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.pop(ctx, true),
              child: const Text('Submit'),
            ),
          ],
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

    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).createCashHandover(
        amount: amount,
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
                              'CASH ON HAND',
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
                          ],
                        ),
                      ),
                      FilledButton(
                        onPressed: _busy || cashOnHand <= 0
                            ? null
                            : () => _createHandover(cashOnHand),
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
                                  ? 'Cash due ৳${order['cash_due'] ?? 0}'
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
                                    '৳${h['amount'] ?? 0}',
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
