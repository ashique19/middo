import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';

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

    final boards = _boards ?? const <String, dynamic>{};
    final packages = (boards['packages'] as Map?)?.cast<String, dynamic>() ?? {};
    final orders = (boards['orders'] as Map?)?.cast<String, dynamic>() ?? {};
    final grouping = (boards['grouping'] as Map?)?.cast<String, dynamic>() ?? {};
    final cash = (boards['cash_collection'] as Map?)?.cast<String, dynamic>() ?? {};
    final boxRequests = (boards['box_requests'] as Map?)?.cast<String, dynamic>() ?? {};
    final complaints = (boards['complaints'] as Map?)?.cast<String, dynamic>() ?? {};

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Ops dashboard',
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
          if (_error != null)
            Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text(_error!, style: const TextStyle(color: Colors.red)),
            ),
          const SizedBox(height: 8),
          _BoardSection(
            title: 'Packages for today',
            child: _TabbedBoard(
              tabs: const [
                _TabSpec('Unassigned meals', 'unassigned_meals'),
                _TabSpec('Package orders', 'orders'),
              ],
              data: packages,
              itemBuilder: (item) => _PackageOrOrderTile(item: item),
            ),
          ),
          _BoardSection(
            title: 'Orders for today',
            child: _TabbedBoard(
              tabs: const [
                _TabSpec('All', 'all'),
                _TabSpec('From packages', 'package'),
                _TabSpec('Individual', 'individual'),
              ],
              data: orders,
              itemBuilder: (item) => _OrderTile(item: item),
            ),
          ),
          _BoardSection(
            title: 'Grouping for today',
            subtitle: 'Paid · green · Unpaid · yellow · Cancelled · red',
            child: _TabbedBoard(
              tabs: const [
                _TabSpec('Ungrouped', 'ungrouped'),
                _TabSpec('Grouped', 'grouped_pending'),
                _TabSpec('Accepted', 'accepted'),
                _TabSpec('Packed', 'packed'),
                _TabSpec('Picked', 'picked'),
                _TabSpec('Delivered', 'delivered'),
                _TabSpec('Failed', 'failed'),
              ],
              data: grouping,
              itemBuilder: (item) => _OrderTile(item: item, showPaymentBadge: true),
            ),
          ),
          _BoardSection(
            title: 'Rider cash collection today',
            child: _TabbedBoard(
              tabs: const [
                _TabSpec('At rider', 'at_rider'),
                _TabSpec('Kitchen', 'kitchen'),
                _TabSpec('Middo', 'middo'),
              ],
              data: cash,
              itemBuilder: (item) => _CashTile(item: item),
            ),
          ),
          _BoardSection(
            title: 'Box requests',
            trailingCount: (boxRequests['count'] as num?)?.toInt() ?? 0,
            onOpenAll: () => context.go('/boxes'),
            child: _SimpleList(
              items: (boxRequests['items'] as List?) ?? const [],
              emptyLabel: 'No open box requests',
              itemBuilder: (item) {
                final map = (item as Map).cast<String, dynamic>();
                return ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  title: Text(map['kitchen_name']?.toString() ?? 'Kitchen'),
                  subtitle: Text(
                    'Qty ${(map['quantity'] ?? map['allocated_qty'] ?? '—')} · ${map['status'] ?? ''}',
                  ),
                  onTap: () => context.go('/boxes'),
                );
              },
            ),
          ),
          _BoardSection(
            title: 'Complaints',
            trailingCount: (complaints['count'] as num?)?.toInt() ?? 0,
            onOpenAll: () => context.push('/more'),
            child: _SimpleList(
              items: (complaints['items'] as List?) ?? const [],
              emptyLabel: 'No open complaints',
              itemBuilder: (item) {
                final map = (item as Map).cast<String, dynamic>();
                return ListTile(
                  dense: true,
                  contentPadding: EdgeInsets.zero,
                  title: Text(map['category']?.toString() ?? 'Complaint #${map['id']}'),
                  subtitle: Text(
                    map['message']?.toString() ?? '',
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  onTap: () {
                    final id = map['id'];
                    if (id is int) context.push('/complaints/$id');
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class _TabSpec {
  const _TabSpec(this.label, this.key);
  final String label;
  final String key;
}

class _BoardSection extends StatelessWidget {
  const _BoardSection({
    required this.title,
    required this.child,
    this.subtitle,
    this.trailingCount,
    this.onOpenAll,
  });

  final String title;
  final String? subtitle;
  final Widget child;
  final int? trailingCount;
  final VoidCallback? onOpenAll;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(12, 12, 12, 8),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    title,
                    style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
                  ),
                ),
                if (trailingCount != null)
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: _CountChip(trailingCount!),
                  ),
                if (onOpenAll != null)
                  TextButton(onPressed: onOpenAll, child: const Text('Open')),
              ],
            ),
            if (subtitle != null)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Text(
                  subtitle!,
                  style: const TextStyle(color: MiddoColors.muted, fontSize: 12),
                ),
              ),
            child,
          ],
        ),
      ),
    );
  }
}

class _TabbedBoard extends StatefulWidget {
  const _TabbedBoard({
    required this.tabs,
    required this.data,
    required this.itemBuilder,
  });

  final List<_TabSpec> tabs;
  final Map<String, dynamic> data;
  final Widget Function(Map<String, dynamic> item) itemBuilder;

  @override
  State<_TabbedBoard> createState() => _TabbedBoardState();
}

class _TabbedBoardState extends State<_TabbedBoard>
    with SingleTickerProviderStateMixin {
  late final TabController _controller;

  @override
  void initState() {
    super.initState();
    _controller = TabController(length: widget.tabs.length, vsync: this);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Map<String, dynamic> _bucket(String key) {
    final raw = widget.data[key];
    if (raw is Map) return raw.cast<String, dynamic>();
    return const {};
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        TabBar(
          controller: _controller,
          isScrollable: true,
          tabAlignment: TabAlignment.start,
          labelPadding: const EdgeInsets.only(right: 12),
          tabs: [
            for (final tab in widget.tabs)
              Tab(
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(tab.label),
                    const SizedBox(width: 6),
                    _CountChip((_bucket(tab.key)['count'] as num?)?.toInt() ?? 0),
                  ],
                ),
              ),
          ],
        ),
        SizedBox(
          height: 220,
          child: TabBarView(
            controller: _controller,
            children: [
              for (final tab in widget.tabs)
                _SimpleList(
                  items: (_bucket(tab.key)['items'] as List?) ?? const [],
                  emptyLabel: 'Nothing here',
                  itemBuilder: (item) =>
                      widget.itemBuilder((item as Map).cast<String, dynamic>()),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class _SimpleList extends StatelessWidget {
  const _SimpleList({
    required this.items,
    required this.itemBuilder,
    required this.emptyLabel,
  });

  final List items;
  final Widget Function(dynamic item) itemBuilder;
  final String emptyLabel;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 24),
        child: Center(
          child: Text(emptyLabel, style: const TextStyle(color: MiddoColors.muted)),
        ),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.only(top: 8),
      itemCount: items.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (_, i) => itemBuilder(items[i]),
    );
  }
}

class _CountChip extends StatelessWidget {
  const _CountChip(this.count);
  final int count;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: MiddoColors.orange.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        '$count',
        style: const TextStyle(
          fontWeight: FontWeight.w700,
          fontSize: 12,
          color: MiddoColors.orange,
        ),
      ),
    );
  }
}

class _PaymentBadge extends StatelessWidget {
  const _PaymentBadge(this.keyName, this.label);
  final String keyName;
  final String label;

  Color get _color {
    switch (keyName) {
      case 'paid':
        return const Color(0xFF1B7F4E);
      case 'rotten':
        return const Color(0xFFB42318);
      default:
        return const Color(0xFFB54708);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 2),
      decoration: BoxDecoration(
        color: _color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: _color.withValues(alpha: 0.35)),
      ),
      child: Text(
        label,
        style: TextStyle(color: _color, fontSize: 11, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class _OrderTile extends StatelessWidget {
  const _OrderTile({required this.item, this.showPaymentBadge = false});

  final Map<String, dynamic> item;
  final bool showPaymentBadge;

  @override
  Widget build(BuildContext context) {
    final id = item['id'];
    return ListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      title: Text(
        '#$id · ${item['menu_name'] ?? 'Order'} · qty ${item['quantity'] ?? 1}',
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: Text(
        [
          item['customer_name'],
          item['area_name'],
          item['order_status'],
          if (item['is_package'] == true) 'package',
        ].where((e) => e != null && e.toString().isNotEmpty).join(' · '),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      trailing: showPaymentBadge
          ? _PaymentBadge(
              item['payment_badge']?.toString() ?? 'unpaid',
              item['payment_badge_label']?.toString() ?? 'Unpaid',
            )
          : null,
      onTap: () {
        if (id is int) context.push('/orders/$id');
      },
    );
  }
}

class _PackageOrOrderTile extends StatelessWidget {
  const _PackageOrOrderTile({required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    if (item.containsKey('order_status')) {
      return _OrderTile(item: item);
    }

    return ListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      title: Text(item['package_name']?.toString() ?? 'Package'),
      subtitle: Text(
        [
          item['customer_name'],
          item['customer_mobile'],
          'qty ${item['quantity'] ?? 1}',
          'meal not assigned',
        ].where((e) => e != null && e.toString().isNotEmpty).join(' · '),
      ),
    );
  }
}

class _CashTile extends StatelessWidget {
  const _CashTile({required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final amount = item['cash_due'] ?? item['amount'] ?? 0;
    return ListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      title: Text(
        item['rider_name']?.toString() ??
            (item['id'] != null ? 'Handover #${item['id']}' : 'Cash'),
      ),
      subtitle: Text(
        [
          if (item['customer_name'] != null) item['customer_name'],
          if (item['status'] != null) item['status'],
          if (item['order_ids'] is List) '${(item['order_ids'] as List).length} orders',
        ].where((e) => e != null && e.toString().isNotEmpty).join(' · '),
      ),
      trailing: Text(
        '৳$amount',
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
      onTap: () => context.go('/cash'),
    );
  }
}
