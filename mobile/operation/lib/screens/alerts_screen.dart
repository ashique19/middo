import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/pickers.dart';

class AlertsScreen extends StatefulWidget {
  const AlertsScreen({super.key});

  @override
  State<AlertsScreen> createState() => _AlertsScreenState();
}

class _AlertsScreenState extends State<AlertsScreen> {
  bool _loading = true;
  String? _error;
  int _unread = 0;
  List<dynamic> _alerts = const [];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await AppScope.of(context).alerts();
      if (!mounted) return;
      setState(() {
        _unread = data['unread_count'] as int? ?? 0;
        _alerts = (data['alerts'] as List?) ?? const [];
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

  Future<void> _markRead(int id) async {
    try {
      await AppScope.of(context).markAlertRead(id);
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  Future<void> _markAll() async {
    try {
      await AppScope.of(context).markAllAlertsRead();
      await _load();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Alerts ($_unread unread)'),
        actions: [
          TextButton(onPressed: _markAll, child: const Text('Read all')),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(_error!),
                      FilledButton(
                        onPressed: _load,
                        child: const Text('Retry'),
                      ),
                    ],
                  ),
                )
              : RefreshIndicator(
                  onRefresh: _load,
                  child: ListView(
                    padding: const EdgeInsets.all(16),
                    children: [
                      if (_alerts.isEmpty)
                        const Text(
                          'No alerts.',
                          style: TextStyle(color: MiddoColors.muted),
                        ),
                      ..._alerts.map((raw) {
                        final row = Map<String, dynamic>.from(raw as Map);
                        final id = row['id'] as int? ?? 0;
                        final unread = row['is_unread'] == true ||
                            row['read_at'] == null;
                        return Card(
                          child: ListTile(
                            leading: Icon(
                              unread
                                  ? Icons.notifications_active
                                  : Icons.notifications_none,
                              color: unread
                                  ? MiddoColors.orange
                                  : MiddoColors.muted,
                            ),
                            title: Text(row['title']?.toString() ?? 'Alert'),
                            subtitle: Text(row['body']?.toString() ?? ''),
                            trailing: unread
                                ? TextButton(
                                    onPressed:
                                        id == 0 ? null : () => _markRead(id),
                                    child: const Text('Read'),
                                  )
                                : null,
                          ),
                        );
                      }),
                    ],
                  ),
                ),
    );
  }
}
