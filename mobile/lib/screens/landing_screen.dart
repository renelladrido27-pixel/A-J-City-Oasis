import 'package:flutter/material.dart';

import '../models/room.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/wireframe_placeholder.dart';
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
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'A & J OASIS',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w800,
                    color: OasisColors.green,
                  ),
                ),
                if (!app.isLoggedIn)
                  Row(
                    children: [
                      OutlinedButton(
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                const AuthScreen(initialTabLogin: true),
                          ),
                        ),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: OasisColors.ink,
                          side: const BorderSide(color: OasisColors.border),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(6),
                          ),
                        ),
                        child: const Text('Log in'),
                      ),
                      const SizedBox(width: 8),
                      ElevatedButton(
                        onPressed: () => Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) =>
                                const AuthScreen(initialTabLogin: false),
                          ),
                        ),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFE9E7DE),
                          foregroundColor: OasisColors.ink,
                          elevation: 0,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(6),
                          ),
                        ),
                        child: const Text('Sign up'),
                      ),
                    ],
                  ),
              ],
            ),
            const SizedBox(height: 20),
            WireframePlaceholder(label: 'hero + CTA', height: 150),
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
              child: WireframePlaceholder(label: '', height: double.infinity),
            ),
            const SizedBox(height: 6),
            Text(
              '${room.label} · ${formatPeso(room.monthlyRent)}/mo',
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}
