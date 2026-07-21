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
  String _filter = 'all';

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
        title: const Text('Meal Packages'),
        backgroundColor: MiddoColors.cream,
        foregroundColor: MiddoColors.ink,
        elevation: 0,
      ),
      body: FutureBuilder<List<MealPackage>>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const MiddoPageLoader(message: 'Loading packages…');
          }
          if (snapshot.hasError) {
            return Center(child: Text(snapshot.error.toString()));
          }

          final packages = (snapshot.data ?? []).where((p) {
            if (_filter == 'all') return true;
            return p.dietTag == _filter;
          }).toList();

          return ListView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
            children: [
              Text(
                'Pick a rate plan, choose menus and day counts for the month, set off-days, and prepay. Operations schedules exact dates.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: MiddoColors.muted,
                      fontWeight: FontWeight.w600,
                    ),
              ),
              const SizedBox(height: 14),
              Wrap(
                spacing: 8,
                runSpacing: 8,
                children: [
                  for (final entry in {
                    'all': 'All',
                    'classic': 'Classic',
                    'veg': 'Veg',
                    'vegetarian': 'Vegetarian',
                    'protein': 'Protein',
                    'light': 'Light',
                  }.entries)
                    ChoiceChip(
                      label: Text(entry.value),
                      selected: _filter == entry.key,
                      onSelected: (_) => setState(() => _filter = entry.key),
                      selectedColor: MiddoColors.forest,
                      labelStyle: TextStyle(
                        color: _filter == entry.key
                            ? Colors.white
                            : MiddoColors.ink,
                        fontWeight: FontWeight.w800,
                        fontSize: 12,
                      ),
                    ),
                ],
              ),
              const SizedBox(height: 16),
              if (packages.isEmpty)
                Container(
                  padding: const EdgeInsets.all(24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(color: MiddoColors.creamBorder),
                  ),
                  child: const Text(
                    'No published packages right now.',
                    textAlign: TextAlign.center,
                  ),
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
                '${package.dietTag} · ${package.durationDays} days · ${package.daysCount} menus',
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
                  child: const Text('Choose plan'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
