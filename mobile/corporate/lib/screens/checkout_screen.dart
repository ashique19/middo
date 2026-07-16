import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../data/mock_repository.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key, required this.menuItemId});

  final String menuItemId;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  late final Map<DateTime, int> _quantities = {
    DateTime(2026, 7, 17): 12,
    DateTime(2026, 7, 18): 0,
    DateTime(2026, 7, 20): 0,
    DateTime(2026, 7, 21): 0,
    DateTime(2026, 7, 22): 0,
    DateTime(2026, 7, 23): 0,
  };

  @override
  Widget build(BuildContext context) {
    final item = MockRepository.instance.menuById(widget.menuItemId);
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
                Image.asset(item.imageAsset, height: 160, fit: BoxFit.cover),
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
          const Text(
            'Same-day cutoff open · closes in 02h 14m',
            style: TextStyle(
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
                    _quantities[entry.key] = active ? 0 : 12;
                  });
                },
                borderRadius: BorderRadius.circular(14),
                child: Ink(
                  decoration: BoxDecoration(
                    color: active ? MiddoColors.forest : const Color(0xFFFCF8F2),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: active ? MiddoColors.forest : const Color(0xFFDDD3BE),
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
                _row('Delivery window', '11:30–12:00'),
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
            onPressed: totalQty == 0
                ? null
                : () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      SnackBar(
                        content: Text(
                          'Scheduled $totalQty meals · ${bdt.format(total)}',
                        ),
                        backgroundColor: MiddoColors.forest,
                      ),
                    );
                    context.go('/schedule');
                  },
            child: const Text('Confirm & Schedule'),
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
