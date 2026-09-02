import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_ui.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  Future<Map<String, dynamic>>? _account;
  Future<Map<String, dynamic>>? _handovers;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final repo = AppScope.of(context);
    _account ??= repo.account();
    _handovers ??= repo.cashHandovers();
  }

  Future<void> _reload() async {
    final repo = AppScope.of(context);
    setState(() {
      _account = repo.account();
      _handovers = repo.cashHandovers();
    });
    await Future.wait([
      _account ?? Future.value(<String, dynamic>{}),
      _handovers ?? Future.value(<String, dynamic>{}),
    ]);
  }

  Future<void> _withdraw() async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).requestWithdrawal();
      if (!mounted) return;
      showKitchenSnack(context, res['message']?.toString() ?? 'Submitted.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _transfer() async {
    final amountCtrl = TextEditingController();
    final refCtrl = TextEditingController();
    final noteCtrl = TextEditingController();
    XFile? proof;

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) {
        return StatefulBuilder(
          builder: (ctx, setLocal) {
            return AlertDialog(
              title: const Text('Pay Middo'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextField(
                      controller: amountCtrl,
                      keyboardType: TextInputType.number,
                      inputFormatters: [
                        FilteringTextInputFormatter.digitsOnly,
                      ],
                      decoration: const InputDecoration(
                        labelText: 'Amount (৳)',
                      ),
                    ),
                    TextField(
                      controller: refCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Reference (optional)',
                      ),
                    ),
                    TextField(
                      controller: noteCtrl,
                      decoration: const InputDecoration(
                        labelText: 'Notes (optional)',
                      ),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton.icon(
                      onPressed: () async {
                        final file = await ImagePicker().pickImage(
                          source: ImageSource.gallery,
                          imageQuality: 85,
                        );
                        if (file != null) setLocal(() => proof = file);
                      },
                      icon: const Icon(Icons.photo),
                      label: Text(
                        proof == null ? 'Attach proof photo' : 'Proof selected',
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
                  onPressed: () => Navigator.pop(ctx, true),
                  child: const Text('Submit'),
                ),
              ],
            );
          },
        );
      },
    );

    final amount = int.tryParse(amountCtrl.text.trim()) ?? 0;
    final reference = refCtrl.text.trim();
    final notes = noteCtrl.text.trim();
    amountCtrl.dispose();
    refCtrl.dispose();
    noteCtrl.dispose();
    if (ok != true) return;
    if (amount < 1 || proof == null) {
      showKitchenSnack(
        context,
        'Amount and proof photo are required.',
        error: true,
      );
      return;
    }

    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).transferToMiddo(
        amount: amount,
        proofPath: proof!.path,
        reference: reference.isEmpty ? null : reference,
        notes: notes.isEmpty ? null : notes,
      );
      if (!mounted) return;
      showKitchenSnack(context, res['message']?.toString() ?? 'Submitted.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _acceptHandover(int id) async {
    setState(() => _busy = true);
    try {
      await AppScope.of(context).acceptCashHandover(id);
      if (!mounted) return;
      showKitchenSnack(context, 'Cash handover accepted.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _rejectHandover(int id) async {
    setState(() => _busy = true);
    try {
      await AppScope.of(context).rejectCashHandover(id);
      if (!mounted) return;
      showKitchenSnack(context, 'Cash handover rejected.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Account'),
        actions: [
          IconButton(
            onPressed: () => context.push('/profile'),
            icon: const Icon(Icons.person_outline),
            tooltip: 'Profile',
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
          children: [
            FutureBuilder<Map<String, dynamic>>(
              future: _account,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const Padding(
                    padding: EdgeInsets.all(24),
                    child: Center(child: CircularProgressIndicator()),
                  );
                }
                if (snap.hasError) {
                  return Text('Account error: ${snap.error}');
                }
                final a = snap.data ?? const {};
                final receivable =
                    (a['receivable'] as num?)?.toInt() ?? 0;
                final payable =
                    (a['payable_to_middo'] as num?)?.toInt() ?? 0;
                return KitchenPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Receivable from Middo',
                        style: Theme.of(context).textTheme.titleMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      Text(
                        '৳$receivable',
                        style: const TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.w800,
                          color: MiddoColors.forest,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Payable to Middo: ৳$payable',
                        style: const TextStyle(color: MiddoColors.inkSoft),
                      ),
                      const SizedBox(height: 12),
                      Wrap(
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          FilledButton(
                            onPressed:
                                (_busy || receivable < 1) ? null : _withdraw,
                            child: const Text('Request withdrawal'),
                          ),
                          OutlinedButton(
                            onPressed:
                                _busy || payable < 1 ? null : _transfer,
                            child: const Text('Pay Middo'),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              },
            ),
            const SizedBox(height: 20),
            Text(
              'Cash handovers',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: 8),
            FutureBuilder<Map<String, dynamic>>(
              future: _handovers,
              builder: (context, snap) {
                if (snap.connectionState != ConnectionState.done) {
                  return const SizedBox.shrink();
                }
                if (snap.hasError) {
                  return Text('Handovers error: ${snap.error}');
                }
                final list = (snap.data?['handovers'] as List?) ?? const [];
                if (list.isEmpty) {
                  return const Text(
                    'No pending cash handovers.',
                    style: TextStyle(color: MiddoColors.inkSoft),
                  );
                }
                return Column(
                  children: [
                    for (final raw in list)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 8),
                        child: KitchenPanel(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Handover #${(raw as Map)['id']} · ৳${raw['amount']}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                              Text(
                                '${raw['rider_name'] ?? 'Rider'}'
                                '${raw['rider_mobile'] != null ? ' · ${raw['rider_mobile']}' : ''}'
                                ' · ${raw['item_count'] ?? 0} order(s)',
                                style: const TextStyle(
                                  color: MiddoColors.inkSoft,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  Expanded(
                                    child: FilledButton(
                                      onPressed: _busy
                                          ? null
                                          : () => _acceptHandover(
                                                raw['id'] as int,
                                              ),
                                      child: const Text('Accept'),
                                    ),
                                  ),
                                  const SizedBox(width: 8),
                                  Expanded(
                                    child: OutlinedButton(
                                      onPressed: _busy
                                          ? null
                                          : () => _rejectHandover(
                                                raw['id'] as int,
                                              ),
                                      child: const Text('Reject'),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                  ],
                );
              },
            ),
          ],
        ),
      ),
    );
  }
}
