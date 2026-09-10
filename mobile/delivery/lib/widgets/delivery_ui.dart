import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../theme/middo_colors.dart';
import 'empty_state.dart';

void showDeliverySnack(
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

class DeliveryDialogField extends StatelessWidget {
  const DeliveryDialogField({
    super.key,
    required this.label,
    required this.controller,
    this.enabled = true,
    this.obscureText = false,
    this.autofocus = false,
    this.keyboardType,
    this.inputFormatters,
    this.maxLines = 1,
  });

  final String label;
  final TextEditingController controller;
  final bool enabled;
  final bool obscureText;
  final bool autofocus;
  final TextInputType? keyboardType;
  final List<TextInputFormatter>? inputFormatters;
  final int maxLines;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.8,
            color: MiddoColors.muted,
          ),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: controller,
          enabled: enabled,
          obscureText: obscureText,
          autofocus: autofocus,
          keyboardType: keyboardType,
          inputFormatters: inputFormatters,
          maxLines: maxLines,
          decoration: InputDecoration(
            isDense: true,
            hintText: label,
            floatingLabelBehavior: FloatingLabelBehavior.never,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 12,
              vertical: 12,
            ),
          ),
        ),
      ],
    );
  }
}

class DeliveryPanel extends StatelessWidget {
  const DeliveryPanel({super.key, required this.child, this.onTap});

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

class DeliveryEmpty extends StatelessWidget {
  const DeliveryEmpty(this.message, {super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    return MiddoEmptyState(
      icon: Icons.inbox_outlined,
      title: 'Nothing here',
      message: message,
    );
  }
}

class DeliveryError extends StatelessWidget {
  const DeliveryError(this.error, {super.key, this.onRetry});

  final Object error;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: [
        SizedBox(
          height: MediaQuery.sizeOf(context).height * 0.55,
          child: MiddoEmptyState(
            icon: Icons.error_outline,
            title: 'Could not load',
            message: '$error',
            actionLabel: onRetry != null ? 'Retry' : null,
            onAction: onRetry,
          ),
        ),
      ],
    );
  }
}

class DeliveryStatusChip extends StatelessWidget {
  const DeliveryStatusChip(this.label, {super.key, this.positive = false});

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
