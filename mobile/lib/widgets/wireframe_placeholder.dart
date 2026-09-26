import 'package:flutter/material.dart';

import '../theme.dart';

/// Reproduces the wireframes' dashed-border "X" placeholder box (used for
/// hero art, photo galleries, and room photos) instead of inventing fake
/// photography that the design docs never specified.
class WireframePlaceholder extends StatelessWidget {
  final String label;
  final double height;
  final BorderRadius? borderRadius;

  const WireframePlaceholder({
    super.key,
    required this.label,
    this.height = 140,
    this.borderRadius,
  });

  @override
  Widget build(BuildContext context) {
    final radius = borderRadius ?? BorderRadius.circular(6);
    return ClipRRect(
      borderRadius: radius,
      child: SizedBox(
        height: height,
        width: double.infinity,
        child: CustomPaint(
          painter: _DashedBorderPainter(radius: radius),
          child: Stack(
            alignment: Alignment.center,
            fit: StackFit.expand,
            children: [
              CustomPaint(painter: _CrossPainter()),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                color: Colors.white.withValues(alpha: 0.85),
                child: Text(
                  label,
                  style: const TextStyle(
                    color: OasisColors.muted,
                    fontSize: 12,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CrossPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = OasisColors.placeholderGrey
      ..strokeWidth = 10
      ..strokeCap = StrokeCap.round;
    canvas.drawLine(Offset.zero, Offset(size.width, size.height), paint);
    canvas.drawLine(Offset(size.width, 0), Offset(0, size.height), paint);
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

class _DashedBorderPainter extends CustomPainter {
  final BorderRadius radius;
  _DashedBorderPainter({required this.radius});

  @override
  void paint(Canvas canvas, Size size) {
    final rrect = radius.toRRect(Offset.zero & size);
    final path = Path()..addRRect(rrect);
    final paint = Paint()
      ..color = OasisColors.placeholderGrey
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1.4;
    for (final metric in path.computeMetrics()) {
      double distance = 0;
      const dashWidth = 5.0;
      const dashGap = 4.0;
      while (distance < metric.length) {
        final next = distance + dashWidth;
        canvas.drawPath(
          metric.extractPath(distance, next.clamp(0, metric.length)),
          paint,
        );
        distance = next + dashGap;
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
