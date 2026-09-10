import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_mobile_header.dart';
import '../widgets/delivery_ui.dart';

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
      showDeliverySnack(context, 'All alerts marked read.');
      await _reload();
    } catch (e) {
      if (mounted) showDeliverySnack(context, '$e', error: true);
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
    final runId = raw['run_id'];
    if (runId != null) {
      context.push('/runs/$runId');
    } else {
      await _reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const DeliveryMobileHeader(title: 'Alerts', showBack: true),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<List<dynamic>>(
          future: _alerts,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return DeliveryError(snap.error!, onRetry: _reload);
            }
            final alerts = snap.data ?? const [];
            if (alerts.isEmpty) {
              return ListView(
                children: [
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: _busy ? null : _markAll,
                      child: const Text('Mark all read'),
                    ),
                  ),
                  const DeliveryEmpty('No alerts.'),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: alerts.length + 1,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                if (i == 0) {
                  return Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: _busy ? null : _markAll,
                      child: const Text('Mark all read'),
                    ),
                  );
                }
                final alert =
                    (alerts[i - 1] as Map).cast<String, dynamic>();
                final unread = alert['is_unread'] == true;
                return DeliveryPanel(
                  onTap: () => _openAlert(alert),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              alert['title']?.toString() ?? 'Alert',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                color: unread
                                    ? MiddoColors.ink
                                    : MiddoColors.inkSoft,
                              ),
                            ),
                          ),
                          if (unread)
                            Container(
                              width: 8,
                              height: 8,
                              decoration: const BoxDecoration(
                                color: MiddoColors.orange,
                                shape: BoxShape.circle,
                              ),
                            ),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        alert['body']?.toString() ?? '',
                        style: const TextStyle(
                          color: MiddoColors.inkSoft,
                          fontWeight: FontWeight.w600,
                          fontSize: 13,
                        ),
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
