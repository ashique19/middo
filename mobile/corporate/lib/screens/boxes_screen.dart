import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

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
              return const MiddoPageLoader(message: 'Loading boxes…');
            }
            if (snapshot.hasError) {
              return ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: [
                  Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text(snapshot.error.toString()),
                  ),
                ],
              );
            }

            final data = snapshot.data!;
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
                if (data.boxes.isEmpty)
                  Container(
                    padding: const EdgeInsets.all(20),
                    decoration: BoxDecoration(
                      color: MiddoColors.white,
                      borderRadius: BorderRadius.circular(16),
                      border: Border.all(color: MiddoColors.creamBorder),
                    ),
                    child: const Text(
                      'No Middo Boxes currently at your office.',
                      style: TextStyle(fontWeight: FontWeight.w600),
                    ),
                  )
                else
                  ...data.boxes.map(
                    (box) => Container(
                      margin: const EdgeInsets.only(bottom: 10),
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: MiddoColors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: MiddoColors.creamBorder),
                      ),
                      child: Row(
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
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 8, vertical: 4),
                              decoration: BoxDecoration(
                                color: Colors.green.shade50,
                                borderRadius: BorderRadius.circular(8),
                                border: Border.all(
                                    color: Colors.green.shade200),
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
                              child: _markingReady.contains(box.id)
                                  ? const SizedBox(
                                      width: 20,
                                      height: 20,
                                      child: CircularProgressIndicator(
                                          strokeWidth: 2),
                                    )
                                  : OutlinedButton(
                                      onPressed: () =>
                                          _markReady(box.id),
                                      style: OutlinedButton.styleFrom(
                                        padding: const EdgeInsets.symmetric(
                                            horizontal: 10),
                                        side: BorderSide(
                                            color: MiddoColors.orange),
                                        shape: RoundedRectangleBorder(
                                          borderRadius:
                                              BorderRadius.circular(8),
                                        ),
                                      ),
                                      child: Text(
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
                        content: Text('Contact Middo from an order\'s Support tab.'),
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
