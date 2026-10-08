import 'package:flutter/material.dart';

import '../theme.dart';

/// The shared look of the app's screens: white rounded cards with a hairline
/// border and a soft shadow, pill filters, status chips, and rows that fade
/// and slide in. Home, Rental, Pay and Alerts are all built from these so they
/// look and move the same way.

/// Fades and slides a row in; rows further down start a beat later so a list
/// arrives top to bottom.
class ListEntrance extends StatelessWidget {
  final int index;
  final Widget child;
  const ListEntrance({super.key, required this.index, required this.child});

  @override
  Widget build(BuildContext context) {
    final delayMs = 45 * index.clamp(0, 8);
    final totalMs = 320 + delayMs;
    return TweenAnimationBuilder<double>(
      tween: Tween(begin: 0, end: 1),
      duration: Duration(milliseconds: totalMs),
      curve: Interval(delayMs / totalMs, 1, curve: Curves.easeOutCubic),
      builder: (context, t, child) => Opacity(
        opacity: t,
        child: Transform.translate(
          offset: Offset(0, 14 * (1 - t)),
          child: child,
        ),
      ),
      child: child,
    );
  }
}

/// Cross-fades (with a slight rise) whenever [child]'s key changes — used
/// between tabs and between the sections inside a tab. Fills its parent.
class FadeThrough extends StatelessWidget {
  final Widget child;
  const FadeThrough({super.key, required this.child});

  @override
  Widget build(BuildContext context) {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 260),
      switchInCurve: Curves.easeOutCubic,
      switchOutCurve: Curves.easeIn,
      layoutBuilder: (current, previous) =>
          Stack(fit: StackFit.expand, children: [...previous, ?current]),
      transitionBuilder: (child, animation) => FadeTransition(
        opacity: animation,
        child: SlideTransition(
          position: Tween(
            begin: const Offset(0, 0.02),
            end: Offset.zero,
          ).animate(animation),
          child: child,
        ),
      ),
      child: child,
    );
  }
}

class OasisCard extends StatelessWidget {
  final Widget child;
  final EdgeInsetsGeometry padding;
  final VoidCallback? onTap;

  /// Tinted green instead of white (e.g. something that needs attention).
  final bool highlighted;

  const OasisCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(16),
    this.onTap,
    this.highlighted = false,
  });

  static const radius = 14.0;

  @override
  Widget build(BuildContext context) {
    final card = AnimatedContainer(
      duration: const Duration(milliseconds: 280),
      curve: Curves.easeOut,
      clipBehavior: Clip.antiAlias,
      decoration: BoxDecoration(
        color: highlighted ? OasisColors.unreadTint : Colors.white,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(
          color: highlighted
              ? OasisColors.green.withValues(alpha: 0.35)
              : OasisColors.hairline,
        ),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: highlighted ? 0.07 : 0.04),
            blurRadius: highlighted ? 14 : 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Material(
        type: MaterialType.transparency,
        child: onTap == null
            ? Padding(padding: padding, child: child)
            : InkWell(
                onTap: onTap,
                child: Padding(padding: padding, child: child),
              ),
      ),
    );
    return onTap == null ? card : PressableScale(child: card);
  }
}

/// Shrinks its child a touch while a finger is on it.
class PressableScale extends StatefulWidget {
  final Widget child;
  const PressableScale({super.key, required this.child});

  @override
  State<PressableScale> createState() => _PressableScaleState();
}

class _PressableScaleState extends State<PressableScale> {
  bool _down = false;

  @override
  Widget build(BuildContext context) {
    return Listener(
      onPointerDown: (_) => setState(() => _down = true),
      onPointerUp: (_) => setState(() => _down = false),
      onPointerCancel: (_) => setState(() => _down = false),
      child: AnimatedScale(
        scale: _down ? 0.97 : 1,
        duration: const Duration(milliseconds: 120),
        curve: Curves.easeOut,
        child: widget.child,
      ),
    );
  }
}

/// Round tinted icon at the start of a card.
class IconBadge extends StatelessWidget {
  final IconData icon;
  final bool filled;
  final double size;
  const IconBadge(this.icon, {super.key, this.filled = false, this.size = 40});

  @override
  Widget build(BuildContext context) {
    return AnimatedContainer(
      duration: const Duration(milliseconds: 280),
      width: size,
      height: size,
      decoration: BoxDecoration(
        color: filled ? OasisColors.green : OasisColors.sand,
        shape: BoxShape.circle,
      ),
      child: Icon(
        icon,
        size: size / 2,
        color: filled ? Colors.white : OasisColors.green,
      ),
    );
  }
}

/// Selectable pill (the Alerts filter, the Rental sections).
class OasisPill extends StatelessWidget {
  final String label;
  final IconData? icon;
  final bool selected;
  final VoidCallback onTap;
  const OasisPill({
    super.key,
    required this.label,
    required this.selected,
    required this.onTap,
    this.icon,
  });

  @override
  Widget build(BuildContext context) {
    final color = selected ? Colors.white : OasisColors.ink;
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 9),
        decoration: BoxDecoration(
          color: selected ? OasisColors.green : Colors.white,
          borderRadius: BorderRadius.circular(20),
          border: Border.all(
            color: selected ? OasisColors.green : OasisColors.placeholderGrey,
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (icon != null) ...[
              Icon(icon, size: 16, color: color),
              const SizedBox(width: 6),
            ],
            Text(
              label,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: color,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

/// A row of [OasisPill]s. Scrolls sideways when it doesn't fit, or — with
/// [fit] — shrinks so that every pill stays on screen.
class PillRow extends StatelessWidget {
  final List<Widget> pills;
  final bool fit;
  const PillRow({super.key, required this.pills, this.fit = false});

  @override
  Widget build(BuildContext context) {
    if (fit) {
      return Padding(
        padding: const EdgeInsets.symmetric(horizontal: 20),
        child: FittedBox(
          fit: BoxFit.scaleDown,
          alignment: Alignment.centerLeft,
          child: Row(
            children: [
              for (final (i, p) in pills.indexed) ...[
                if (i > 0) const SizedBox(width: 8),
                p,
              ],
            ],
          ),
        ),
      );
    }
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Row(
        children: [
          for (final (i, p) in pills.indexed) ...[
            if (i > 0) const SizedBox(width: 8),
            p,
          ],
        ],
      ),
    );
  }
}

enum ChipTone { success, warning, danger, neutral }

/// Small coloured status label ("Active", "Pending", "Resolved"…).
class StatusChip extends StatelessWidget {
  final String label;
  final ChipTone tone;
  const StatusChip(this.label, {super.key, required this.tone});

  /// Picks the colour from the status wording the server uses.
  factory StatusChip.forStatus(String status) {
    final tone = switch (status.toLowerCase()) {
      'active' ||
      'approved' ||
      'resolved' ||
      'paid' ||
      'up to date' => ChipTone.success,
      'pending' || 'in progress' || 'payment due' => ChipTone.warning,
      'rejected' || 'cancelled' || 'overdue' => ChipTone.danger,
      _ => ChipTone.neutral,
    };
    return StatusChip(status, tone: tone);
  }

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = switch (tone) {
      ChipTone.success => (OasisColors.unreadTint, OasisColors.green),
      ChipTone.warning => (const Color(0xFFFBF1DD), const Color(0xFF8A6116)),
      ChipTone.danger => (const Color(0xFFFBE4E2), const Color(0xFFA1281E)),
      ChipTone.neutral => (OasisColors.sand, OasisColors.muted),
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: bg,
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        style: TextStyle(color: fg, fontSize: 12, fontWeight: FontWeight.w700),
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  final IconData icon;
  final String message;
  final String? hint;
  const EmptyState({
    super.key,
    required this.icon,
    required this.message,
    this.hint,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 40, horizontal: 20),
      child: Column(
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: const BoxDecoration(
              color: OasisColors.sand,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 30, color: OasisColors.muted),
          ),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: OasisColors.muted),
          ),
          if (hint != null) ...[
            const SizedBox(height: 4),
            Text(
              hint!,
              textAlign: TextAlign.center,
              style: const TextStyle(color: OasisColors.muted, fontSize: 12),
            ),
          ],
        ],
      ),
    );
  }
}

/// Small grey heading above a group of cards.
class SectionLabel extends StatelessWidget {
  final String text;
  final Widget? trailing;
  const SectionLabel(this.text, {super.key, this.trailing});

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: OasisColors.muted,
              fontWeight: FontWeight.w700,
              fontSize: 13,
              letterSpacing: 0.3,
            ),
          ),
        ),
        ?trailing,
      ],
    );
  }
}

/// Grey block that gently pulses while real content is loading.
class Skeleton extends StatefulWidget {
  final double? height;
  final double radius;
  const Skeleton({super.key, this.height, this.radius = OasisCard.radius});

  @override
  State<Skeleton> createState() => _SkeletonState();
}

class _SkeletonState extends State<Skeleton>
    with SingleTickerProviderStateMixin {
  late final _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return FadeTransition(
      opacity: Tween(begin: 0.45, end: 1.0).animate(_controller),
      child: Container(
        height: widget.height,
        decoration: BoxDecoration(
          color: const Color(0xFFE6ECE8),
          borderRadius: BorderRadius.circular(widget.radius),
        ),
      ),
    );
  }
}
