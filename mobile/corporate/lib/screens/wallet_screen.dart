import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/tab_scroll_bus.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';
import 'payment_result_screen.dart';
import 'payment_webview_screen.dart';

class WalletScreen extends StatefulWidget {
  const WalletScreen({super.key});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  static const _tabIndex = 3;

  Future<({DashboardData dashboard, List<WalletLedgerEntry> transactions})>?
      _future;
  int _selected = 5000;
  final _custom = TextEditingController(text: '5000');
  final _scrollController = ScrollController();
  bool _submitting = false;

  @override
  void initState() {
    super.initState();
    TabScrollBus.instance.register(_tabIndex, _scrollController);
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= _load();
  }

  @override
  void dispose() {
    TabScrollBus.instance.unregister(_tabIndex, _scrollController);
    _scrollController.dispose();
    _custom.dispose();
    super.dispose();
  }

  Future<({DashboardData dashboard, List<WalletLedgerEntry> transactions})>
      _load() async {
    final repo = AppScope.of(context);
    final results = await Future.wait([
      repo.dashboard(),
      repo.walletTransactions(),
    ]);
    final dashboard = results[0] as DashboardData;
    final ledger =
        results[1] as ({int balance, List<WalletLedgerEntry> transactions});
    return (dashboard: dashboard, transactions: ledger.transactions);
  }

  Future<void> _reload() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  Future<void> _topUp() async {
    setState(() => _submitting = true);
    try {
      final result = await AppScope.of(context).topUp(_selected.toDouble());
      if (!mounted) return;
      final paid = await PaymentWebViewScreen.open(
        context,
        paymentUrl: result.paymentUrl,
        title: 'Top up ৳$_selected',
      );
      if (!mounted) return;
      await PaymentResultScreen.open(
        context,
        success: paid,
        title: 'Middo Balance',
        message: paid
            ? 'Your top-up is recorded. Pull to refresh if the balance takes a moment.'
            : 'Payment was closed before completion. If you already paid, pull to refresh.',
        primaryLabel: 'Back to wallet',
        primaryRoute: '/wallet',
      );
      if (!mounted) return;
      await _reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const ListSkeleton(rows: 5);
            }
            if (snapshot.hasError) {
              return MiddoEmptyState(
                icon: Icons.cloud_off_rounded,
                title: 'Wallet unavailable',
                message: snapshot.error.toString(),
                actionLabel: 'Retry',
                onAction: _reload,
              );
            }
            final data = snapshot.data!;
            final user = data.dashboard.user;
            final metrics = data.dashboard.metrics;
            final transactions = data.transactions;

            return ListView(
              controller: _scrollController,
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
              children: [
                Text(
                  'Wallet',
                  style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                        letterSpacing: -0.8,
                      ),
                ),
                const SizedBox(height: 4),
                const Text(
                  'Fund lunches from your secure Middo Balance.',
                  style: TextStyle(
                    color: MiddoColors.inkSoft,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.all(20),
                  decoration: BoxDecoration(
                    gradient: const LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [MiddoColors.forest, Color(0xFF2F5A3C)],
                    ),
                    borderRadius: BorderRadius.circular(22),
                    border: Border.all(color: MiddoColors.forestDeep),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'AVAILABLE BALANCE',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.6,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        bdt.format(user.balance),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 34,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -1,
                        ),
                      ),
                      const SizedBox(height: 14),
                      Row(
                        children: [
                          Expanded(
                            child: _SpendStat(
                              label: 'This month',
                              value: bdt.format(metrics.monthlySpend),
                            ),
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: _SpendStat(
                              label: 'Saved ~',
                              value: bdt.format(metrics.monthlySaved),
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 18),
                const Text(
                  'Quick top-up',
                  style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 10),
                Row(
                  children: [2000, 5000, 10000].map((amount) {
                    final active = _selected == amount;
                    return Expanded(
                      child: Padding(
                        padding: EdgeInsets.only(right: amount == 10000 ? 0 : 8),
                        child: InkWell(
                          onTap: () {
                            setState(() {
                              _selected = amount;
                              _custom.text = '$amount';
                            });
                          },
                          borderRadius: BorderRadius.circular(14),
                          child: Ink(
                            padding: const EdgeInsets.symmetric(vertical: 12),
                            decoration: BoxDecoration(
                              color: active
                                  ? MiddoColors.amberSoft
                                  : MiddoColors.white,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(
                                color: active
                                    ? MiddoColors.orange
                                    : const Color(0xFFDDD3BE),
                              ),
                            ),
                            child: Center(
                              child: Text(
                                bdt.format(amount),
                                style: TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 13,
                                  color: active
                                      ? MiddoColors.orange
                                      : MiddoColors.ink,
                                ),
                              ),
                            ),
                          ),
                        ),
                      ),
                    );
                  }).toList(),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _custom,
                  keyboardType: TextInputType.number,
                  decoration: const InputDecoration(labelText: 'CUSTOM AMOUNT'),
                  onChanged: (value) {
                    final parsed = int.tryParse(value);
                    if (parsed != null) setState(() => _selected = parsed);
                  },
                ),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: _submitting ? null : _topUp,
                  child: Text(
                    _submitting ? 'Opening checkout…' : 'Continue to payment',
                  ),
                ),
                const SizedBox(height: 22),
                const Text(
                  'Transactions',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 10),
                if (transactions.isEmpty)
                  const Text(
                    'No wallet transactions yet.',
                    style: TextStyle(
                      color: MiddoColors.inkSoft,
                      fontWeight: FontWeight.w600,
                      fontSize: 13,
                    ),
                  )
                else
                  ...transactions.map((tx) {
                    final credit = tx.amount >= 0;
                    final amountColor =
                        credit ? MiddoColors.forest : MiddoColors.orangeDeep;
                    final signed =
                        '${credit ? '+' : ''}${bdt.format(tx.amount)}';
                    return Container(
                      margin: const EdgeInsets.only(bottom: 8),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: MiddoColors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: MiddoColors.creamBorder),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  tx.type.isNotEmpty
                                      ? tx.type
                                      : (credit ? 'Credit' : 'Debit'),
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w800,
                                    color: MiddoColors.inkSoft,
                                    letterSpacing: 0.3,
                                  ),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  tx.description.isNotEmpty
                                      ? tx.description
                                      : 'Wallet entry',
                                  style: const TextStyle(
                                    fontSize: 13,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  DateFormat('MMM d, yyyy · h:mm a')
                                      .format(tx.at),
                                  style: const TextStyle(
                                    fontSize: 11,
                                    fontWeight: FontWeight.w600,
                                    color: MiddoColors.muted,
                                  ),
                                ),
                              ],
                            ),
                          ),
                          Text(
                            signed,
                            style: TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: amountColor,
                            ),
                          ),
                        ],
                      ),
                    );
                  }),
                const SizedBox(height: 22),
                const Text(
                  'Account',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 10),
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: MiddoColors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: MiddoColors.creamBorder),
                  ),
                  child: Column(
                    children: [
                      MetaRow(
                        label: 'Company',
                        value: user.companyName,
                        labelWidth: 100,
                      ),
                      MetaRow(
                        label: 'Buyer',
                        value: user.receiverName,
                        labelWidth: 100,
                      ),
                      MetaRow(
                        label: 'Mobile',
                        value: user.mobile,
                        labelWidth: 100,
                      ),
                      MetaRow(
                        label: 'Delivery area',
                        value: user.area ?? '—',
                        labelWidth: 100,
                      ),
                    ],
                  ),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _SpendStat extends StatelessWidget {
  const _SpendStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(
              color: Colors.white70,
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.4,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}
