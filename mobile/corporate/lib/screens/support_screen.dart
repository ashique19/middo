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
  Future<
      ({
        CorporateOrder order,
        List<SupportMessage> messages,
        bool hasExisting,
        bool isResolved,
      })>? _future;
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

  Future<void> _send({required bool hasExisting}) async {
    final text = _composer.text.trim();
    final minLen = hasExisting ? 5 : 10;
    if (text.length < minLen) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            hasExisting
                ? 'Reply must be at least 5 characters.'
                : 'Please describe the issue in at least 10 characters.',
          ),
        ),
      );
      return;
    }
    setState(() => _sending = true);
    try {
      await AppScope.of(context).submitSupport(
        orderId: widget.orderId,
        message: text,
        category: hasExisting ? null : _category,
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
            return const MiddoPageLoader(message: 'Loading support…');
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }
          final data = snapshot.data!;
          final order = data.order;
          final thread = data.messages;
          final hasExisting = data.hasExisting;
          final isResolved = data.isResolved;

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
                    if (isResolved) ...[
                      const SizedBox(height: 14),
                      Container(
                        width: double.infinity,
                        padding: const EdgeInsets.all(12),
                        decoration: BoxDecoration(
                          color: const Color(0xFFECFDF5),
                          borderRadius: BorderRadius.circular(14),
                          border: Border.all(color: const Color(0xFFA7F3D0)),
                        ),
                        child: const Text(
                          'This complaint is complete',
                          style: TextStyle(
                            fontSize: 13,
                            fontWeight: FontWeight.w800,
                            color: Color(0xFF065F46),
                          ),
                        ),
                      ),
                    ],
                    const SizedBox(height: 16),
                    if (!hasExisting) ...[
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
                          hintText:
                              'What went wrong? Include floor, desk, or timing details…',
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
              if (!hasExisting)
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
                            _sending ? null : () => _send(hasExisting: false),
                        child: Text(_sending ? 'Sending…' : 'Send'),
                      ),
                    ),
                  ),
                )
              else if (!isResolved)
                SafeArea(
                  top: false,
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
                    child: Row(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _composer,
                            minLines: 1,
                            maxLines: 4,
                            textCapitalization: TextCapitalization.sentences,
                            decoration: const InputDecoration(
                              hintText: 'Write a reply…',
                              isDense: true,
                            ),
                          ),
                        ),
                        const SizedBox(width: 10),
                        FilledButton(
                          style: FilledButton.styleFrom(
                            backgroundColor: MiddoColors.orange,
                            padding: const EdgeInsets.symmetric(
                              horizontal: 16,
                              vertical: 14,
                            ),
                          ),
                          onPressed: _sending
                              ? null
                              : () => _send(hasExisting: true),
                          child: Text(_sending ? '…' : 'Reply'),
                        ),
                      ],
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
