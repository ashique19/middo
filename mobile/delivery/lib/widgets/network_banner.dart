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
  StreamSubscription<bool>? _netSub;
  StreamSubscription<int>? _queueSub;

  @override
  void initState() {
    super.initState();
    _netSub = NetworkStatus.instance.onChange.listen((online) {
      if (mounted) setState(() => _online = online);
    });
    _queueSub = OfflineMutationQueue.instance.onChange.listen((count) {
      if (mounted) setState(() => _pending = count);
    });
  }

  @override
  void dispose() {
    _netSub?.cancel();
    _queueSub?.cancel();
    super.dispose();
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
      ],
    );
  }
}
