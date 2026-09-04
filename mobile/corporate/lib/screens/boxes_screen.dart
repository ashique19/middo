import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class BoxesScreen extends StatefulWidget {
  const BoxesScreen({super.key});

  @override
  State<BoxesScreen> createState() => _BoxesScreenState();
}

class _BoxesScreenState extends State<BoxesScreen> {
  late Future<BoxesCustodyData> _future;
  final Set<int> _markingReady = {};

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future = AppScope.of(context).boxes();
  }

  Future<void> _reload() async {
    final next = AppScope.of(context).boxes();
    setState(() => _future = next);
    await next;
  }

  Future<void> _markReady(int boxId) async {
    setState(() => _markingReady.add(boxId));
    try {
      await AppScope.of(context).markBoxReadyForPickup(boxId);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Box marked as ready for pickup.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
        await _reload();
      }
    } catch (_) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Could not update box status. Please try again.'),
            behavior: SnackBarBehavior.floating,
          ),
        );
      }
    } finally {
      if (mounted) setState(() => _markingReady.remove(boxId));
    }
  }

  void _browseMenu() {
    context.go('/menu');
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MiddoColors.cream,
      appBar: AppBar(
        title: const Text('Boxes with you'),
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<BoxesCustodyData>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const ListSkeleton(rows: 4);
            }
            if (snapshot.hasError) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  MiddoEmptyState(
                    icon: Icons.cloud_off_rounded,
                    title: 'Couldn’t load boxes',
                    message: snapshot.error.toString(),
                    actionLabel: 'Retry',
                    onAction: _reload,
                  ),
                ],
              );
            }

            final data = snapshot.data!;
            if (data.boxes.isEmpty) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  MiddoEmptyState(
                    icon: Icons.inventory_2_outlined,
                    title: 'No boxes with you',
                    message: data.message.isNotEmpty
                        ? data.message
                        : 'No Middo Boxes currently at your office. Order a lunch and empty boxes will show up here for pickup.',
                    actionLabel: 'Browse menu',
                    onAction: _browseMenu,
                  ),
                ],
              );
            }

            return ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
              children: [
                Text(
                  '${data.count} box(es) at your office',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 8),
                Text(
                  data.message,
                  style: const TextStyle(
                    color: MiddoColors.inkSoft,
                    fontWeight: FontWeight.w600,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 16),
                ...data.boxes.map(
                  (box) => _BoxCustodyCard(
                    box: box,
                    markingReady: _markingReady.contains(box.id),
                    onMarkReady: () => _markReady(box.id),
                  ),
                ),
                const SizedBox(height: 12),
                const Text(
                  'Tip: leave empty boxes near reception so riders can collect them quickly.',
                  style: TextStyle(
                    color: MiddoColors.inkSoft,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                    height: 1.4,
                  ),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: () {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text(
                          'Contact Middo from an order\'s Support tab.',
                        ),
                        behavior: SnackBarBehavior.floating,
                      ),
                    );
                  },
                  child: const Text(
                    'Need help with a box?',
                    style: TextStyle(
                      color: MiddoColors.orange,
                      fontWeight: FontWeight.w700,
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

class _BoxCustodyCard extends StatelessWidget {
  const _BoxCustodyCard({
    required this.box,
    required this.markingReady,
    required this.onMarkReady,
  });

  final MiddoBoxSummary box;
  final bool markingReady;
  final VoidCallback onMarkReady;

  @override
  Widget build(BuildContext context) {
    final readyAt = box.readyForPickupAt;
    final readyAtLabel = readyAt == null
        ? null
        : DateFormat('MMM d, h:mm a').format(readyAt.toLocal());

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: MiddoColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: MiddoColors.creamBorder),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.inventory_2_outlined,
                color: MiddoColors.orange,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      box.qrCodeId,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontFamily: 'monospace',
                      ),
                    ),
                    Text(
                      box.locationLabel,
                      style: const TextStyle(
                        color: MiddoColors.inkSoft,
                        fontSize: 12,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              if (box.readyForPickup)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: Colors.green.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.green.shade200),
                  ),
                  child: Text(
                    'Ready',
                    style: TextStyle(
                      color: Colors.green.shade700,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                )
              else
                SizedBox(
                  height: 32,
                  child: markingReady
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : OutlinedButton(
                          onPressed: onMarkReady,
                          style: OutlinedButton.styleFrom(
                            padding:
                                const EdgeInsets.symmetric(horizontal: 10),
                            side: const BorderSide(color: MiddoColors.orange),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(8),
                            ),
                          ),
                          child: const Text(
                            'Mark ready',
                            style: TextStyle(
                              color: MiddoColors.orange,
                              fontSize: 11,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                ),
            ],
          ),
          const SizedBox(height: 14),
          _CustodyTimeline(
            readyForPickup: box.readyForPickup,
            readyAtLabel: readyAtLabel,
          ),
        ],
      ),
    );
  }
}

class _CustodyTimeline extends StatelessWidget {
  const _CustodyTimeline({
    required this.readyForPickup,
    this.readyAtLabel,
  });

  final bool readyForPickup;
  final String? readyAtLabel;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        _CustodyStep(
          label: 'With you',
          complete: true,
          isLast: false,
        ),
        _CustodyStep(
          label: readyAtLabel != null
              ? 'Ready for pickup · $readyAtLabel'
              : 'Ready for pickup',
          complete: readyForPickup,
          isLast: false,
        ),
        const _CustodyStep(
          label: 'Picked up',
          complete: false,
          isLast: true,
          pending: true,
        ),
      ],
    );
  }
}

class _CustodyStep extends StatelessWidget {
  const _CustodyStep({
    required this.label,
    required this.complete,
    required this.isLast,
    this.pending = false,
  });

  final String label;
  final bool complete;
  final bool isLast;
  final bool pending;

  @override
  Widget build(BuildContext context) {
    final color = complete
        ? MiddoColors.forest
        : (pending ? MiddoColors.muted : MiddoColors.inkSoft);

    return IntrinsicHeight(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 22,
            child: Column(
              children: [
                Container(
                  width: 18,
                  height: 18,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: complete ? MiddoColors.forest : Colors.transparent,
                    border: Border.all(
                      color: complete ? MiddoColors.forest : MiddoColors.muted,
                      width: 2,
                    ),
                  ),
                  child: complete
                      ? const Icon(Icons.check, size: 11, color: Colors.white)
                      : null,
                ),
                if (!isLast)
                  Expanded(
                    child: Container(
                      width: 2,
                      margin: const EdgeInsets.symmetric(vertical: 2),
                      color: complete
                          ? MiddoColors.forest.withValues(alpha: 0.35)
                          : MiddoColors.creamBorder,
                    ),
                  ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(bottom: isLast ? 0 : 12),
              child: Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  color: color,
                  height: 1.35,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
