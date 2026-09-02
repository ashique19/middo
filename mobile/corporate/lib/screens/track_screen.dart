import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class TrackScreen extends StatefulWidget {
  const TrackScreen({super.key, required this.orderId});

  final String orderId;

  @override
  State<TrackScreen> createState() => _TrackScreenState();
}

class _TrackScreenState extends State<TrackScreen> {
  Future<({CorporateOrder order, List<TrackEvent> events})>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).track(widget.orderId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/schedule');
            }
          },
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'ORDER #${widget.orderId}',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: MiddoColors.muted,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.5,
                  ),
            ),
            const Text('Track Order'),
          ],
        ),
        actions: [
          IconButton(
            tooltip: 'Home',
            onPressed: () => context.go('/home'),
            icon: const Icon(Icons.home_outlined),
          ),
        ],
      ),
      body: FutureBuilder(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const MiddoPageLoader(message: 'Loading tracking…');
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }
          final data = snapshot.data!;
          final order = data.order;
          final events = data.events;

          return ListView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 24),
            children: [
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: MiddoColors.white,
                  borderRadius: BorderRadius.circular(16),
                  border: Border.all(color: MiddoColors.creamBorder),
                ),
                child: Column(
                  children: [
                    MetaRow(label: 'Meal', value: order.menuItem.name),
                    MetaRow(
                      label: 'Status',
                      value: order.statusLabel,
                      valueColor: MiddoColors.forest,
                    ),
                    MetaRow(
                      label: 'Total',
                      value: bdt.format(order.totalAmount),
                      valueColor: MiddoColors.orange,
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 18),
              if (events.isEmpty)
                const Text(
                  'No activity recorded for this order yet.',
                  style: TextStyle(
                    color: MiddoColors.inkSoft,
                    fontWeight: FontWeight.w600,
                  ),
                )
              else
                ...List.generate(events.length, (index) {
                  return _TimelineTile(
                    event: events[index],
                    isLast: index == events.length - 1,
                  );
                }),
              const SizedBox(height: 8),
              OutlinedButton(
                onPressed: () => context.push('/support/${widget.orderId}'),
                child: const Text('Report an issue'),
              ),
              const SizedBox(height: 8),
              TextButton(
                onPressed: () => context.go('/schedule'),
                child: const Text('Back to schedule'),
              ),
              TextButton(
                onPressed: () => context.go('/home'),
                child: const Text('Go home'),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _TimelineTile extends StatelessWidget {
  const _TimelineTile({required this.event, required this.isLast});

  final TrackEvent event;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 24,
            child: Column(
              children: [
                Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: event.isCurrent ? MiddoColors.forest : Colors.white,
                    border: Border.all(
                      color: event.isCurrent
                          ? MiddoColors.forest
                          : const Color(0xFFDDD3BE),
                      width: 2,
                    ),
                  ),
                  child: event.isCurrent
                      ? Center(
                          child: Container(
                            width: 8,
                            height: 8,
                            decoration: const BoxDecoration(
                              color: Colors.white,
                              shape: BoxShape.circle,
                            ),
                          ),
                        )
                      : null,
                ),
                if (!isLast)
                  Expanded(
                    child: Container(
                      width: 1,
                      color: MiddoColors.creamBorder,
                      margin: const EdgeInsets.symmetric(vertical: 2),
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    event.title,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    event.description,
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: MiddoColors.inkSoft,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    DateFormat('MMM d · h:mm a').format(event.at),
                    style: const TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: MiddoColors.muted,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
