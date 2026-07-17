import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../models/models.dart';
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
  String _category = 'delivery';
  Future<({CorporateOrder order, List<SupportMessage> messages, bool hasExisting})>?
      _future;
  bool _sending = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).supportThread(widget.orderId);
  }

  @override
  void dispose() {
    _composer.dispose();
    super.dispose();
  }

  Future<void> _reload() async {
    final next = AppScope.of(context).supportThread(widget.orderId);
    setState(() => _future = next);
    await next;
  }

  Future<void> _send(bool hasExisting) async {
    if (hasExisting) return;
    if (_composer.text.trim().length < 10) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please describe the issue in at least 10 characters.')),
      );
      return;
    }
    setState(() => _sending = true);
    try {
      await AppScope.of(context).submitSupport(
        orderId: widget.orderId,
        category: _category,
        message: _composer.text.trim(),
      );
      _composer.clear();
      await _reload();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Message sent to Middo Support'),
          backgroundColor: MiddoColors.forest,
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(e.message)),
      );
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  @override
  Widget build(BuildContext context) {
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
      body: FutureBuilder(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }
          final data = snapshot.data!;
          final order = data.order;
          final thread = data.messages;

          return Column(
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
                          MetaRow(
                            label: 'Date',
                            value:
                                '${DateFormat('MMM d').format(order.deliveryDate)} · ${order.deliveryTime}',
                          ),
                          MetaRow(label: 'Meal', value: order.menuItem.name),
                          MetaRow(
                            label: 'Total',
                            value: bdt.format(order.totalAmount),
                            valueColor: MiddoColors.orange,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    if (!data.hasExisting) ...[
                      DropdownButtonFormField<String>(
                        value: _category,
                        decoration: const InputDecoration(labelText: 'CATEGORY'),
                        items: const [
                          DropdownMenuItem(
                            value: 'delivery',
                            child: Text('Delivery Issue'),
                          ),
                          DropdownMenuItem(
                            value: 'food_quality',
                            child: Text('Food Quality'),
                          ),
                          DropdownMenuItem(
                            value: 'payment',
                            child: Text('Payment Issue'),
                          ),
                          DropdownMenuItem(
                            value: 'other',
                            child: Text('Other'),
                          ),
                        ],
                        onChanged: (value) {
                          if (value != null) setState(() => _category = value);
                        },
                      ),
                      const SizedBox(height: 14),
                      TextField(
                        controller: _composer,
                        minLines: 6,
                        maxLines: 10,
                        textCapitalization: TextCapitalization.sentences,
                        keyboardType: TextInputType.multiline,
                        decoration: const InputDecoration(
                          labelText: 'DESCRIBE THE ISSUE',
                          hintText: 'What went wrong? Include floor, desk, or timing details…',
                          alignLabelWithHint: true,
                        ),
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Minimum 10 characters so support can act quickly.',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: MiddoColors.inkSoft,
                        ),
                      ),
                    ] else ...[
                      const Text(
                        'Conversation',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 10),
                      ...thread.map((msg) {
                        final mine = !msg.fromSupport;
                        return Align(
                          alignment: mine
                              ? Alignment.centerRight
                              : Alignment.centerLeft,
                          child: Container(
                            constraints: BoxConstraints(
                              maxWidth:
                                  MediaQuery.sizeOf(context).width * 0.85,
                            ),
                            margin: const EdgeInsets.only(bottom: 10),
                            padding:
                                const EdgeInsets.fromLTRB(12, 10, 12, 10),
                            decoration: BoxDecoration(
                              color: mine
                                  ? MiddoColors.creamDeep
                                  : MiddoColors.forest,
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
                                    color:
                                        mine ? MiddoColors.ink : Colors.white,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        );
                      }),
                    ],
                  ],
                ),
              ),
              if (!data.hasExisting)
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
                    child: SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        style: FilledButton.styleFrom(
                          backgroundColor: MiddoColors.orange,
                          padding: const EdgeInsets.symmetric(vertical: 16),
                        ),
                        onPressed:
                            _sending ? null : () => _send(data.hasExisting),
                        child: Text(_sending ? 'Sending…' : 'Send'),
                      ),
                    ),
                  ),
                ),
            ],
          );
        },
      ),
    );
  }

}
