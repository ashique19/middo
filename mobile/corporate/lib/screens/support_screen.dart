import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../data/mock_repository.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class SupportScreen extends StatefulWidget {
  const SupportScreen({super.key, required this.orderId});

  final String orderId;

  @override
  State<SupportScreen> createState() => _SupportScreenState();
}

class _SupportScreenState extends State<SupportScreen> {
  final _composer = TextEditingController();

  @override
  void dispose() {
    _composer.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final repo = MockRepository.instance;
    final order = repo.orderById(widget.orderId);
    final thread = repo.supportThreadFor(widget.orderId);

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
              'ORDER #${widget.orderId}',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: MiddoColors.muted,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.5,
                  ),
            ),
            const Text('Complaint / Support'),
          ],
        ),
      ),
      body: Column(
        children: [
          Expanded(
            child: ListView(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
              children: [
                Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: MiddoColors.white,
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: MiddoColors.creamBorder),
                  ),
                  child: Column(
                    children: [
                      _meta(
                        'Date',
                        '${DateFormat('MMM d').format(order.deliveryDate)} · ${order.deliveryTime}',
                      ),
                      _meta('Meal', order.menuItem.name),
                      _meta('Total', bdt.format(order.totalAmount)),
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                ...thread.map((msg) {
                  final mine = !msg.fromSupport;
                  return Align(
                    alignment:
                        mine ? Alignment.centerRight : Alignment.centerLeft,
                    child: Container(
                      constraints: BoxConstraints(
                        maxWidth: MediaQuery.sizeOf(context).width * 0.85,
                      ),
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
                      decoration: BoxDecoration(
                        color: mine ? MiddoColors.creamDeep : MiddoColors.forest,
                        borderRadius: BorderRadius.only(
                          topLeft: const Radius.circular(18),
                          topRight: const Radius.circular(18),
                          bottomLeft: Radius.circular(mine ? 18 : 6),
                          bottomRight: Radius.circular(mine ? 6 : 18),
                        ),
                        border: mine
                            ? Border.all(color: MiddoColors.creamBorder)
                            : null,
                      ),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            mine
                                ? 'You${msg.category != null ? ' · ${msg.category}' : ''}'
                                : 'Middo Support',
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w800,
                              letterSpacing: 0.4,
                              color: mine
                                  ? MiddoColors.orange
                                  : Colors.white70,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            msg.body,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              height: 1.4,
                              color: mine ? MiddoColors.ink : Colors.white,
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                }),
              ],
            ),
          ),
          SafeArea(
            top: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(18, 0, 18, 12),
              child: Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _composer,
                      decoration: const InputDecoration(
                        hintText: 'Describe the issue…',
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                  FilledButton(
                    onPressed: () {
                      if (_composer.text.trim().isEmpty) return;
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Message sent to Middo Support'),
                          backgroundColor: MiddoColors.forest,
                        ),
                      );
                      _composer.clear();
                    },
                    child: const Text('Send'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _meta(String label, String value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Text(
            label,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              fontSize: 13,
              color: MiddoColors.inkSoft,
            ),
          ),
          const Spacer(),
          Flexible(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}
