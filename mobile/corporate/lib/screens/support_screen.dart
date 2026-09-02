import 'dart:io';

import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:image_picker/image_picker.dart';
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
  final _picker = ImagePicker();
  String _category = 'delivery';
  String? _attachmentPath;
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

  Future<void> _pickAttachment() async {
    try {
      final file = await _picker.pickImage(
        source: ImageSource.gallery,
        imageQuality: 85,
        maxWidth: 1600,
      );
      if (file == null || !mounted) return;
      setState(() => _attachmentPath = file.path);
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Could not pick image: $e')),
      );
    }
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
        attachmentPath: _attachmentPath,
      );
      _composer.clear();
      setState(() => _attachmentPath = null);
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

  Widget _attachmentChip() {
    final path = _attachmentPath;
    if (path == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Align(
        alignment: Alignment.centerLeft,
        child: Stack(
          clipBehavior: Clip.none,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Image.file(
                File(path),
                width: 72,
                height: 72,
                fit: BoxFit.cover,
              ),
            ),
            Positioned(
              top: -6,
              right: -6,
              child: Material(
                color: MiddoColors.ink,
                shape: const CircleBorder(),
                child: InkWell(
                  customBorder: const CircleBorder(),
                  onTap: () => setState(() => _attachmentPath = null),
                  child: const Padding(
                    padding: EdgeInsets.all(4),
                    child: Icon(Icons.close, size: 14, color: Colors.white),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _messageBubble(SupportMessage msg, bool mine) {
    final url = msg.attachmentUrl;
    return Align(
      alignment: mine ? Alignment.centerRight : Alignment.centerLeft,
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
          border: mine ? Border.all(color: MiddoColors.creamBorder) : null,
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
                color: mine ? MiddoColors.orange : Colors.white70,
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
            if (url != null && url.isNotEmpty) ...[
              const SizedBox(height: 8),
              ClipRRect(
                borderRadius: BorderRadius.circular(10),
                child: Image.network(
                  url,
                  height: 140,
                  width: double.infinity,
                  fit: BoxFit.cover,
                  errorBuilder: (_, __, ___) => Container(
                    height: 80,
                    alignment: Alignment.center,
                    color: Colors.black12,
                    child: Icon(
                      Icons.broken_image_outlined,
                      color: mine ? MiddoColors.inkSoft : Colors.white70,
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
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
                      _attachmentChip(),
                      Row(
                        children: [
                          TextButton.icon(
                            onPressed: _sending ? null : _pickAttachment,
                            icon: const Icon(Icons.image_outlined, size: 18),
                            label: Text(
                              _attachmentPath == null
                                  ? 'Attach photo'
                                  : 'Change photo',
                            ),
                          ),
                          const Spacer(),
                          const Flexible(
                            child: Text(
                              'Minimum 10 characters so support can act quickly.',
                              textAlign: TextAlign.end,
                              style: TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                                color: MiddoColors.inkSoft,
                              ),
                            ),
                          ),
                        ],
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
                        return _messageBubble(msg, mine);
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
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        _attachmentChip(),
                        Row(
                          crossAxisAlignment: CrossAxisAlignment.end,
                          children: [
                            IconButton(
                              onPressed: _sending ? null : _pickAttachment,
                              icon: const Icon(Icons.image_outlined),
                              tooltip: 'Attach photo',
                            ),
                            Expanded(
                              child: TextField(
                                controller: _composer,
                                minLines: 1,
                                maxLines: 4,
                                textCapitalization:
                                    TextCapitalization.sentences,
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
