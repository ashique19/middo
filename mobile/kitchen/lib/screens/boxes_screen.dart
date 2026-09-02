import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_mobile_header.dart';
import '../widgets/kitchen_ui.dart';

class BoxesScreen extends StatefulWidget {
  const BoxesScreen({super.key});

  @override
  State<BoxesScreen> createState() => _BoxesScreenState();
}

class _BoxesScreenState extends State<BoxesScreen> {
  Future<Map<String, dynamic>>? _stock;
  Future<Map<String, dynamic>>? _incoming;
  final Set<int> _busy = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _stock ??= AppScope.of(context).boxesAtKitchen();
    _incoming ??= AppScope.of(context).incomingBoxes();
  }


  Future<void> _reload() async {
    final repo = AppScope.of(context);
    setState(() {
      _stock = repo.boxesAtKitchen();
      _incoming = repo.incomingBoxes();
    });
    await Future.wait([
      _stock ?? Future.value(<String, dynamic>{}),
      _incoming ?? Future.value(<String, dynamic>{}),
    ]);
  }

  Future<void> _run(int id, Future<void> Function() action) async {
    setState(() => _busy.add(id));
    try {
      await action();
      if (!mounted) return;
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } catch (e) {
      if (mounted) showKitchenSnack(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy.remove(id));
    }
  }

  Future<void> _requestBoxes() async {
    final qtyCtrl = TextEditingController(text: '10');
    final noteCtrl = TextEditingController();
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Request boxes'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            KitchenDialogField(
              label: 'Quantity',
              controller: qtyCtrl,
              keyboardType: TextInputType.number,
              inputFormatters: [FilteringTextInputFormatter.digitsOnly],
            ),
            const SizedBox(height: 12),
            KitchenDialogField(
              label: 'Note (optional)',
              controller: noteCtrl,
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
            child: const Text('Request'),
          ),
        ],
      ),
    );
    if (ok != true) {
      qtyCtrl.dispose();
      noteCtrl.dispose();
      return;
    }
    final qty = int.tryParse(qtyCtrl.text.trim()) ?? 0;
    final note = noteCtrl.text.trim();
    qtyCtrl.dispose();
    noteCtrl.dispose();
    if (qty < 1) {
      showKitchenSnack(context, 'Enter a valid quantity.', error: true);
      return;
    }
    try {
      final res = await AppScope.of(context).requestBoxes(
        quantity: qty,
        note: note.isEmpty ? null : note,
      );
      if (!mounted) return;
      showKitchenSnack(context, res['message']?.toString() ?? 'Requested.');
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: const KitchenMobileHeader(
          title: 'Boxes at Kitchen',
          showBack: true,
        ),
        body: Column(
          children: [
            Material(
              color: MiddoColors.cream,
              child: TabBar(
                labelColor: MiddoColors.forest,
                unselectedLabelColor: MiddoColors.inkSoft,
                indicatorColor: MiddoColors.forest,
                tabs: const [
                  Tab(text: 'In stock'),
                  Tab(text: 'Incoming'),
                ],
              ),
            ),
            Expanded(
              child: TabBarView(
                children: [
                  RefreshIndicator(
                    onRefresh: _reload,
                    child: FutureBuilder<Map<String, dynamic>>(
                      future: _stock,
                      builder: (context, snap) {
                        return _buildStockTab(snap);
                      },
                    ),
                  ),
                  RefreshIndicator(
                    onRefresh: _reload,
                    child: FutureBuilder<Map<String, dynamic>>(
                      future: _incoming,
                      builder: (context, snap) {
                        return _buildIncomingTab(snap);
                      },
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildStockTab(AsyncSnapshot<Map<String, dynamic>> snap) {
    if (snap.connectionState != ConnectionState.done) {
      return const Center(child: CircularProgressIndicator());
    }
    if (snap.hasError) {
      return KitchenError(snap.error!, onRetry: _reload);
    }
    final boxes = (snap.data?['boxes'] as List?) ?? const [];
    final count = snap.data?['count'] ?? boxes.length;
    if (boxes.isEmpty) {
      return ListView(
        children: const [
          KitchenEmpty('No sendable boxes in kitchen stock.'),
        ],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      itemCount: boxes.length + 1,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, i) {
        if (i == 0) {
          return Row(
            children: [
              Expanded(
                child: Text(
                  '$count box(es) ready to pack',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    color: MiddoColors.inkSoft,
                  ),
                ),
              ),
              IconButton(
                onPressed: _requestBoxes,
                icon: const Icon(Icons.add_box_outlined),
                tooltip: 'Request boxes',
              ),
            ],
          );
        }
        final b = boxes[i - 1] as Map;
        final id = b['id'] as int;
        final busy = _busy.contains(id);
        return KitchenPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                b['qr_code_id']?.toString() ?? 'Box #$id',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              Text(
                b['asset_status']?.toString() ?? '',
                style: const TextStyle(color: MiddoColors.inkSoft),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: busy
                          ? null
                          : () => _run(id, () async {
                                await AppScope.of(context)
                                    .sendBoxToWarehouse(id);
                                if (!mounted) return;
                                showKitchenSnack(
                                  context,
                                  'Sent to warehouse flow.',
                                );
                              }),
                      child: const Text('To warehouse'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton(
                      onPressed: busy
                          ? null
                          : () async {
                              final notes = await promptKitchenText(
                                context,
                                title: 'Mark damaged',
                                hint: 'Notes (optional)',
                                confirmLabel: 'Mark damaged',
                                minLength: 0,
                              );
                              if (notes == null) return;
                              await _run(id, () async {
                                await AppScope.of(context).markBoxDamaged(
                                  id,
                                  notes: notes.isEmpty ? null : notes,
                                );
                                if (!mounted) return;
                                showKitchenSnack(context, 'Marked damaged.');
                              });
                            },
                      child: const Text('Damaged'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildIncomingTab(AsyncSnapshot<Map<String, dynamic>> snap) {
    if (snap.connectionState != ConnectionState.done) {
      return const Center(child: CircularProgressIndicator());
    }
    if (snap.hasError) {
      return KitchenError(snap.error!, onRetry: _reload);
    }
    final boxes = (snap.data?['boxes'] as List?) ?? const [];
    if (boxes.isEmpty) {
      return ListView(
        children: const [KitchenEmpty('No incoming boxes.')],
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      itemCount: boxes.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, i) {
        final b = boxes[i] as Map;
        final id = b['id'] as int;
        final canReceive = b['can_receive'] == true;
        final busy = _busy.contains(id);
        return KitchenPanel(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                b['qr_code_id']?.toString() ?? 'Box #$id',
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              Text(
                'Latest: ${b['latest_action'] ?? '—'}',
                style: const TextStyle(color: MiddoColors.inkSoft),
              ),
              const SizedBox(height: 8),
              FilledButton(
                onPressed: (!canReceive || busy)
                    ? null
                    : () => _run(id, () async {
                          await AppScope.of(context).receiveBox(id);
                          if (!mounted) return;
                          showKitchenSnack(context, 'Received into stock.');
                        }),
                child: Text(
                  busy
                      ? '…'
                      : (canReceive ? 'Confirm receive' : 'Waiting for rider'),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}
