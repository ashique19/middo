import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key, required this.menuItemId});

  final String menuItemId;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  MenuItem? _item;
  CheckoutMeta? _meta;
  Map<DateTime, int> _quantities = {};
  bool _loading = true;
  bool _submitting = false;
  String? _error;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_item == null) {
      _bootstrap();
    }
  }

  Future<void> _bootstrap() async {
    final repo = AppScope.of(context);
    try {
      final menu = await repo.menu();
      final meta = await repo.checkoutMeta();
      final item = menu.firstWhere(
        (m) => m.id == widget.menuItemId,
        orElse: () => repo.menuById(widget.menuItemId),
      );
      final quantities = <DateTime, int>{
        for (var i = 0; i < meta.dates.length; i++)
          meta.dates[i]: i == 0 ? 1 : 0,
      };
      if (!mounted) return;
      setState(() {
        _item = item;
        _meta = meta;
        _quantities = quantities;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _confirm() async {
    final item = _item;
    if (item == null) return;
    setState(() => _submitting = true);
    try {
      await AppScope.of(context).placeOrder(
        menuItemId: item.id,
        quantities: _quantities,
      );
      if (!mounted) return;
      final totalQty = _quantities.values.fold<int>(0, (a, b) => a + b);
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Scheduled $totalQty meals · ${bdt.format(totalQty * item.price)}',
          ),
          backgroundColor: MiddoColors.forest,
        ),
      );
      context.go('/schedule');
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message), backgroundColor: MiddoColors.orangeDeep),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: Center(child: CircularProgressIndicator()));
    }
    if (_error != null || _item == null || _meta == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: Center(child: Text(_error ?? 'Unable to load checkout')),
      );
    }

    final item = _item!;
    final totalQty = _quantities.values.fold<int>(0, (a, b) => a + b);
    final total = totalQty * item.price;

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'NEW ORDER',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: MiddoColors.orange,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.6,
                  ),
            ),
            const Text('Checkout'),
          ],
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 120),
        children: [
          Container(
            decoration: BoxDecoration(
              color: MiddoColors.white,
              borderRadius: BorderRadius.circular(22),
              border: Border.all(color: MiddoColors.creamBorder),
            ),
            clipBehavior: Clip.antiAlias,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                MealImage(item: item, height: 160),
                Padding(
                  padding: const EdgeInsets.all(14),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        item.name,
                        style: const TextStyle(
                          fontSize: 17,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Unit price ${bdt.format(item.price)} · Desk-side delivery',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                          color: MiddoColors.inkSoft,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Delivery dates & quantities',
            style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 4),
          Text(
            _meta!.isPastCutoff
                ? 'Same-day cutoff passed (${_meta!.cutoffLabel})'
                : 'Same-day cutoff open until ${_meta!.cutoffLabel}',
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w600,
              color: MiddoColors.inkSoft,
            ),
          ),
          const SizedBox(height: 10),
          GridView.count(
            crossAxisCount: 3,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 8,
            mainAxisSpacing: 8,
            childAspectRatio: 1.05,
            children: _quantities.entries.map((entry) {
              final active = entry.value > 0;
              return InkWell(
                onTap: () {
                  setState(() {
                    _quantities[entry.key] = active ? 0 : 1;
                  });
                },
                onLongPress: () {
                  setState(() {
                    _quantities[entry.key] = (entry.value + 1).clamp(0, 5);
                  });
                },
                borderRadius: BorderRadius.circular(14),
                child: Ink(
                  decoration: BoxDecoration(
                    color: active ? MiddoColors.forest : const Color(0xFFFCF8F2),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color:
                          active ? MiddoColors.forest : const Color(0xFFDDD3BE),
                    ),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Text(
                        DateFormat('E').format(entry.key),
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w700,
                          color: active ? Colors.white70 : MiddoColors.inkSoft,
                        ),
                      ),
                      Text(
                        '${entry.key.day}',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w800,
                          color: active ? Colors.white : MiddoColors.ink,
                        ),
                      ),
                      Text(
                        '×${entry.value}',
                        style: TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w800,
                          color: active ? Colors.white : MiddoColors.inkSoft,
                        ),
                      ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
          const SizedBox(height: 8),
          const Text(
            'Tap to toggle · long-press to increase quantity',
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w600,
              color: MiddoColors.muted,
            ),
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(14),
            decoration: BoxDecoration(
              color: MiddoColors.white,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: MiddoColors.creamBorder),
            ),
            child: Column(
              children: [
                _row('Meals', '$totalQty × ${bdt.format(item.price)}'),
                _row('Delivery window', '12:00 PM'),
                _row('Pay from', 'Middo Balance'),
                const Divider(height: 20),
                _row('Total', bdt.format(total), bold: true),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
          child: FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: MiddoColors.forest,
              padding: const EdgeInsets.symmetric(vertical: 16),
            ),
            onPressed: totalQty == 0 || _submitting ? null : _confirm,
            child: Text(_submitting ? 'Scheduling…' : 'Confirm & Schedule'),
          ),
        ),
      ),
    );
  }

  Widget _row(String left, String right, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Text(
            left,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
              fontSize: bold ? 15 : 13,
              color: bold ? MiddoColors.ink : MiddoColors.inkSoft,
            ),
          ),
          const Spacer(),
          Text(
            right,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
              fontSize: bold ? 15 : 13,
              color: MiddoColors.ink,
            ),
          ),
        ],
      ),
    );
  }
}
