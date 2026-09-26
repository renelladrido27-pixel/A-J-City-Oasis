import 'package:flutter/material.dart';

import '../theme.dart';

/// Matches the dashed section-separator lines used throughout the wireframes.
class DashedDivider extends StatelessWidget {
  final double verticalGap;
  const DashedDivider({super.key, this.verticalGap = 20});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.symmetric(vertical: verticalGap),
      child: CustomPaint(
        size: const Size(double.infinity, 1),
        painter: _DashPainter(),
      ),
    );
  }
}

class _DashPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = OasisColors.placeholderGrey
      ..strokeWidth = 1.2;
    double x = 0;
    const dashWidth = 5.0;
    const dashGap = 4.0;
    while (x < size.width) {
      canvas.drawLine(Offset(x, 0), Offset(x + dashWidth, 0), paint);
      x += dashWidth + dashGap;
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
