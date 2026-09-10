import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/middo_haptics.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_mobile_header.dart';
import '../widgets/delivery_ui.dart';

class AccountScreen extends StatefulWidget {
  const AccountScreen({super.key});

  @override
  State<AccountScreen> createState() => _AccountScreenState();
}

class _AccountScreenState extends State<AccountScreen> {
  Future<Map<String, dynamic>>? _account;
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _account ??= AppScope.of(context).account();
  }

  Future<void> _reload() async {
    setState(() {
      _account = AppScope.of(context).account();
    });
    await _account;
  }

  Future<void> _withdraw() async {
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).withdraw();
      MiddoHaptics.success();
      if (!mounted) return;
      showDeliverySnack(context, res['message']?.toString() ?? 'Submitted.');
      await _reload();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const DeliveryMobileHeader(title: 'Account', showBack: true),
      body: RefreshIndicator(
        onRefresh: _reload,
        child: FutureBuilder<Map<String, dynamic>>(
          future: _account,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return DeliveryError(snap.error!, onRetry: _reload);
            }
            final data = snap.data ?? const {};
            final balance = (data['balance'] as num?)?.toInt() ?? 0;
            final cashOnHand = (data['cash_on_hand'] as num?)?.toInt() ?? 0;
            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                DeliveryPanel(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Text(
                        'WALLET BALANCE',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          letterSpacing: 0.8,
                          color: MiddoColors.muted,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        '৳$balance',
                        style: const TextStyle(
                          fontSize: 32,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Cash on hand ৳$cashOnHand',
                        style: const TextStyle(
                          color: MiddoColors.inkSoft,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      if (data['preferred_payout_channel'] != null) ...[
                        const SizedBox(height: 4),
                        Text(
                          'Payout: ${data['preferred_payout_channel']}',
                          style: const TextStyle(
                            color: MiddoColors.muted,
                            fontSize: 12,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton(
                  onPressed: _busy || balance <= 0 ? null : _withdraw,
                  child: const Text('Withdraw balance'),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}
