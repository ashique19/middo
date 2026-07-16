import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../data/mock_repository.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class MenuScreen extends StatefulWidget {
  const MenuScreen({super.key});

  @override
  State<MenuScreen> createState() => _MenuScreenState();
}

class _MenuScreenState extends State<MenuScreen> {
  String _filter = 'All';

  static const filters = ['All', 'Thalis', 'Light', 'Veg', 'Protein'];

  @override
  Widget build(BuildContext context) {
    final items = MockRepository.instance.menu.where((item) {
      if (_filter == 'All') return true;
      return item.tags.contains(_filter);
    }).toList();

    return SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
        children: [
          Text(
            'Today’s Menu',
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.8,
                ),
          ),
          const SizedBox(height: 4),
          const Text(
            'Curated thalis for Gulshan & Banani offices.',
            style: TextStyle(
              color: MiddoColors.inkSoft,
              fontWeight: FontWeight.w600,
              fontSize: 13,
            ),
          ),
          const SizedBox(height: 14),
          SizedBox(
            height: 38,
            child: ListView.separated(
              scrollDirection: Axis.horizontal,
              itemCount: filters.length,
              separatorBuilder: (_, __) => const SizedBox(width: 8),
              itemBuilder: (context, index) {
                final label = filters[index];
                final active = label == _filter;
                return ChoiceChip(
                  label: Text(label),
                  selected: active,
                  onSelected: (_) => setState(() => _filter = label),
                  selectedColor: MiddoColors.forest,
                  labelStyle: TextStyle(
                    color: active ? Colors.white : MiddoColors.inkSoft,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                  backgroundColor: MiddoColors.white,
                  side: BorderSide(
                    color: active ? MiddoColors.forest : const Color(0xFFDDD3BE),
                  ),
                  showCheckmark: false,
                );
              },
            ),
          ),
          const SizedBox(height: 14),
          ...items.map((item) {
            return Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: Material(
                color: MiddoColors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(16),
                  side: const BorderSide(color: MiddoColors.creamBorder),
                ),
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: () => context.push('/checkout/${item.id}'),
                  child: Padding(
                    padding: const EdgeInsets.all(10),
                    child: Row(
                      children: [
                        ClipRRect(
                          borderRadius: BorderRadius.circular(14),
                          child: Image.asset(
                            item.imageAsset,
                            width: 88,
                            height: 88,
                            fit: BoxFit.cover,
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                item.name,
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  fontSize: 14,
                                ),
                              ),
                              const SizedBox(height: 4),
                              Text(
                                item.description,
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w600,
                                  color: MiddoColors.inkSoft,
                                ),
                              ),
                              const SizedBox(height: 8),
                              Row(
                                children: [
                                  Text(
                                    bdt.format(item.price),
                                    style: const TextStyle(
                                      color: MiddoColors.orange,
                                      fontWeight: FontWeight.w800,
                                      fontSize: 14,
                                    ),
                                  ),
                                  const Spacer(),
                                  const MiddoBadge(
                                    label: 'Order',
                                    tone: MiddoBadgeTone.orange,
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ),
            );
          }),
        ],
      ),
    );
  }
}
