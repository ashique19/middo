import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/board_widgets.dart';

class BoardDetailScaffold extends StatefulWidget {
  const BoardDetailScaffold({
    super.key,
    required this.title,
    required this.sectionKey,
    required this.bodyBuilder,
    this.subtitle,
    this.showDatePicker = true,
    this.initialDate,
  });

  final String title;
  final String? subtitle;
  final String sectionKey;
  final bool showDatePicker;
  final String? initialDate;
  final Widget Function(BuildContext context, Map<String, dynamic> section)
      bodyBuilder;

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

class OrdersBoardScreen extends StatelessWidget {
  const OrdersBoardScreen({super.key, this.date});
  final String? date;

  @override
  Widget build(BuildContext context) {
    return BoardDetailScaffold(
      title: 'Orders for today',
      sectionKey: 'orders',
      initialDate: date,
      bodyBuilder: (context, section) => BoardTabbedView(
        tabs: const [
          BoardTabSpec('All', 'all'),
          BoardTabSpec('From packages', 'package'),
          BoardTabSpec('Individual', 'individual'),
        ],
        data: section,
        itemBuilder: (item) => BoardOrderTile(item: item),
      ),
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
