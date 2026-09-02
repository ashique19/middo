import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_ui.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  Future<List<dynamic>>? _alerts;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _alerts ??= AppScope.of(context).alerts();
  }

  Future<void> _reload() async {
    setState(() {
      _alerts = AppScope.of(context).alerts();
    });
    await _alerts;
  }

  Future<void> _markAll() async {
    setState(() => _busy = true);
    try {
      await AppScope.of(context).markAllAlertsRead();
      if (!mounted) return;
      showKitchenSnack(context, 'All alerts marked read.');
      await _reload();
    } catch (e) {
      if (mounted) showKitchenSnack(context, '$e', error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  Future<void> _openAlert(Map raw) async {
    final id = raw['id'];
    if (id is int && raw['is_unread'] == true) {
      try {
        await AppScope.of(context).markAlertRead(id);
      } catch (_) {}
    }
    if (!mounted) return;
    final groupId = raw['order_group_id'];
    if (groupId != null) {
      context.go('/groups');
    } else {
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Alerts'),
        actions: [
          TextButton(
            onPressed: _busy ? null : _markAll,
            child: const Text('Mark all read'),
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<List<dynamic>>(
          future: _alerts,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return KitchenError(snap.error!, onRetry: _reload);
            }
            final alerts = snap.data ?? const [];
            if (alerts.isEmpty) {
              return ListView(children: const [KitchenEmpty('No alerts.')]);
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: alerts.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final raw = alerts[i] as Map;
                final unread = raw['is_unread'] == true;
                return KitchenPanel(
                  onTap: () => _openAlert(raw),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              raw['title']?.toString() ?? 'Alert',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                color: unread
                                    ? MiddoColors.ink
                                    : MiddoColors.inkSoft,
                              ),
                            ),
                          ),
                          if (unread)
                            const KitchenStatusChip('New', positive: true),
                        ],
                      ),
                      const SizedBox(height: 6),
                      Text(
                        raw['body']?.toString() ?? '',
                        style: const TextStyle(color: MiddoColors.inkSoft),
                      ),
                    ],
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
