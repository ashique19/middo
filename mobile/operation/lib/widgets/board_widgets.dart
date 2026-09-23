import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../theme/middo_colors.dart';

class BoardTabSpec {
  const BoardTabSpec(this.label, this.key);
  final String label;
  final String key;
}

class BoardCountChip extends StatelessWidget {
  const BoardCountChip(this.count, {super.key});
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

class BoardPaymentBadge extends StatelessWidget {
  const BoardPaymentBadge(this.keyName, this.label, {super.key});
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

class BoardSimpleList extends StatelessWidget {
  const BoardSimpleList({
    super.key,
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
      return Center(
        child: Text(emptyLabel, style: const TextStyle(color: MiddoColors.muted)),
      );
    }

    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
      itemCount: items.length,
      separatorBuilder: (_, __) => const Divider(height: 1),
      itemBuilder: (_, i) => itemBuilder(items[i]),
    );
  }
}

class BoardTabbedView extends StatefulWidget {
  const BoardTabbedView({
    super.key,
    required this.tabs,
    required this.data,
    required this.itemBuilder,
    this.emptyLabel = 'Nothing here',
  });

  final List<BoardTabSpec> tabs;
  final Map<String, dynamic> data;
  final Widget Function(Map<String, dynamic> item) itemBuilder;
  final String emptyLabel;

  @override
  State<BoardTabbedView> createState() => _BoardTabbedViewState();
}

class _BoardTabbedViewState extends State<BoardTabbedView>
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
        Material(
          color: Theme.of(context).colorScheme.surface,
          child: TabBar(
            controller: _controller,
            isScrollable: true,
            tabAlignment: TabAlignment.start,
            labelPadding: const EdgeInsets.symmetric(horizontal: 12),
            tabs: [
              for (final tab in widget.tabs)
                Tab(
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(tab.label),
                      const SizedBox(width: 6),
                      BoardCountChip(
                        (_bucket(tab.key)['count'] as num?)?.toInt() ?? 0,
                      ),
                    ],
                  ),
                ),
            ],
          ),
        ),
        Expanded(
          child: TabBarView(
            controller: _controller,
            children: [
              for (final tab in widget.tabs)
                BoardSimpleList(
                  items: (_bucket(tab.key)['items'] as List?) ?? const [],
                  emptyLabel: widget.emptyLabel,
                  itemBuilder: (item) => widget.itemBuilder(
                    (item as Map).cast<String, dynamic>(),
                  ),
                ),
            ],
          ),
        ),
      ],
    );
  }
}

class BoardOrderTile extends StatelessWidget {
  const BoardOrderTile({
    super.key,
    required this.item,
    this.showPaymentBadge = false,
  });

  final Map<String, dynamic> item;
  final bool showPaymentBadge;

  void _openParty(BuildContext context, dynamic id) {
    if (id is int && id > 0) {
      context.push('/parties/$id');
    }
  }

  void _openGroup(BuildContext context, dynamic id) {
    if (id is int && id > 0) {
      context.push('/order-groups/$id');
    }
  }

  Widget _link(
    BuildContext context,
    String label,
    dynamic id, {
    IconData? icon,
    String? photoUrl,
  }) {
    final hasId = id is int && id > 0;
    final photo = photoUrl?.trim();
    return InkWell(
      onTap: hasId ? () => _openParty(context, id) : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (photo != null && photo.isNotEmpty) ...[
              CircleAvatar(radius: 8, backgroundImage: NetworkImage(photo)),
              const SizedBox(width: 4),
            ] else if (icon != null) ...[
              Icon(icon, size: 14, color: hasId ? MiddoColors.orange : MiddoColors.muted),
              const SizedBox(width: 4),
            ],
            Flexible(
              child: Text(
                label,
                style: TextStyle(
                  color: hasId ? MiddoColors.orange : MiddoColors.muted,
                  fontWeight: hasId ? FontWeight.w600 : FontWeight.w400,
                  decoration: hasId ? TextDecoration.underline : TextDecoration.none,
                  decorationColor: MiddoColors.orange.withValues(alpha: 0.5),
                  fontSize: 12,
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final id = item['id'];
    final groupId = item['group_id'];
    final groupName = item['group_name']?.toString();
    final customer = item['customer_name']?.toString() ?? '—';
    final rider = item['rider_name']?.toString();
    final kitchen = item['kitchen_name']?.toString();

    return InkWell(
      onTap: id is int ? () => context.push('/orders/$id') : null,
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '#$id · ${item['menu_name'] ?? 'Order'} · qty ${item['quantity'] ?? 1}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w700),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    [
                      item['area_name'],
                      item['order_status'],
                      if (item['is_package'] == true) 'package',
                    ].where((e) => e != null && e.toString().isNotEmpty).join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: MiddoColors.muted, fontSize: 12),
                  ),
                  const SizedBox(height: 6),
                  if (groupName != null && groupName.isNotEmpty)
                    InkWell(
                      onTap: () => _openGroup(context, groupId),
                      child: Padding(
                        padding: const EdgeInsets.only(bottom: 4),
                        child: Text(
                          'Group: $groupName',
                          style: TextStyle(
                            color: groupId is int ? MiddoColors.orange : MiddoColors.muted,
                            fontWeight: FontWeight.w700,
                            fontSize: 12,
                            decoration: groupId is int ? TextDecoration.underline : null,
                          ),
                        ),
                      ),
                    ),
                  Wrap(
                    spacing: 12,
                    runSpacing: 2,
                    children: [
                      _link(context, 'Customer: $customer', item['customer_id'], icon: Icons.business),
                      if (rider != null && rider.isNotEmpty)
                        _link(context, 'Rider: $rider', item['rider_id'], icon: Icons.delivery_dining),
                      if (kitchen != null && kitchen.isNotEmpty)
                        _link(
                          context,
                          'Kitchen: $kitchen',
                          item['kitchen_id'],
                          icon: Icons.soup_kitchen,
                          photoUrl: item['kitchen_profile_photo_url']?.toString(),
                        ),
                    ],
                  ),
                ],
              ),
            ),
            if (showPaymentBadge)
              BoardPaymentBadge(
                item['payment_badge']?.toString() ?? 'unpaid',
                item['payment_badge_label']?.toString() ?? 'Unpaid',
              ),
          ],
        ),
      ),
    );
  }
}

class BoardPackageOrOrderTile extends StatelessWidget {
  const BoardPackageOrOrderTile({super.key, required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    if (item.containsKey('order_status')) {
      return BoardOrderTile(item: item);
    }

    return ListTile(
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

class BoardCashTile extends StatelessWidget {
  const BoardCashTile({super.key, required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final amount = item['cash_due'] ?? item['amount'] ?? 0;
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(
        item['rider_name']?.toString() ??
            (item['id'] != null ? 'Handover #${item['id']}' : 'Cash'),
      ),
      subtitle: Text(
        [
          if (item['customer_name'] != null) item['customer_name'],
          if (item['status'] != null) item['status'],
          if (item['order_ids'] is List)
            '${(item['order_ids'] as List).length} orders',
        ].where((e) => e != null && e.toString().isNotEmpty).join(' · '),
      ),
      trailing: Text(
        '৳$amount',
        style: const TextStyle(fontWeight: FontWeight.w800),
      ),
    );
  }
}

class BoardBoxRequestTile extends StatelessWidget {
  const BoardBoxRequestTile({super.key, required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    final photo = item['kitchen_profile_photo_url']?.toString();
    return ListTile(
      contentPadding: EdgeInsets.zero,
      leading: (photo != null && photo.isNotEmpty)
          ? CircleAvatar(backgroundImage: NetworkImage(photo))
          : const Icon(Icons.soup_kitchen),
      title: Text(item['kitchen_name']?.toString() ?? 'Kitchen'),
      subtitle: Text(
        'Qty ${(item['quantity'] ?? item['allocated_qty'] ?? '—')} · ${item['status'] ?? ''}',
      ),
      onTap: () => context.go('/boxes'),
    );
  }
}

class BoardComplaintTile extends StatelessWidget {
  const BoardComplaintTile({super.key, required this.item});
  final Map<String, dynamic> item;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(item['category']?.toString() ?? 'Complaint #${item['id']}'),
      subtitle: Text(
        item['message']?.toString() ?? '',
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      onTap: () {
        final id = item['id'];
        if (id is int) context.push('/complaints/$id');
      },
    );
  }
}
