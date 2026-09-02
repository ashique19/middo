import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_mobile_header.dart';
import '../widgets/kitchen_ui.dart';

class ComplaintsScreen extends StatefulWidget {
  const ComplaintsScreen({super.key});

  @override
  State<ComplaintsScreen> createState() => _ComplaintsScreenState();
}

class _ComplaintsScreenState extends State<ComplaintsScreen> {
  Future<List<dynamic>>? _list;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _list ??= AppScope.of(context).complaints();
  }

  Future<void> _reload() async {
    setState(() {
      _list = AppScope.of(context).complaints();
    });
    await _list;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const KitchenMobileHeader(title: 'Complaints', showBack: true),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<List<dynamic>>(
          future: _list,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return KitchenError(snap.error!, onRetry: _reload);
            }
            final items = snap.data ?? const [];
            if (items.isEmpty) {
              return ListView(
                children: const [KitchenEmpty('No complaints.')],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final c = items[i] as Map;
                return KitchenPanel(
                  onTap: () => context.push('/complaints/${c['id']}'),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              c['category_label']?.toString() ??
                                  c['category']?.toString() ??
                                  'Complaint',
                              style:
                                  const TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ),
                          KitchenStatusChip(c['status']?.toString() ?? ''),
                        ],
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Order #${c['order_id'] ?? '—'} · ${c['menu_name'] ?? ''}',
                        style: const TextStyle(color: MiddoColors.inkSoft),
                      ),
                      const SizedBox(height: 6),
                      Text(c['message']?.toString() ?? ''),
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

class ComplaintDetailScreen extends StatefulWidget {
  const ComplaintDetailScreen({super.key, required this.complaintId});

  final int complaintId;

  @override
  State<ComplaintDetailScreen> createState() => _ComplaintDetailScreenState();
}

class _ComplaintDetailScreenState extends State<ComplaintDetailScreen> {
  Future<Map<String, dynamic>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).showComplaint(widget.complaintId);
  }

  Future<void> _reload() async {
    setState(() {
      _future = AppScope.of(context).showComplaint(widget.complaintId);
    });
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: KitchenMobileHeader(
        title: 'Complaint #${widget.complaintId}',
        showBack: true,
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _future,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return KitchenError(snap.error!, onRetry: _reload);
            }
            final c =
                (snap.data?['complaint'] as Map?)?.cast<String, dynamic>() ??
                    const {};
            final thread = (c['thread'] as List?) ?? const [];
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  c['category_label']?.toString() ?? 'Complaint',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    fontSize: 18,
                  ),
                ),
                Text(
                  'Order #${c['order_id'] ?? '—'} · ${c['menu_name'] ?? ''} · ${c['status'] ?? ''}',
                  style: const TextStyle(color: MiddoColors.inkSoft),
                ),
                const SizedBox(height: 16),
                for (final raw in thread)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: KitchenPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            (raw as Map)['created_by_name']?.toString() ??
                                'Message',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          const SizedBox(height: 4),
                          Text(raw['message']?.toString() ?? ''),
                        ],
                      ),
                    ),
                  ),
              ],
            );
          },
        ),
      ),
    );
  }
}
