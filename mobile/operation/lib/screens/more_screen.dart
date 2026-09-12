import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/offline_mutation_queue.dart';
import '../data/push_notification_service.dart';
import '../theme/middo_colors.dart';
import '../widgets/pickers.dart';

class MoreScreen extends StatefulWidget {
  const MoreScreen({super.key});

  @override
  State<MoreScreen> createState() => _MoreScreenState();
}

class _MoreScreenState extends State<MoreScreen> {
  int _queued = 0;
  Map<String, dynamic>? _me;
  Map<String, dynamic>? _sla;
  Map<String, dynamic>? _opsDay;
  List<dynamic> _complaints = const [];
  String? _error;
  bool _flushing = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final repo = AppScope.of(context);
      final results = await Future.wait([
        repo.me(),
        repo.slaBoard(),
        repo.opsDay(),
        repo.complaints(),
        OfflineMutationQueue.instance.count(),
      ]);
      if (!mounted) return;
      setState(() {
        _me = results[0] as Map<String, dynamic>;
        _sla = results[1] as Map<String, dynamic>;
        _opsDay = results[2] as Map<String, dynamic>;
        _complaints = ((results[3] as Map)['complaints'] as List?) ?? const [];
        _queued = results[4] as int;
        _error = null;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _error = e.message);
    }
  }

  Future<void> _flushQueue() async {
    setState(() => _flushing = true);
    try {
      final result = await OfflineMutationQueue.instance.flush(
        AppScope.of(context).repo.client,
      );
      if (!mounted) return;
      showSnack(
        context,
        'Flushed ${result['flushed']}; remaining ${result['remaining']}'
        '${result['error'] != null ? ' · ${result['error']}' : ''}',
      );
      await _load();
    } catch (e) {
      if (!mounted) return;
      showSnack(context, e.toString());
    } finally {
      if (mounted) setState(() => _flushing = false);
    }
  }

  Future<void> _logout() async {
    final scope = AppScope.of(context);
    await PushNotificationService.instance.unregister();
    await scope.logout();
    if (!mounted) return;
    context.go('/login');
  }

  @override
  Widget build(BuildContext context) {
    final user = (_me?['user'] as Map?)?.cast<String, dynamic>();
    final slaCounts = (_sla?['counts'] as Map?)?.cast<String, dynamic>() ?? {};
    final totals = (_opsDay?['totals'] as Map?)?.cast<String, dynamic>() ?? {};

    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        if (_error != null)
          Text(_error!, style: const TextStyle(color: Colors.red)),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: Text(user?['name']?.toString() ?? 'Operation'),
          subtitle: Text(user?['mobile']?.toString() ?? ''),
          trailing: TextButton(onPressed: _logout, child: const Text('Log out')),
        ),
        const Divider(),
        ListTile(
          leading: const Icon(Icons.notifications_outlined),
          title: const Text('Alerts'),
          onTap: () => context.push('/alerts'),
        ),
        ListTile(
          leading: const Icon(Icons.search),
          title: const Text('Order search'),
          onTap: () => context.push('/orders'),
        ),
        ListTile(
          leading: const Icon(Icons.speed),
          title: const Text('Dispatch SLA'),
          subtitle: Text(
            'Unassigned ${slaCounts['unassigned_closed'] ?? slaCounts['unassigned_total'] ?? 0} · '
            'Late pack ${slaCounts['late_to_pack'] ?? 0}',
          ),
          onTap: () => context.push('/sla'),
        ),
        ListTile(
          leading: const Icon(Icons.checklist),
          title: const Text('Ops day'),
          subtitle: Text(
            'Attention ${totals['attention'] ?? 0} · '
            'Rows ${totals['rows'] ?? 0}',
          ),
        ),
        ListTile(
          leading: const Icon(Icons.report_problem_outlined),
          title: Text('Open complaints (${_complaints.length})'),
          subtitle: const Text('Tap a complaint to reply / complete.'),
        ),
        ..._complaints.take(8).map((raw) {
          final row = Map<String, dynamic>.from(raw as Map);
          final id = row['id'] as int? ?? 0;
          return ListTile(
            dense: true,
            title: Text(row['category']?.toString() ?? 'Complaint #$id'),
            subtitle: Text(row['message']?.toString() ?? ''),
            trailing: const Icon(Icons.chevron_right),
            onTap: id == 0 ? null : () => context.push('/complaints/$id'),
          );
        }),
        ListTile(
          leading: const Icon(Icons.cloud_off_outlined),
          title: Text('Offline queue ($_queued)'),
          subtitle: const Text(
            'Flush pending accept/assign mutations when back online.',
            style: TextStyle(color: MiddoColors.muted),
          ),
          trailing: TextButton(
            onPressed: _queued == 0 || _flushing ? null : _flushQueue,
            child: Text(_flushing ? '…' : 'Flush'),
          ),
        ),
        const SizedBox(height: 12),
        OutlinedButton(onPressed: _load, child: const Text('Refresh')),
      ],
    );
  }
}
