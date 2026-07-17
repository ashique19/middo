import 'package:flutter/material.dart';

import '../theme/middo_colors.dart';

/// Branded full-page / inline loading state so route changes never look stuck.
class MiddoPageLoader extends StatefulWidget {
  const MiddoPageLoader({
    super.key,
    this.message = 'Loading…',
    this.compact = false,
  });

  final String message;
  final bool compact;

  @override
  State<MiddoPageLoader> createState() => _MiddoPageLoaderState();
}

class _MiddoPageLoaderState extends State<MiddoPageLoader>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse;

  @override
  void initState() {
    super.initState();
    _pulse = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1100),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final markSize = widget.compact ? 44.0 : 64.0;

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            FadeTransition(
              opacity: Tween<double>(begin: 0.78, end: 1).animate(
                CurvedAnimation(parent: _pulse, curve: Curves.easeInOut),
              ),
              child: ScaleTransition(
                scale: Tween<double>(begin: 0.96, end: 1).animate(
                  CurvedAnimation(parent: _pulse, curve: Curves.easeInOut),
                ),
                child: Container(
                  width: markSize,
                  height: markSize,
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(markSize * 0.28),
                    boxShadow: [
                      BoxShadow(
                        color: MiddoColors.forest.withValues(alpha: 0.16),
                        blurRadius: 18,
                        offset: const Offset(0, 8),
                      ),
                    ],
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: Image.asset(
                    'assets/images/middo-app-icon.png',
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => Container(
                      color: MiddoColors.forest,
                      alignment: Alignment.center,
                      child: Text(
                        'M',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: markSize * 0.42,
                          fontWeight: FontWeight.w800,
                          height: 1,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
            SizedBox(height: widget.compact ? 14 : 20),
            SizedBox(
              width: widget.compact ? 22 : 26,
              height: widget.compact ? 22 : 26,
              child: const CircularProgressIndicator(
                strokeWidth: 2.6,
                color: MiddoColors.orange,
              ),
            ),
            const SizedBox(height: 14),
            Text(
              widget.message,
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: widget.compact ? 13 : 14,
                fontWeight: FontWeight.w700,
                color: MiddoColors.inkSoft,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Scaffold wrapper used by pushed routes while their data loads.
class MiddoLoadingScaffold extends StatelessWidget {
  const MiddoLoadingScaffold({
    super.key,
    this.title,
    this.message = 'Loading…',
  });

  final String? title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: title == null
          ? null
          : AppBar(
              title: Text(title!),
            ),
      body: MiddoPageLoader(message: message),
    );
  }
}
