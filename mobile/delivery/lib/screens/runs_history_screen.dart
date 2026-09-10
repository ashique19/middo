import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_mobile_header.dart';
import '../widgets/delivery_ui.dart';

class RunsHistoryScreen extends StatefulWidget {
  const RunsHistoryScreen({super.key, this.initialPeriod = 'this_month'});

  final String initialPeriod;

  @override
  State<RunsHistoryScreen> createState() => _RunsHistoryScreenState();
}

class _RunsHistoryScreenState extends State<RunsHistoryScreen> {
  late String _period = widget.initialPeriod;
  Future<Map<String, dynamic>>? _history;

  static const _periods = {
    'this_month': 'This month',
    'last_month': 'Last month',
    'last_3_months': 'Last 3 months',
  };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _history ??= AppScope.of(context).runsHistory(period: _period);
  }

  Future<void> _load(String period) async {
    setState(() {
      _period = period;
      _history = AppScope.of(context).runsHistory(period: period);
    });
    await _history;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const DeliveryMobileHeader(
        title: 'Runs history',
        showBack: true,
      ),
      body: Column(
        children: [
          SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
            child: Row(
              children: _periods.entries.map((e) {
                final selected = e.key == _period;
                return Padding(
                  padding: const EdgeInsets.only(right: 8),
                  child: ChoiceChip(
                    label: Text(e.value),
                    selected: selected,
                    onSelected: (_) => _load(e.key),
                    selectedColor: MiddoColors.forest,
                    labelStyle: TextStyle(
                      color: selected ? Colors.white : MiddoColors.inkSoft,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                );
              }).toList(),
            ),
          ),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _load(_period),
              child: FutureBuilder<Map<String, dynamic>>(
                future: _history,
                builder: (context, snap) {
                  if (snap.connectionState != ConnectionState.done) {
                    return const Center(child: CircularProgressIndicator());
                  }
                  if (snap.hasError) {
                    return DeliveryError(
                      snap.error!,
                      onRetry: () => _load(_period),
                    );
                  }
                  final runs =
                      (snap.data?['runs'] as List?) ?? const <dynamic>[];
                  if (runs.isEmpty) {
                    return ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      children: const [
                        SizedBox(height: 80),
                        DeliveryEmpty('No runs in this period.'),
                      ],
                    );
                  }
                  return ListView.separated(
                    padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                    itemCount: runs.length,
                    separatorBuilder: (_, __) => const SizedBox(height: 8),
                    itemBuilder: (context, i) {
                      final run =
                          (runs[i] as Map).cast<String, dynamic>();
                      return DeliveryPanel(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              run['label']?.toString() ??
                                  'Run #${run['id']}',
                              style: const TextStyle(
                                fontWeight: FontWeight.w800,
                              ),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              '${run['status'] ?? ''} · ${run['delivered_at'] ?? ''}',
                              style: const TextStyle(
                                color: MiddoColors.inkSoft,
                                fontWeight: FontWeight.w600,
                                fontSize: 13,
                              ),
                            ),
                            if (run['menu_name'] != null) ...[
                              const SizedBox(height: 2),
                              Text(
                                '${run['menu_name']} · qty ${run['quantity'] ?? ''}',
                                style: const TextStyle(
                                  color: MiddoColors.muted,
                                  fontSize: 12,
                                ),
                              ),
                            ],
                          ],
                        ),
                      );
                    },
                  );
                },
              ),
            ),
          ),
        ],
      ),
    );
  }
}
