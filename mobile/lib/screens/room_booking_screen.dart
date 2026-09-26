import 'package:flutter/material.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/format.dart';
import '../widgets/dashed_divider.dart';
import '../widgets/oasis_button.dart';
import '../widgets/wireframe_placeholder.dart';
import 'booking_payment_screen.dart';

/// C3 - Room details + booking form ("Create account & continue").
class RoomBookingScreen extends StatefulWidget {
  const RoomBookingScreen({super.key});

  @override
  State<RoomBookingScreen> createState() => _RoomBookingScreenState();
}

class _RoomBookingScreenState extends State<RoomBookingScreen> {
  final _fullName = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  DateTime? _moveInDate;
  String? _error;
  bool _submitting = false;

  @override
  void dispose() {
    _fullName.dispose();
    _email.dispose();
    _phone.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _pickMoveInDate() async {
    final now = DateTime.now();
    final picked = await showDatePicker(
      context: context,
      initialDate: now,
      firstDate: now,
      // Locked business rule: move-in date must be selected within 7 days of booking.
      lastDate: now.add(const Duration(days: 7)),
    );
    if (picked != null) setState(() => _moveInDate = picked);
  }

  Future<void> _continue() async {
    final app = AppStateScope.of(context);
    final loggedIn = app.isLoggedIn;

    if (!loggedIn &&
        (_fullName.text.trim().isEmpty ||
            _email.text.trim().isEmpty ||
            _phone.text.trim().isEmpty)) {
      setState(() => _error = 'Please fill in your name, email, and phone.');
      return;
    }
    if (_moveInDate == null) {
      setState(() => _error = 'Please select a move-in date.');
      return;
    }
    if (!loggedIn &&
        (_password.text.isEmpty || _password.text != _confirm.text)) {
      setState(() => _error = 'Passwords must match.');
      return;
    }
    setState(() {
      _error = null;
      _submitting = true;
    });
    try {
      if (loggedIn) {
        await app.submitBookingForCurrentUser(moveInDate: _moveInDate!);
      } else {
        await app.submitBookingAndAccount(
          fullName: _fullName.text.trim(),
          email: _email.text.trim(),
          phone: _phone.text.trim(),
          password: _password.text,
          moveInDate: _moveInDate!,
        );
      }
      if (mounted) {
        Navigator.of(
          context,
        ).push(MaterialPageRoute(builder: (_) => const BookingPaymentScreen()));
      }
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final room = app.draft?.room;
    final loggedIn = app.isLoggedIn;
    return Scaffold(
      appBar: AppBar(
        leading: const BackButton(),
        title: const Text('Book a room'),
      ),
      body: room == null
          ? const Center(child: Text('No room selected'))
          : SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const WireframePlaceholder(
                    label: 'photo gallery',
                    height: 170,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    '${room.label} · ${formatPeso(room.monthlyRent)}/mo',
                    style: const TextStyle(
                      fontSize: 17,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    '${room.sizeSqm.toStringAsFixed(0)} sqm · ${room.amenities.join(', ')} · ${room.statusTag}',
                    style: const TextStyle(
                      color: OasisColors.muted,
                      fontSize: 12,
                    ),
                  ),
                  const DashedDivider(verticalGap: 18),
                  if (loggedIn) ...[
                    Text(
                      'Booking as ${app.profile?.fullName ?? 'your account'}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 14),
                  ] else ...[
                    TextField(
                      controller: _fullName,
                      decoration: const InputDecoration(hintText: 'Full name'),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: _email,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(hintText: 'Email'),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: _phone,
                      keyboardType: TextInputType.phone,
                      decoration: const InputDecoration(hintText: 'Phone'),
                    ),
                    const SizedBox(height: 14),
                  ],
                  InkWell(
                    onTap: _pickMoveInDate,
                    child: InputDecorator(
                      decoration: const InputDecoration(
                        hintText: 'Move-in date',
                      ),
                      isEmpty: _moveInDate == null,
                      child: _moveInDate == null
                          ? null
                          : Text(
                              formatShortDate(_moveInDate!),
                              style: const TextStyle(fontSize: 15),
                            ),
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Must be within 7 days of booking',
                    style: TextStyle(fontSize: 11, color: OasisColors.muted),
                  ),
                  if (!loggedIn) ...[
                    const SizedBox(height: 14),
                    TextField(
                      controller: _password,
                      obscureText: true,
                      decoration: const InputDecoration(hintText: 'Password'),
                    ),
                    const SizedBox(height: 14),
                    TextField(
                      controller: _confirm,
                      obscureText: true,
                      decoration: const InputDecoration(
                        hintText: 'Confirm password',
                      ),
                    ),
                  ],
                  if (_error != null) ...[
                    const SizedBox(height: 12),
                    Text(
                      _error!,
                      style: const TextStyle(
                        color: Colors.redAccent,
                        fontSize: 12,
                      ),
                    ),
                  ],
                  const SizedBox(height: 20),
                  OasisButton(
                    label: loggedIn ? 'Continue' : 'Create account & continue',
                    onPressed: _submitting ? null : _continue,
                  ),
                  if (_submitting)
                    const Padding(
                      padding: EdgeInsets.only(top: 16),
                      child: Center(child: CircularProgressIndicator()),
                    ),
                ],
              ),
            ),
    );
  }
}
