import 'package:flutter/material.dart';

import '../theme/middo_colors.dart';

void showKitchenSnack(
  BuildContext context,
  String message, {
  bool error = false,
}) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(
      content: Text(message),
      backgroundColor: error ? MiddoColors.orangeDeep : MiddoColors.forest,
    ),
  );
}

Future<String?> promptKitchenText(
  BuildContext context, {
  required String title,
  String? hint,
  String confirmLabel = 'Submit',
  int minLength = 3,
}) async {
  final controller = TextEditingController();
  final result = await showDialog<String>(
    context: context,
    builder: (ctx) {
      return AlertDialog(
        title: Text(title),
        content: TextField(
          controller: controller,
          autofocus: true,
          maxLines: 3,
          decoration: InputDecoration(hintText: hint),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              final text = controller.text.trim();
              if (text.length < minLength) return;
              Navigator.pop(ctx, text);
            },
            child: Text(confirmLabel),
          ),
        ],
      );
    },
  );
  controller.dispose();
  return result;
}

class KitchenPanel extends StatelessWidget {
  const KitchenPanel({super.key, required this.child, this.onTap});

  final Widget child;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final panel = Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: MiddoColors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: MiddoColors.creamBorder),
      ),
      child: child,
    );
    if (onTap == null) return panel;
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: panel,
      ),
    );
  }
}

class KitchenEmpty extends StatelessWidget {
  const KitchenEmpty(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.all(24),
      child: Text(
        message,
        style: const TextStyle(color: MiddoColors.inkSoft),
      ),
    );
  }
}

class KitchenError extends StatelessWidget {
  const KitchenError(this.error, {super.key, this.onRetry});

  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      children: [
        Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Error: $error'),
              if (onRetry != null) ...[
                const SizedBox(height: 12),
                OutlinedButton(onPressed: onRetry, child: const Text('Retry')),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class KitchenStatusChip extends StatelessWidget {
  const KitchenStatusChip(this.label, {super.key, this.positive = false});

  final String label;
  final bool positive;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: positive ? MiddoColors.amberSoft : MiddoColors.creamDeep,
        borderRadius: BorderRadius.circular(8),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w700,
          color: positive ? MiddoColors.forest : MiddoColors.inkSoft,
        ),
      ),
    );
  }
}
