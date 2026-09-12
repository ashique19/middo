import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/pickers.dart';

class ComplaintDetailScreen extends StatefulWidget {
  const ComplaintDetailScreen({super.key, required this.complaintId});

  final int complaintId;

  @override
  State<ComplaintDetailScreen> createState() => _ComplaintDetailScreenState();
}

class _ComplaintDetailScreenState extends State<ComplaintDetailScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _complaint;
  final _reply = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _reply.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await AppScope.of(context).showComplaint(widget.complaintId);
      if (!mounted) return;
      setState(() {
        _complaint = Map<String, dynamic>.from(data['complaint'] as Map);
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    }
  }

  Future<void> _sendReply() async {
    final message = _reply.text.trim();
    if (message.length < 5) {
      showSnack(context, 'Reply needs at least 5 characters.');
      return;
    }
    try {
      await AppScope.of(context).replyComplaint(
        widget.complaintId,
        message: message,
      );
      if (!mounted) return;
      _reply.clear();
      showSnack(context, 'Reply sent.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Future<void> _complete() async {
    try {
      await AppScope.of(context).completeComplaint(widget.complaintId);
      if (!mounted) return;
      showSnack(context, 'Complaint completed.');
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final thread = (_complaint?['thread'] as List?) ?? const [];

    return Scaffold(
      appBar: AppBar(title: Text('Complaint #${widget.complaintId}')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : ListView(
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(
                      _complaint?['category']?.toString() ?? 'Complaint',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    Text(
                      'Status: ${_complaint?['status'] ?? '—'}',
                      style: const TextStyle(color: MiddoColors.muted),
                    ),
                    const SizedBox(height: 8),
                    Text(_complaint?['message']?.toString() ?? ''),
                    const Divider(height: 28),
                    Text(
                      'Thread',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 8),
                    if (thread.isEmpty)
                      const Text(
                        'No replies yet.',
                        style: TextStyle(color: MiddoColors.muted),
                      ),
                    ...thread.map((raw) {
                      final row = Map<String, dynamic>.from(raw as Map);
                      return Card(
                        child: ListTile(
                          title: Text(row['message']?.toString() ?? ''),
                          subtitle: Text(
                            '${row['created_by'] ?? ''} · ${row['created_at'] ?? ''}',
                          ),
                        ),
                      );
                    }),
                    const SizedBox(height: 16),
                    TextField(
                      controller: _reply,
                      decoration: const InputDecoration(
                        labelText: 'Reply',
                      ),
                      maxLines: 3,
                    ),
                    const SizedBox(height: 8),
                    FilledButton(
                      onPressed: _sendReply,
                      child: const Text('Send reply'),
                    ),
                    const SizedBox(height: 8),
                    OutlinedButton(
                      onPressed: _complete,
                      child: const Text('Mark complete'),
                    ),
                  ],
                ),
    );
  }
}
