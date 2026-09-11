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
            final dueToMiddo = (data['due_to_middo'] as num?)?.toInt() ??
                (data['cash_on_hand'] as num?)?.toInt() ??
                0;
            final canRequest = data['can_request_payment'] == true;
            final payoutComplete = data['has_complete_payout_method'] != false;
            final statement = (data['statement'] as List?) ?? const [];
            final withdrawals = (data['withdrawals'] as List?) ?? const [];
            final showBanner = dueToMiddo > 0 || !payoutComplete;

            return ListView(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              children: [
                if (showBanner)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Material(
                      color: MiddoColors.amberSoft,
                      borderRadius: BorderRadius.circular(12),
                      child: Padding(
                        padding: const EdgeInsets.all(12),
                        child: Text(
                          dueToMiddo > 0
                              ? 'Due to Middo ৳$dueToMiddo must be cleared before withdraw.'
                              : 'Complete your payout method in profile before withdrawing.',
                          style: const TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                            height: 1.35,
                          ),
                        ),
                      ),
                    ),
                  ),
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
                        'Due to Middo ৳$dueToMiddo',
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
                  onPressed: _busy || !canRequest ? null : _withdraw,
                  child: const Text('Withdraw balance'),
                ),
                if (statement.isNotEmpty) ...[
                  const SizedBox(height: 20),
                  const Text(
                    'Statement',
                    style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
                  ),
                  const SizedBox(height: 8),
                  ...statement.map((raw) {
                    final row = (raw as Map).cast<String, dynamic>();
                    final amount = (row['amount'] as num?)?.toInt() ?? 0;
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: DeliveryPanel(
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                row['label']?.toString() ??
                                    row['description']?.toString() ??
                                    'Entry',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ),
                            Text(
                              '${amount >= 0 ? '+' : ''}৳$amount',
                              style: TextStyle(
                                fontWeight: FontWeight.w800,
                                color: amount >= 0
                                    ? MiddoColors.forest
                                    : MiddoColors.orangeDeep,
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                ],
                if (withdrawals.isNotEmpty) ...[
                  const SizedBox(height: 16),
                  const Text(
                    'Withdrawals',
                    style: TextStyle(fontWeight: FontWeight.w900, fontSize: 15),
                  ),
                  const SizedBox(height: 8),
                  ...withdrawals.map((raw) {
                    final row = (raw as Map).cast<String, dynamic>();
                    return Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: DeliveryPanel(
                        child: Row(
                          children: [
                            Expanded(
                              child: Text(
                                '৳${row['amount'] ?? 0}',
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                ),
                              ),
                            ),
                            DeliveryStatusChip(
                              row['status']?.toString() ?? '',
                              positive: row['status'] == 'paid',
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}
