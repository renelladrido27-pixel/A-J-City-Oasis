import 'package:flutter/material.dart';

import '../models/room.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
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
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await AppStateScope.of(context).loadRooms();
      if (mounted) setState(() => _loading = false);
    } on ApiException catch (e) {
      if (mounted) {
        setState(() {
          _loading = false;
          _error = e.message;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    return RefreshIndicator(
      onRefresh: _load,
      color: OasisColors.green,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            LandingHeader(
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
            const SizedBox(height: 20),
            _Hero(availableRooms: _loading ? null : app.availableRooms.length),
            const SizedBox(height: 24),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Available rooms',
                  style: TextStyle(
                    color: OasisColors.muted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                const Icon(
                  Icons.arrow_forward,
                  size: 18,
                  color: OasisColors.muted,
                ),
              ],
            ),
            const SizedBox(height: 12),
            if (_loading)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 24),
                child: Center(child: CircularProgressIndicator()),
              )
            else if (_error != null)
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    _error!,
                    style: const TextStyle(color: Colors.redAccent),
                  ),
                  const SizedBox(height: 8),
                  TextButton(onPressed: _load, child: const Text('Retry')),
                ],
              )
            else
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: app.availableRooms.length,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 2,
                  mainAxisSpacing: 14,
                  crossAxisSpacing: 14,
                  childAspectRatio: 0.95,
                ),
                itemBuilder: (context, i) {
                  final room = app.availableRooms[i];
                  return _RoomCard(
                    room: room,
                    onTap: () {
                      app.startBooking(room);
                      Navigator.of(context).push(
                        MaterialPageRoute(
                          builder: (_) => const RoomBookingScreen(),
                        ),
                      );
                    },
                  );
                },
              ),
            const SizedBox(height: 24),
            const Text(
              'About · Contact · Location',
              style: TextStyle(color: OasisColors.muted),
            ),
          ],
        ),
      ),
    );
  }
}

class _RoomCard extends StatelessWidget {
  final Room room;
  final VoidCallback onTap;
  const _RoomCard({required this.room, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(6),
      child: Container(
        decoration: BoxDecoration(
          border: Border.all(color: OasisColors.border, width: 1.4),
          borderRadius: BorderRadius.circular(6),
        ),
        padding: const EdgeInsets.all(6),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(
              child: ClipRRect(
                borderRadius: BorderRadius.circular(4),
                child: RoomPhoto(
                  url: room.images.isEmpty ? null : room.images.first,
                ),
              ),
            ),
            const SizedBox(height: 6),
            Text(
              '${room.label} · ${formatPeso(room.monthlyRent)}/mo',
              textAlign: TextAlign.center,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ],
        ),
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
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(14),
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [OasisColors.green, OasisColors.greenDark],
        ),
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
          Text(
            rooms == null
                ? 'Transparent pricing and secure online payments.'
                : rooms == 0
                ? 'All rooms are taken right now — check back soon.'
                : '$rooms ${rooms == 1 ? 'room' : 'rooms'} available now · pay securely online',
            style: const TextStyle(color: Colors.white, fontSize: 13),
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
      borderRadius: BorderRadius.circular(6),
    );
    final logIn = OutlinedButton(
      onPressed: onLogIn,
      style: OutlinedButton.styleFrom(
        visualDensity: _compact.visualDensity,
        tapTargetSize: _compact.tapTargetSize,
        minimumSize: _compact.minimumSize,
        padding: _compact.padding,
        foregroundColor: OasisColors.ink,
        side: const BorderSide(color: OasisColors.border),
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
