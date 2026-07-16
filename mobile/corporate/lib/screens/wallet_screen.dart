import 'package:flutter/material.dart';

import '../data/mock_repository.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class WalletScreen extends StatefulWidget {
  const WalletScreen({super.key});

  @override
  State<WalletScreen> createState() => _WalletScreenState();
}

class _WalletScreenState extends State<WalletScreen> {
  int _selected = 5000;
  final _custom = TextEditingController(text: '5000');

  @override
  void dispose() {
    _custom.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final user = MockRepository.instance.user;

    return SafeArea(
      child: ListView(
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
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: Colors.white,
                          foregroundColor: MiddoColors.forest,
                        ),
                        onPressed: () {},
                        child: const Text('Add Money'),
                      ),
                    ),
                    const SizedBox(width: 8),
                    Expanded(
                      child: OutlinedButton(
                        style: OutlinedButton.styleFrom(
                          foregroundColor: Colors.white,
                          side: BorderSide(
                            color: Colors.white.withValues(alpha: 0.35),
                          ),
                          backgroundColor: Colors.white.withValues(alpha: 0.14),
                        ),
                        onPressed: () {},
                        child: const Text('Profile'),
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
          ),
          const SizedBox(height: 12),
          FilledButton(
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Continue to payment · ${bdt.format(_selected)}'),
                  backgroundColor: MiddoColors.forest,
                ),
              );
            },
            child: const Text('Continue to payment'),
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
                _row('Company', user.companyName),
                _row('Delivery area', user.area),
                _row('Contact', user.email),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _row(String left, String right) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Text(
            left,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 13,
              color: MiddoColors.inkSoft,
            ),
          ),
          const Spacer(),
          Flexible(
            child: Text(
              right,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
