import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../models/room.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/oasis_ui.dart';
import '../widgets/room_photo.dart';
import 'auth_screen.dart';
import 'room_booking_screen.dart';

/// C1 - Landing (public). Logged-in tenants also land here on the Home tab;
/// the Log in / Sign up buttons are simply hidden once authenticated.
class LandingScreen extends StatefulWidget {
  const LandingScreen({super.key});

  @override
  State<LandingScreen> createState() => _LandingScreenState();
}

class _LandingScreenState extends State<LandingScreen> {
  bool _loading = true;
  String? _error;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_loading) _load();
  }

  Future<void> _load() async {
    final app = AppStateScope.of(context);
    setState(() {
      // Coming back to Home with the rooms already in memory: show them at
      // once and refresh quietly, instead of flashing the loading state.
      _loading = app.availableRooms.isEmpty;
      _error = null;
    });
    try {
      await app.loadRooms();
      if (mounted) setState(() => _loading = false);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = app.availableRooms.isEmpty ? e.message : null;
        });
      }
    }
  }

  void _book(Room room) {
    AppStateScope.of(context).startBooking(room);
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const RoomBookingScreen()));
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final rooms = app.availableRooms;
    return RefreshIndicator(
      onRefresh: _load,
      color: OasisColors.green,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            ListEntrance(
              index: 0,
              child: LandingHeader(
                showAuthButtons: !app.isLoggedIn,
                onLogIn: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const AuthScreen(initialTabLogin: true),
                  ),
                ),
                onSignUp: () => Navigator.of(context).push(
                  MaterialPageRoute(
                    builder: (_) => const AuthScreen(initialTabLogin: false),
                  ),
                ),
              ),
            ),
            const SizedBox(height: 20),
            ListEntrance(
              index: 1,
              child: _Hero(availableRooms: _loading ? null : rooms.length),
            ),
            const SizedBox(height: 24),
            ListEntrance(
              index: 2,
              child: SectionLabel(
                'AVAILABLE ROOMS',
                trailing: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 220),
                  child: _loading || rooms.isEmpty
                      ? const SizedBox.shrink()
                      : StatusChip(
                          '${rooms.length} open',
                          key: ValueKey(rooms.length),
                          tone: ChipTone.success,
                        ),
                ),
              ),
            ),
            const SizedBox(height: 12),
            // Cross-fades from the loading placeholders to the real cards.
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 260),
              child: _loading
                  ? const _RoomGrid(
                      key: ValueKey('loading'),
                      count: 4,
                      builder: _skeletonCard,
                    )
                  : _error != null
                  ? OasisCard(
                      key: const ValueKey('error'),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _error!,
                            style: const TextStyle(color: Colors.redAccent),
                          ),
                          const SizedBox(height: 4),
                          TextButton.icon(
                            onPressed: _load,
                            icon: const Icon(Icons.refresh, size: 18),
                            label: const Text('Try again'),
                          ),
                        ],
                      ),
                    )
                  : rooms.isEmpty
                  ? const EmptyState(
                      key: ValueKey('empty'),
                      icon: Icons.bed_outlined,
                      message: 'All rooms are taken right now.',
                      hint: 'Pull down to check again.',
                    )
                  : _RoomGrid(
                      key: const ValueKey('rooms'),
                      count: rooms.length,
                      builder: (i) => ListEntrance(
                        index: i,
                        child: _RoomCard(
                          room: rooms[i],
                          onTap: () => _book(rooms[i]),
                        ),
                      ),
                    ),
            ),
            const SizedBox(height: 24),
            const ListEntrance(index: 3, child: _ContactCard()),
          ],
        ),
      ),
    );
  }
}

Widget _skeletonCard(int _) => const Skeleton();

/// Two-column grid inside the page's own scroll view.
class _RoomGrid extends StatelessWidget {
  final int count;
  final Widget Function(int index) builder;
  const _RoomGrid({super.key, required this.count, required this.builder});

  @override
  Widget build(BuildContext context) {
    return GridView.builder(
      shrinkWrap: true,
      padding: EdgeInsets.zero,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: count,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        mainAxisSpacing: 14,
        crossAxisSpacing: 14,
        mainAxisExtent: 214,
      ),
      itemBuilder: (context, i) => builder(i),
    );
  }
}

class _RoomCard extends StatelessWidget {
  final Room room;
  final VoidCallback onTap;
  const _RoomCard({required this.room, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return OasisCard(
      padding: EdgeInsets.zero,
      onTap: onTap,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Expanded(
            child: RoomPhoto(
              url: room.images.isEmpty ? null : room.images.first,
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(10, 8, 10, 10),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Room ${room.number}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                if (room.floorLabel.isNotEmpty)
                  Text(
                    room.floorLabel,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: OasisColors.muted,
                    ),
                  ),
                const SizedBox(height: 4),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: Alignment.centerLeft,
                  child: Text.rich(
                    TextSpan(
                      children: [
                        TextSpan(
                          text: formatPeso(room.monthlyRent),
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: OasisColors.green,
                          ),
                        ),
                        const TextSpan(
                          text: ' / month',
                          style: TextStyle(
                            fontSize: 12,
                            color: OasisColors.muted,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// Where the place is and how to reach it — the website's contact section.
class _ContactCard extends StatelessWidget {
  const _ContactCard();

  static const _email = 'ajoasis.system@gmail.com';

  @override
  Widget build(BuildContext context) {
    return OasisCard(
      child: Column(
        children: [
          const Row(
            children: [
              IconBadge(Icons.place_outlined),
              SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Find us in Koronadal City',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    Text(
                      'South Cotabato · walk-throughs by appointment',
                      style: TextStyle(color: OasisColors.muted, fontSize: 12),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const Divider(height: 24),
          InkWell(
            onTap: () => launchUrl(Uri.parse('mailto:$_email')),
            child: const Row(
              children: [
                IconBadge(Icons.mail_outline),
                SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Email us',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        _email,
                        style: TextStyle(
                          color: OasisColors.muted,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
                Icon(Icons.chevron_right, color: OasisColors.muted),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

/// The home screen's banner — the website's hero, sized for a phone.
class _Hero extends StatelessWidget {
  /// Null while the room list is still loading.
  final int? availableRooms;
  const _Hero({required this.availableRooms});

  @override
  Widget build(BuildContext context) {
    final rooms = availableRooms;
    final line = rooms == null
        ? 'Transparent pricing and secure online payments.'
        : rooms == 0
        ? 'All rooms are taken right now — check back soon.'
        : '$rooms ${rooms == 1 ? 'room' : 'rooms'} available now · pay securely online';
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(16),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [OasisColors.green, OasisColors.greenDark],
        ),
        boxShadow: [
          BoxShadow(
            color: OasisColors.green.withValues(alpha: 0.25),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Text(
              'Koronadal City, South Cotabato',
              style: TextStyle(
                fontSize: 11,
                fontWeight: FontWeight.w600,
                color: OasisColors.ink,
              ),
            ),
          ),
          const SizedBox(height: 12),
          const Text(
            'Find your next room.\nBook it in minutes.',
            style: TextStyle(
              color: Colors.white,
              fontSize: 22,
              height: 1.2,
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 8),
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 260),
            layoutBuilder: (current, previous) => Stack(
              alignment: Alignment.topLeft,
              children: [...previous, ?current],
            ),
            child: Text(
              line,
              key: ValueKey(line),
              style: const TextStyle(color: Colors.white, fontSize: 13),
            ),
          ),
        ],
      ),
    );
  }
}

/// Business name on the left, Log in / Sign up on the right. The name takes
/// whatever width is left and shrinks to fit, so on a narrow phone (or with a
/// large system font) the buttons are never pushed off the side of the screen.
class LandingHeader extends StatelessWidget {
  final bool showAuthButtons;
  final VoidCallback onLogIn;
  final VoidCallback onSignUp;
  const LandingHeader({
    super.key,
    required this.showAuthButtons,
    required this.onLogIn,
    required this.onSignUp,
  });

  static const _compact = (
    visualDensity: VisualDensity.compact,
    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
    minimumSize: Size(0, 36),
    padding: EdgeInsets.symmetric(horizontal: 12),
  );

  @override
  Widget build(BuildContext context) {
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(10),
    );
    final logIn = OutlinedButton(
      onPressed: onLogIn,
      style: OutlinedButton.styleFrom(
        visualDensity: _compact.visualDensity,
        tapTargetSize: _compact.tapTargetSize,
        minimumSize: _compact.minimumSize,
        padding: _compact.padding,
        foregroundColor: OasisColors.ink,
        side: const BorderSide(color: OasisColors.placeholderGrey),
        shape: shape,
      ),
      child: const Text('Log in', maxLines: 1, softWrap: false),
    );
    final signUp = ElevatedButton(
      onPressed: onSignUp,
      style: ElevatedButton.styleFrom(
        visualDensity: _compact.visualDensity,
        tapTargetSize: _compact.tapTargetSize,
        minimumSize: _compact.minimumSize,
        padding: _compact.padding,
        backgroundColor: const Color(0xFFE9E7DE),
        foregroundColor: OasisColors.ink,
        elevation: 0,
        shape: shape,
      ),
      child: const Text('Sign up', maxLines: 1, softWrap: false),
    );

    // Both halves are flexible and scale down inside their share of the row,
    // so nothing can overflow whatever the screen width or font size.
    return Row(
      children: [
        Expanded(
          flex: showAuthButtons ? 5 : 1,
          child: const FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.centerLeft,
            child: Text(
              'A&J CITY OASIS',
              maxLines: 1,
              softWrap: false,
              style: TextStyle(
                fontSize: 20,
                fontWeight: FontWeight.w800,
                color: OasisColors.green,
              ),
            ),
          ),
        ),
        if (showAuthButtons) ...[
          const SizedBox(width: 12),
          Flexible(
            flex: 6,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              alignment: Alignment.centerRight,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [logIn, const SizedBox(width: 8), signUp],
              ),
            ),
          ),
        ],
      ],
    );
  }
}
