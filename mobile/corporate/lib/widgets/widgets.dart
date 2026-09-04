import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../models/models.dart';
import '../theme/middo_colors.dart';

export 'empty_state.dart';
export 'middo_app_drawer.dart';
export 'middo_page_loader.dart';
export 'network_banner.dart';
export 'skeleton.dart';

final bdt = NumberFormat.currency(locale: 'en_BD', symbol: '৳', decimalDigits: 0);

class MiddoBadge extends StatelessWidget {
  const MiddoBadge({
    super.key,
    required this.label,
    this.tone = MiddoBadgeTone.amber,
  });

  final String label;
  final MiddoBadgeTone tone;

  @override
  Widget build(BuildContext context) {
    final (bg, fg, border) = switch (tone) {
      MiddoBadgeTone.amber => (
          const Color(0xFFFFF7ED),
          const Color(0xFF9A3412),
          const Color(0xFFFED7AA),
        ),
      MiddoBadgeTone.green => (
          const Color(0xFFECFDF5),
          const Color(0xFF065F46),
          const Color(0xFFA7F3D0),
        ),
      MiddoBadgeTone.gray => (
          const Color(0xFFF3F4F6),
          const Color(0xFF6B7280),
          const Color(0xFFE5E7EB),
        ),
      MiddoBadgeTone.orange => (
          MiddoColors.orange,
          Colors.white,
          MiddoColors.orange,
        ),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: border),
      ),
      child: Text(
        label.toUpperCase(),
        style: TextStyle(
          color: fg,
          fontSize: 10,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.4,
        ),
      ),
    );
  }
}

enum MiddoBadgeTone { amber, green, gray, orange }

class MealOrderCard extends StatelessWidget {
  const MealOrderCard({
    super.key,
    required this.order,
    this.onTrack,
    this.onSecondary,
    this.onPay,
    this.secondaryLabel = 'Support',
  });

  final CorporateOrder order;
  final VoidCallback? onTrack;
  final VoidCallback? onSecondary;
  final VoidCallback? onPay;
  final String secondaryLabel;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: MiddoColors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: MiddoColors.creamBorder),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          MealImage(item: order.menuItem, height: 120),
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Text(
                      DateFormat('MMM d, yyyy').format(order.deliveryDate),
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: MiddoColors.inkSoft,
                      ),
                    ),
                    const Spacer(),
                    Text(
                      'Qty ${order.quantity}',
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                        color: MiddoColors.orange,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 6),
                Text(
                  order.menuItem.name,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.2,
                  ),
                ),
                if (order.canPayOnline && order.amountDue > 0) ...[
                  const SizedBox(height: 4),
                  Text(
                    'Due ${bdt.format(order.amountDue)}',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: MiddoColors.orange,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                Wrap(
                  spacing: 6,
                  runSpacing: 6,
                  children: [
                    MiddoBadge(
                      label: order.statusLabel,
                      tone: order.isHistory
                          ? MiddoBadgeTone.gray
                          : MiddoBadgeTone.amber,
                    ),
                    if (order.paid && !order.isHistory)
                      const MiddoBadge(
                        label: 'Paid',
                        tone: MiddoBadgeTone.green,
                      )
                    else if (order.amountPaid > 0 && !order.isHistory)
                      MiddoBadge(
                        label: 'Prepaid ${bdt.format(order.amountPaid)}',
                        tone: MiddoBadgeTone.green,
                      ),
                    if (!order.isHistory &&
                        (order.paymentMethodLabel?.isNotEmpty ?? false))
                      MiddoBadge(
                        label: order.paymentMethodLabel!,
                        tone: MiddoBadgeTone.amber,
                      ),
                    if (order.hasSeparateReceiver &&
                        (order.receiverName?.isNotEmpty ?? false))
                      MiddoBadge(
                        label: 'Recv: ${order.receiverName}',
                        tone: MiddoBadgeTone.amber,
                      ),
                    if (order.hasComplaint)
                      const MiddoBadge(
                        label: 'Complaint',
                        tone: MiddoBadgeTone.orange,
                      ),
                  ],
                ),
                if (onPay != null && order.canPayOnline) ...[
                  const SizedBox(height: 12),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      onPressed: onPay,
                      style: FilledButton.styleFrom(
                        backgroundColor: MiddoColors.orange,
                        padding: const EdgeInsets.symmetric(vertical: 12),
                      ),
                      child: const Text('Make Payment'),
                    ),
                  ),
                ],
                if (onTrack != null || onSecondary != null) ...[
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      if (onTrack != null)
                        Expanded(
                          child: OutlinedButton(
                            onPressed: onTrack,
                            child: Text(
                              order.isHistory ? 'Reorder' : 'Track',
                            ),
                          ),
                        ),
                      if (onTrack != null && onSecondary != null)
                        const SizedBox(width: 8),
                      if (onSecondary != null)
                        Expanded(
                          child: TextButton(
                            onPressed: onSecondary,
                            style: TextButton.styleFrom(
                              foregroundColor: MiddoColors.inkSoft,
                              side: const BorderSide(
                                color: MiddoColors.creamBorder,
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: Text(secondaryLabel),
                          ),
                        ),
                    ],
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class KpiCard extends StatelessWidget {
  const KpiCard({
    super.key,
    required this.label,
    required this.value,
    this.hint,
    this.dark = true,
  });

  final String label;
  final String value;
  final String? hint;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: dark
            ? const LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [MiddoColors.forest, Color(0xFF264F36)],
              )
            : null,
        color: dark ? null : MiddoColors.creamDeep,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: dark ? MiddoColors.forestDeep : const Color(0xFFDDD3BE),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w800,
              letterSpacing: 0.6,
              color: dark ? Colors.white70 : MiddoColors.inkSoft,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            value,
            style: TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.4,
              color: dark ? Colors.white : MiddoColors.ink,
            ),
          ),
          if (hint != null) ...[
            const SizedBox(height: 2),
            Text(
              hint!,
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: dark ? Colors.white70 : MiddoColors.inkSoft,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class MealImage extends StatelessWidget {
  const MealImage({
    super.key,
    required this.item,
    this.height,
    this.width,
    this.borderRadius = 0,
  });

  final MenuItem item;
  final double? height;
  final double? width;
  final double borderRadius;

  @override
  Widget build(BuildContext context) {
    final Widget image;
    if (item.hasNetworkImage) {
      image = CachedNetworkImage(
        imageUrl: item.imageUrl!,
        height: height,
        width: width,
        fit: BoxFit.cover,
        memCacheWidth: 900,
        placeholder: (_, __) => Container(
          height: height,
          width: width,
          color: MiddoColors.creamDeep,
          alignment: Alignment.center,
          child: const SizedBox(
            width: 22,
            height: 22,
            child: CircularProgressIndicator(strokeWidth: 2),
          ),
        ),
        errorWidget: (_, __, ___) => Image.asset(
          item.imageAsset,
          height: height,
          width: width,
          fit: BoxFit.cover,
        ),
      );
    } else {
      image = Image.asset(
        item.image,
        height: height,
        width: width,
        fit: BoxFit.cover,
      );
    }

    if (borderRadius <= 0) return image;
    return ClipRRect(
      borderRadius: BorderRadius.circular(borderRadius),
      child: image,
    );
  }
}

class SectionHeader extends StatelessWidget {
  const SectionHeader({
    super.key,
    required this.title,
    this.actionLabel,
    this.onAction,
  });

  final String title;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 22, bottom: 12),
      child: Row(
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              letterSpacing: -0.2,
            ),
          ),
          const Spacer(),
          if (actionLabel != null)
            TextButton(
              onPressed: onAction,
              style: TextButton.styleFrom(
                backgroundColor: MiddoColors.creamDeep,
                foregroundColor: MiddoColors.orange,
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                minimumSize: Size.zero,
                tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(999),
                ),
              ),
              child: Text(
                actionLabel!,
                style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w800),
              ),
            ),
        ],
      ),
    );
  }
}

/// Fixed-label / right-value row so summary card values share one column.
class MetaRow extends StatelessWidget {
  const MetaRow({
    super.key,
    required this.label,
    required this.value,
    this.valueColor,
    this.labelWidth = 72,
  });

  final String label;
  final String value;
  final Color? valueColor;
  final double labelWidth;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: labelWidth,
            child: Text(
              label,
              style: const TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 13,
                color: MiddoColors.inkSoft,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.right,
              style: TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 13,
                height: 1.35,
                color: valueColor ?? MiddoColors.ink,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
