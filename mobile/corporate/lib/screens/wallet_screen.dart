import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/tab_scroll_bus.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class WalletScreen extends StatefulWidget {
  const WalletScreen({super.key});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  static const _tabIndex = 3;

  Future<DashboardData>? _future;
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
    _future ??= AppScope.of(context).dashboard();
  }

  @override
  void dispose() {
    TabScrollBus.instance.unregister(_tabIndex, _scrollController);
    _scrollController.dispose();
    _custom.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final next = AppScope.of(context).dashboard();
    setState(() => _future = next);
    await next;
  }

  Future<void> _topUp() async {
    setState(() => _submitting = true);
    try {
      await AppScope.of(context).topUp(_selected.toDouble());
      await _reload();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Topped up ${bdt.format(_selected)}'),
          backgroundColor: MiddoColors.forest,
        ),
      );
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
      child: FutureBuilder<DashboardData>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const MiddoPageLoader(message: 'Loading wallet…');
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }
          final user = snapshot.data!.user;

          return ListView(
            controller: _scrollController,
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
                  _submitting ? 'Processing…' : 'Continue to payment',
                ),
              ),
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
                    MetaRow(label: 'Company', value: user.companyName, labelWidth: 100),
                    MetaRow(label: 'Mobile', value: user.mobile, labelWidth: 100),
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
    );
  }
}
