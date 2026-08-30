import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../theme/middo_colors.dart';

class GroupsScreen extends StatefulWidget {
  const GroupsScreen({super.key});

  @override
  State<GroupsScreen> createState() => _GroupsScreenState();
}

class _GroupsScreenState extends State<GroupsScreen> {
  Future<List<dynamic>>? _groups;
  bool _loaded = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_loaded) {
      _loaded = true;
      _groups = AppScope.of(context).orderGroups();
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Groups')),
      body: RefreshIndicator(
        onRefresh: () async {
          setState(() {
            _groups = AppScope.of(context).orderGroups();
          });
          await _groups;
        },
        child: FutureBuilder<List<dynamic>>(
          future: _groups,
          builder: (context, snap) {
            if (snap.connectionState != ConnectionState.done) {
              return const Center(child: CircularProgressIndicator());
            }
            if (snap.hasError) {
              return ListView(
                children: [
                  Padding(
                    padding: const EdgeInsets.all(24),
                    child: Text('Error: ${snap.error}'),
                  ),
                ],
              );
            }
            final groups = snap.data ?? const [];
            if (groups.isEmpty) {
              return ListView(
                children: const [
                  Padding(
                    padding: EdgeInsets.all(24),
                    child: Text('No groups in the claim pool.'),
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
              itemCount: groups.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, i) {
                final g = groups[i] as Map;
                final canAccept = g['can_accept'] == true;
                return Container(
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: MiddoColors.white,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(color: MiddoColors.creamBorder),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        g['name']?.toString() ?? 'Group',
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        '${g['menu_name'] ?? ''} · qty ${g['total_quantity'] ?? '—'}',
                        style: const TextStyle(color: MiddoColors.inkSoft),
                      ),
                      const SizedBox(height: 6),
                      Text(
                        canAccept ? 'Ready to accept' : 'Not available now',
                        style: TextStyle(
                          color: canAccept
                              ? MiddoColors.forest
                              : MiddoColors.muted,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                );
              },
            );
          },
        ),
      ),
    );
  }
}
