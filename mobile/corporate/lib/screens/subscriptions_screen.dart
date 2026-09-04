import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class SubscriptionsScreen extends StatefulWidget {
  const SubscriptionsScreen({super.key});

  @override
  State<SubscriptionsScreen> createState() => _SubscriptionsScreenState();
}

class _SubscriptionsScreenState extends State<SubscriptionsScreen> {
  Future<List<PackageSubscription>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).myPackages();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MiddoColors.cream,
      appBar: AppBar(
        title: const Text('My packages'),
        backgroundColor: MiddoColors.cream,
        foregroundColor: MiddoColors.ink,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (context.canPop()) {
              context.pop();
            } else {
              context.go('/home');
            }
          },
        ),
        actions: [
          IconButton(
            tooltip: 'Home',
            onPressed: () => context.go('/home'),
            icon: const Icon(Icons.home_outlined),
          ),
        ],
      ),
      body: FutureBuilder<List<PackageSubscription>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const ListSkeleton(rows: 4);
          }
          if (snapshot.hasError) {
            return MiddoEmptyState(
              icon: Icons.cloud_off_rounded,
              title: 'Couldn’t load subscriptions',
              message: snapshot.error.toString(),
            );
          }
          final items = snapshot.data ?? [];
          if (items.isEmpty) {
            return MiddoEmptyState(
              icon: Icons.card_membership_outlined,
              title: 'No subscriptions yet',
              message:
                  'Subscribe to a monthly office lunch package to see it here.',
              actionLabel: 'Browse packages',
              onAction: () => context.go('/packages'),
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.all(18),
            itemCount: items.length,
            separatorBuilder: (_, __) => const SizedBox(height: 10),
            itemBuilder: (context, index) {
              final sub = items[index];
              return ListTile(
                tileColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: BorderSide(color: MiddoColors.creamBorder),
                ),
                title: Text(
                  sub.name,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                subtitle: Text(
                  '${sub.startDate} – ${sub.endDate} · ৳${sub.totalAmount}',
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => context.push('/subscriptions/${sub.id}'),
              );
            },
          );
        },
      ),
    );
  }
}
