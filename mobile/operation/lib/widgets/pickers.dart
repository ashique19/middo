import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';

Future<int?> pickRiderId(BuildContext context) async {
  try {
    final board = await AppScope.of(context).ridersBoard();
    if (!context.mounted) return null;
    final riders = (board['riders'] as List?) ?? const [];
    if (riders.isEmpty) {
      showSnack(context, 'No riders on the board.');
      return null;
    }

    return await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => ListView(
        children: [
          const ListTile(title: Text('Pick rider')),
          ...riders.map((raw) {
            final row = Map<String, dynamic>.from(raw as Map);
            final id = row['id'] as int? ?? 0;
            return ListTile(
              title: Text(row['name']?.toString() ?? 'Rider #$id'),
              subtitle: Text(row['mobile']?.toString() ?? ''),
              onTap: () => Navigator.pop(ctx, id),
            );
          }),
        ],
      ),
    );
  } on ApiException catch (e) {
    if (context.mounted) showSnack(context, e.message);
    return null;
  }
}

Future<int?> pickKitchenId(BuildContext context) async {
  try {
    final sla = await AppScope.of(context).slaBoard();
    if (!context.mounted) return null;
    final kitchens = (sla['kitchens'] as List?) ?? const [];
    if (kitchens.isEmpty) {
      showSnack(context, 'No kitchens available.');
      return null;
    }

    return await showModalBottomSheet<int>(
      context: context,
      showDragHandle: true,
      builder: (ctx) => ListView(
        children: [
          const ListTile(title: Text('Pick kitchen')),
          ...kitchens.map((raw) {
            final row = Map<String, dynamic>.from(raw as Map);
            final id = row['id'] as int? ?? 0;
            return ListTile(
              title: Text(row['name']?.toString() ?? 'Kitchen #$id'),
              subtitle: Text('Slots left: ${row['remaining_slots'] ?? '—'}'),
              onTap: () => Navigator.pop(ctx, id),
            );
          }),
        ],
      ),
    );
  } on ApiException catch (e) {
    if (context.mounted) showSnack(context, e.message);
    return null;
  }
}

Future<String?> promptText(
  BuildContext context, {
  required String title,
  String label = 'Reason',
  bool requiredField = false,
}) async {
  final controller = TextEditingController();
  final result = await showDialog<String>(
    context: context,
    builder: (ctx) => AlertDialog(
      title: Text(title),
      content: TextField(
        controller: controller,
        decoration: InputDecoration(labelText: label),
        autofocus: true,
        maxLines: 3,
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(ctx),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: () {
            final text = controller.text.trim();
            if (requiredField && text.isEmpty) return;
            Navigator.pop(ctx, text);
          },
          child: const Text('OK'),
        ),
      ],
    ),
  );
  controller.dispose();
  return result;
}

void showSnack(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
}
