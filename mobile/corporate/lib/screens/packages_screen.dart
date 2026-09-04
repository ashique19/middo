import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class PackagesScreen extends StatefulWidget {
  const PackagesScreen({super.key});

  @override
  State<PackagesScreen> createState() => _PackagesScreenState();
}

class _PackagesScreenState extends State<PackagesScreen> {
  Future<List<MealPackage>>? _future;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).packages();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MiddoColors.cream,
      appBar: AppBar(
        title: const Text('Monthly package'),
        backgroundColor: MiddoColors.cream,
        foregroundColor: MiddoColors.ink,
        elevation: 0,
      ),
      body: FutureBuilder<List<MealPackage>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const ListSkeleton(rows: 3);
          }
          if (snapshot.hasError) {
            return MiddoEmptyState(
              icon: Icons.cloud_off_rounded,
              title: 'Couldn’t load packages',
              message: snapshot.error.toString(),
            );
          }

          final packages = snapshot.data ?? [];

          return ListView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
            children: [
              Text(
                'Subscribe to the monthly office lunch package, choose menus for working days, set off-days, and prepay. Operations schedules exact dates.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: MiddoColors.muted,
                      fontWeight: FontWeight.w600,
                    ),
              ),
              const SizedBox(height: 16),
              if (packages.isEmpty)
                const MiddoEmptyState(
                  icon: Icons.inventory_2_outlined,
                  title: 'No packages available',
                  message: 'No monthly package is published right now.',
                )
              else
                ...packages.map(
                  (pkg) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _PackageCard(
                      package: pkg,
                      onTap: () => context.push('/packages/${pkg.id}'),
                    ),
                  ),
                ),
              TextButton(
                onPressed: () => context.push('/subscriptions'),
                child: const Text('My subscriptions'),
              ),
            ],
          );
        },
      ),
    );
  }
}

class _PackageCard extends StatelessWidget {
  const _PackageCard({required this.package, required this.onTap});

  final MealPackage package;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.white,
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Container(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: MiddoColors.creamBorder),
          ),
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      package.name,
                      style: const TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 18,
                      ),
                    ),
                  ),
                  Text(
                    '৳${package.pricePerDay}',
                    style: const TextStyle(
                      color: MiddoColors.orange,
                      fontWeight: FontWeight.w900,
                      fontSize: 22,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 4),
              Text(
                '${package.durationDays} days · ${package.daysCount} menus',
                style: TextStyle(
                  color: MiddoColors.muted,
                  fontWeight: FontWeight.w600,
                  fontSize: 12,
                ),
              ),
              if (package.summary.isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(package.summary),
              ],
              const SizedBox(height: 12),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: onTap,
                  style: FilledButton.styleFrom(
                    backgroundColor: MiddoColors.orange,
                    foregroundColor: Colors.white,
                  ),
                  child: const Text('Subscribe'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
