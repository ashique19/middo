import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/board_widgets.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _boards;
  late DateTime _date;

  @override
  void initState() {
    super.initState();
    _date = DateTime.now();
    _load();
  }

  String get _dateKey => DateFormat('yyyy-MM-dd').format(_date);

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await AppScope.of(context).boards(date: _dateKey);
      if (!mounted) return;
      setState(() {
        _boards = data;
        _loading = false;
      });
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.message;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  Future<void> _pickDate() async {
    final picked = await showDatePicker(
      context: context,
      initialDate: _date,
      firstDate: DateTime.now().subtract(const Duration(days: 30)),
      lastDate: DateTime.now().add(const Duration(days: 30)),
    );
    if (picked == null) return;
    setState(() => _date = picked);
    await _load();
  }

  int _sectionCount(String sectionKey, {List<String>? tabKeys}) {
    final section = (_boards?[sectionKey] as Map?)?.cast<String, dynamic>();
    if (section == null) return 0;
    if (tabKeys == null) {
      return (section['count'] as num?)?.toInt() ?? 0;
    }
    var total = 0;
    for (final key in tabKeys) {
      final bucket = (section[key] as Map?)?.cast<String, dynamic>();
      total += (bucket?['count'] as num?)?.toInt() ?? 0;
    }
    return total;
  }

  void _open(String path) {
    context.push('$path?date=$_dateKey');
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _boards == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null && _boards == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(_error!, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(onPressed: _load, child: const Text('Retry')),
            ],
          ),
        ),
      );
    }

    final links = <_HomeLink>[
      _HomeLink(
        title: 'Packages for today',
        subtitle: 'Unassigned meals and package orders',
        icon: Icons.inventory_outlined,
        count: _sectionCount(
          'packages',
          tabKeys: const ['unassigned_meals', 'orders'],
        ),
        onTap: () => _open('/boards/packages'),
      ),
      _HomeLink(
        title: 'Orders for today',
        subtitle: 'All · from packages · individual',
        icon: Icons.receipt_long_outlined,
        count: _sectionCount('orders', tabKeys: const ['all']),
        onTap: () => _open('/boards/orders'),
      ),
      _HomeLink(
        title: 'Grouping for today',
        subtitle: 'Ungrouped through delivered / failed',
        icon: Icons.account_tree_outlined,
        count: _sectionCount(
          'grouping',
          tabKeys: const [
            'ungrouped',
            'grouped_pending',
            'accepted',
            'packed',
            'picked',
            'delivered',
            'failed',
          ],
        ),
        onTap: () => _open('/boards/grouping'),
      ),
      _HomeLink(
        title: 'Rider cash collection today',
        subtitle: 'At rider · kitchen · Middo',
        icon: Icons.payments_outlined,
        count: _sectionCount(
          'cash_collection',
          tabKeys: const ['at_rider', 'kitchen', 'middo'],
        ),
        onTap: () => _open('/boards/cash-collection'),
      ),
      _HomeLink(
        title: 'Box requests',
        subtitle: 'Open kitchen box requests',
        icon: Icons.inventory_2_outlined,
        count: _sectionCount('box_requests'),
        onTap: () => _open('/boards/box-requests'),
      ),
      _HomeLink(
        title: 'Complaints',
        subtitle: 'Open customer complaints',
        icon: Icons.support_agent_outlined,
        count: _sectionCount('complaints'),
        onTap: () => _open('/boards/complaints'),
      ),
    ];

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Ops home',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ),
              TextButton.icon(
                onPressed: _pickDate,
                icon: const Icon(Icons.calendar_today, size: 18),
                label: Text(DateFormat('EEE d MMM').format(_date)),
              ),
            ],
          ),
          Text(
            'Open a board to work tabs and details.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: MiddoColors.muted,
                ),
          ),
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          const SizedBox(height: 12),
          for (final link in links) ...[
            _HomeLinkCard(link: link),
            const SizedBox(height: 10),
          ],
        ],
      ),
    );
  }
}

class _HomeLink {
  const _HomeLink({
    required this.title,
    required this.subtitle,
    required this.icon,
    required this.count,
    required this.onTap,
  });

  final String title;
  final String subtitle;
  final IconData icon;
  final int count;
  final VoidCallback onTap;
}

class _HomeLinkCard extends StatelessWidget {
  const _HomeLinkCard({required this.link});
  final _HomeLink link;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: link.onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: MiddoColors.orange.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(link.icon, color: MiddoColors.orange),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      link.title,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      link.subtitle,
                      style: const TextStyle(
                        color: MiddoColors.muted,
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
              BoardCountChip(link.count),
              const SizedBox(width: 4),
              const Icon(Icons.chevron_right, color: MiddoColors.muted),
            ],
          ),
        ),
      ),
    );
  }
}
