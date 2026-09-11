import 'dart:async';

import 'package:flutter/material.dart';

import '../data/network_status.dart';
import '../data/offline_mutation_queue.dart';
import '../theme/middo_colors.dart';

class NetworkBanner extends StatefulWidget {
  const NetworkBanner({super.key});

  @override
  State<NetworkBanner> createState() => _NetworkBannerState();
}

class _NetworkBannerState extends State<NetworkBanner> {
  late bool _online = NetworkStatus.instance.isOnline;
  late int _pending = OfflineMutationQueue.instance.pendingCount;
  late int _failed = OfflineMutationQueue.instance.failedCount;
  StreamSubscription<bool>? _netSub;
  StreamSubscription<int>? _queueSub;

  @override
  void initState() {
    super.initState();
    _netSub = NetworkStatus.instance.onChange.listen((online) {
      if (mounted) setState(() => _online = online);
    });
    _queueSub = OfflineMutationQueue.instance.onChange.listen((_) {
      if (mounted) {
        setState(() {
          _pending = OfflineMutationQueue.instance.pendingCount;
          _failed = OfflineMutationQueue.instance.failedCount;
        });
      }
    });
  }

  @override
  void dispose() {
    _netSub?.cancel();
    _queueSub?.cancel();
    super.dispose();
  }

  Future<void> _openFailedSheet() async {
    final items = OfflineMutationQueue.instance.failedItems;
    if (!mounted) return;
    await showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Sync conflicts',
                  style: TextStyle(fontWeight: FontWeight.w900, fontSize: 18),
                ),
                const SizedBox(height: 4),
                const Text(
                  'These actions failed on the server. Retry after fixing the issue, or discard.',
                  style: TextStyle(fontSize: 13, height: 1.35),
                ),
                const SizedBox(height: 12),
                if (items.isEmpty)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 24),
                    child: Text('No failed actions.'),
                  )
                else
                  Flexible(
                    child: ListView.separated(
                      shrinkWrap: true,
                      itemCount: items.length,
                      separatorBuilder: (_, __) => const SizedBox(height: 8),
                      itemBuilder: (_, i) {
                        final item = items[i];
                        final type = item['type']?.toString() ?? 'action';
                        final err = item['error_message']?.toString() ??
                            'Request rejected';
                        final code = item['error_status'];
                        return Material(
                          color: MiddoColors.cream,
                          borderRadius: BorderRadius.circular(12),
                          child: Padding(
                            padding: const EdgeInsets.all(12),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  type.replaceAll('_', ' '),
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w800,
                                  ),
                                ),
                                const SizedBox(height: 4),
                                Text(
                                  code == null ? err : 'HTTP $code · $err',
                                  style: const TextStyle(
                                    fontSize: 12,
                                    height: 1.35,
                                    color: MiddoColors.inkSoft,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Row(
                                  children: [
                                    TextButton(
                                      onPressed: () async {
                                        await OfflineMutationQueue.instance
                                            .retryFailed(
                                          item['id'].toString(),
                                        );
                                        if (ctx.mounted) Navigator.pop(ctx);
                                      },
                                      child: const Text('Retry'),
                                    ),
                                    TextButton(
                                      onPressed: () async {
                                        await OfflineMutationQueue.instance
                                            .discardFailed(
                                          item['id'].toString(),
                                        );
                                        if (ctx.mounted) Navigator.pop(ctx);
                                      },
                                      child: const Text('Discard'),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        );
                      },
                    ),
                  ),
                if (items.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: TextButton(
                      onPressed: () async {
                        await OfflineMutationQueue.instance.discardAllFailed();
                        if (ctx.mounted) Navigator.pop(ctx);
                      },
                      child: const Text('Discard all'),
                    ),
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
    if (mounted) {
      setState(() {
        _pending = OfflineMutationQueue.instance.pendingCount;
        _failed = OfflineMutationQueue.instance.failedCount;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (!_online)
          Material(
            color: MiddoColors.orangeDeep,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
              child: Row(
                children: const [
                  Icon(Icons.wifi_off_rounded, color: Colors.white, size: 18),
                  SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      'You appear to be offline. Actions will queue until you reconnect.',
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                        height: 1.3,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        if (_pending > 0)
          Material(
            color: MiddoColors.forest,
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
              child: Row(
                children: [
                  const Icon(Icons.sync, color: Colors.white, size: 16),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      '$_pending action${_pending == 1 ? '' : 's'} pending sync',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w700,
                        fontSize: 12,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        if (_failed > 0)
          Material(
            color: MiddoColors.orangeDeep,
            child: InkWell(
              onTap: _openFailedSheet,
              child: Padding(
                padding:
                    const EdgeInsets.symmetric(horizontal: 14, vertical: 6),
                child: Row(
                  children: [
                    const Icon(Icons.error_outline,
                        color: Colors.white, size: 16),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        '$_failed sync conflict${_failed == 1 ? '' : 's'} — tap to review',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                    ),
                    const Icon(Icons.chevron_right,
                        color: Colors.white, size: 18),
                  ],
                ),
              ),
            ),
          ),
      ],
    );
  }
}
