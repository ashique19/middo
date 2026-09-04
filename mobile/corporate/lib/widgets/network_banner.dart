import 'package:flutter/material.dart';

import '../data/network_status.dart';
import '../theme/middo_colors.dart';

class NetworkBanner extends StatefulWidget {
  const NetworkBanner({super.key});

  @override
  State<NetworkBanner> createState() => _NetworkBannerState();
}

class _NetworkBannerState extends State<NetworkBanner> {
  late bool _online = NetworkStatus.instance.isOnline;

  @override
  void initState() {
    super.initState();
    NetworkStatus.instance.onChange.listen((online) {
      if (mounted) setState(() => _online = online);
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_online) return const SizedBox.shrink();
    return Material(
      color: MiddoColors.orangeDeep,
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
          child: Row(
            children: const [
              Icon(Icons.wifi_off_rounded, color: Colors.white, size: 18),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'You appear to be offline. Some actions may not work until you reconnect.',
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
    );
  }
}
