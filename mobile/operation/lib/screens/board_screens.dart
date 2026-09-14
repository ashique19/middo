import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/board_widgets.dart';
import '../widgets/pickers.dart';

class BoardDetailScaffold extends StatefulWidget {
  const BoardDetailScaffold({
    super.key,
    required this.title,
    required this.sectionKey,
    required this.bodyBuilder,
    this.subtitle,
    this.showDatePicker = true,
    this.initialDate,
    this.actionsBuilder,
  });

  final String title;
  final String? subtitle;
  final String sectionKey;
  final bool showDatePicker;
  final String? initialDate;
  final Widget Function(BuildContext context, Map<String, dynamic> section)
      bodyBuilder;
  /// Extra app-bar actions. Receives selected date key + reload callback.
  final List<Widget> Function(
    BuildContext context,
    String dateKey,
    Future<void> Function() reload,
  )? actionsBuilder;

  @override
  State<BoardDetailScaffold> createState() => _BoardDetailScaffoldState();
}

class _BoardDetailScaffoldState extends State<BoardDetailScaffold> {
  bool _loading = true;
  String? _error;
  Map<String, dynamic>? _boards;
  late DateTime _date;

  @override
  void initState() {
    super.initState();
    _date = widget.initialDate != null
        ? (DateTime.tryParse(widget.initialDate!) ?? DateTime.now())
        : DateTime.now();
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
    final section =
        ((_boards?[widget.sectionKey] as Map?)?.cast<String, dynamic>()) ??
            const <String, dynamic>{};

    return Scaffold(
      appBar: AppBar(
        title: Text(widget.title),
        actions: [
          if (widget.showDatePicker)
            TextButton.icon(
              onPressed: _pickDate,
              icon: const Icon(Icons.calendar_today, size: 18),
              label: Text(DateFormat('EEE d MMM').format(_date)),
            ),
          IconButton(
            tooltip: 'Refresh',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
          if (widget.actionsBuilder != null)
            ...widget.actionsBuilder!(context, _dateKey, _load),

        ],
      ),
      body: _loading && _boards == null
          ? const Center(child: CircularProgressIndicator())
          : _error != null && _boards == null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error!, textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        FilledButton(
                          onPressed: _load,
                          child: const Text('Retry'),
                        ),
                      ],
                    ),
                  ),
                )
              : Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (widget.subtitle != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                        child: Text(
                          widget.subtitle!,
                          style: const TextStyle(
                            color: MiddoColors.muted,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    if (_error != null)
                      Padding(
                        padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                        child: Text(
                          _error!,
                          style: const TextStyle(color: Colors.red),
                        ),
                      ),
                    Expanded(child: widget.bodyBuilder(context, section)),
                  ],
                ),
    );
  }
}

class PackagesBoardScreen extends StatelessWidget {
  const PackagesBoardScreen({super.key, this.date});
  final String? date;

  @override
  Widget build(BuildContext context) {
    return BoardDetailScaffold(
      title: 'Packages for today',
      sectionKey: 'packages',
      initialDate: date,
      bodyBuilder: (context, section) => BoardTabbedView(
        tabs: const [
          BoardTabSpec('Unassigned meals', 'unassigned_meals'),
          BoardTabSpec('Package orders', 'orders'),
        ],
        data: section,
        itemBuilder: (item) => BoardPackageOrOrderTile(item: item),
      ),
    );
  }
}

class OrdersBoardScreen extends StatefulWidget {
  const OrdersBoardScreen({super.key, this.date});
  final String? date;

  @override
  State<OrdersBoardScreen> createState() => _OrdersBoardScreenState();
}

class _OrdersBoardScreenState extends State<OrdersBoardScreen> {
  bool _byGroup = false;
  bool _grouping = false;

  Future<void> _autoGroup(String dateKey, Future<void> Function() reload) async {
    setState(() => _grouping = true);
    try {
      final res = await AppScope.of(context).autoGroupOrders(date: dateKey);
      if (!mounted) return;
      showSnack(context, res['message']?.toString() ?? 'Auto-group done.');
      await reload();
    } on ApiException catch (e) {
      if (!mounted) return;
      showSnack(context, e.message);
    } finally {
      if (mounted) setState(() => _grouping = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return BoardDetailScaffold(
      title: 'Orders for today',
      sectionKey: 'orders',
      initialDate: widget.date,
      actionsBuilder: (context, dateKey, reload) => [
        IconButton(
          tooltip: _byGroup ? 'Show flat list' : 'Show by group',
          onPressed: () => setState(() => _byGroup = !_byGroup),
          icon: Icon(_byGroup ? Icons.view_list : Icons.view_agenda),
        ),
        IconButton(
          tooltip: 'Auto-group this day',
          onPressed: _grouping ? null : () => _autoGroup(dateKey, reload),
          icon: _grouping
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Icon(Icons.auto_awesome),
        ),
      ],
      bodyBuilder: (context, section) {
        if (_byGroup) {
          final buckets = (section['by_group'] as List?) ?? const [];
          if (buckets.isEmpty) {
            return const Center(
              child: Text('No orders for this day', style: TextStyle(color: MiddoColors.muted)),
            );
          }
          return ListView.builder(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            itemCount: buckets.length,
            itemBuilder: (context, index) {
              final bucket = (buckets[index] as Map).cast<String, dynamic>();
              final items = (bucket['items'] as List?) ?? const [];
              final groupId = bucket['group_id'];
              final groupName = bucket['group_name']?.toString() ?? 'Ungrouped';
              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: ExpansionTile(
                  initiallyExpanded: true,
                  title: Text(
                    groupName,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text(
                    [
                      if (bucket['kitchen_name'] != null) 'Kitchen: ${bucket['kitchen_name']}',
                      '${bucket['count'] ?? items.length} order(s)',
                      'qty ${bucket['qty'] ?? '—'}',
                    ].join(' · '),
                  ),
                  trailing: groupId is int
                      ? IconButton(
                          tooltip: 'Group details',
                          icon: const Icon(Icons.open_in_new, size: 18),
                          onPressed: () => context.push('/order-groups/$groupId'),
                        )
                      : null,
                  children: [
                    for (final raw in items)
                      Padding(
                        padding: const EdgeInsets.symmetric(horizontal: 12),
                        child: BoardOrderTile(
                          item: (raw as Map).cast<String, dynamic>(),
                        ),
                      ),
                  ],
                ),
              );
            },
          );
        }

        return BoardTabbedView(
          tabs: const [
            BoardTabSpec('All', 'all'),
            BoardTabSpec('From packages', 'package'),
            BoardTabSpec('Individual', 'individual'),
          ],
          data: section,
          itemBuilder: (item) => BoardOrderTile(item: item),
        );
      },
    );
  }
}

class GroupingBoardScreen extends StatelessWidget {
  const GroupingBoardScreen({super.key, this.date});
  final String? date;

  @override
  Widget build(BuildContext context) {
    return BoardDetailScaffold(
      title: 'Grouping for today',
      subtitle: 'Paid · green · Unpaid · yellow · Cancelled · red',
      sectionKey: 'grouping',
      initialDate: date,
      bodyBuilder: (context, section) => BoardTabbedView(
        tabs: const [
          BoardTabSpec('Ungrouped', 'ungrouped'),
          BoardTabSpec('Grouped', 'grouped_pending'),
          BoardTabSpec('Accepted', 'accepted'),
          BoardTabSpec('Packed', 'packed'),
          BoardTabSpec('Picked', 'picked'),
          BoardTabSpec('Delivered', 'delivered'),
          BoardTabSpec('Failed', 'failed'),
        ],
        data: section,
        itemBuilder: (item) =>
            BoardOrderTile(item: item, showPaymentBadge: true),
      ),
    );
  }
}

class CashCollectionBoardScreen extends StatelessWidget {
  const CashCollectionBoardScreen({super.key, this.date});
  final String? date;

  @override
  Widget build(BuildContext context) {
    return BoardDetailScaffold(
      title: 'Rider cash collection',
      sectionKey: 'cash_collection',
      initialDate: date,
      bodyBuilder: (context, section) => BoardTabbedView(
        tabs: const [
          BoardTabSpec('At rider', 'at_rider'),
          BoardTabSpec('Kitchen', 'kitchen'),
          BoardTabSpec('Middo', 'middo'),
        ],
        data: section,
        itemBuilder: (item) => BoardCashTile(item: item),
      ),
    );
  }
}

class BoxRequestsBoardScreen extends StatelessWidget {
  const BoxRequestsBoardScreen({super.key, this.date});
  final String? date;

  @override
  Widget build(BuildContext context) {
    return BoardDetailScaffold(
      title: 'Box requests',
      sectionKey: 'box_requests',
      initialDate: date,
      bodyBuilder: (context, section) {
        final items = (section['items'] as List?) ?? const [];
        return BoardSimpleList(
          items: items,
          emptyLabel: 'No open box requests',
          itemBuilder: (item) => BoardBoxRequestTile(
            item: (item as Map).cast<String, dynamic>(),
          ),
        );
      },
    );
  }
}

class ComplaintsBoardScreen extends StatelessWidget {
  const ComplaintsBoardScreen({super.key, this.date});
  final String? date;

  @override
  Widget build(BuildContext context) {
    return BoardDetailScaffold(
      title: 'Complaints',
      sectionKey: 'complaints',
      showDatePicker: false,
      initialDate: date,
      bodyBuilder: (context, section) {
        final items = (section['items'] as List?) ?? const [];
        return BoardSimpleList(
          items: items,
          emptyLabel: 'No open complaints',
          itemBuilder: (item) => BoardComplaintTile(
            item: (item as Map).cast<String, dynamic>(),
          ),
        );
      },
    );
  }
}
