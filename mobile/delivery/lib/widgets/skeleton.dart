import 'package:flutter/material.dart';

import '../theme/middo_colors.dart';

class MiddoSkeleton extends StatefulWidget {
  const MiddoSkeleton({super.key, required this.child});

  final Widget child;

  @override
  State<MiddoSkeleton> createState() => _MiddoSkeletonState();
}

class _MiddoSkeletonState extends State<MiddoSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (context, child) {
        final t = 0.55 + (_controller.value * 0.35);
        return Opacity(opacity: t, child: child);
      },
      child: widget.child,
    );
  }
}

class SkeletonBox extends StatelessWidget {
  const SkeletonBox({
    super.key,
    this.height = 16,
    this.width,
    this.radius = 10,
  });

  final double height;
  final double? width;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return MiddoSkeleton(
      child: Container(
        height: height,
        width: width,
        decoration: BoxDecoration(
          color: MiddoColors.creamDeep,
          borderRadius: BorderRadius.circular(radius),
          border: Border.all(color: MiddoColors.creamBorder),
        ),
      ),
    );
  }
}

class ListSkeleton extends StatelessWidget {
  const ListSkeleton({super.key, this.rows = 4});

  final int rows;

  @override
  Widget build(BuildContext context) {
    return ListView.separated(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
      itemCount: rows,
      separatorBuilder: (_, __) => const SizedBox(height: 12),
      itemBuilder: (_, __) => const SkeletonBox(height: 110, radius: 14),
    );
  }
}
