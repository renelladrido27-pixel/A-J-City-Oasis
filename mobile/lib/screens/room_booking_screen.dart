import 'package:flutter/material.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/account_validators.dart';
import '../utils/format.dart';
import '../widgets/account_fields.dart';
import '../widgets/oasis_button.dart';
import '../widgets/room_photo.dart';
import 'booking_payment_screen.dart';
import 'verify_email_screen.dart';

/// C3 - Room details + booking form ("Create account & continue").
class RoomBookingScreen extends StatefulWidget {
  const RoomBookingScreen({super.key});

  @override
  State<RoomBookingScreen> createState() => _RoomBookingScreenState();
}

class _RoomBookingScreenState extends State<RoomBookingScreen> {
  final _account = AccountFormControllers();
  DateTime? _moveInDate;
  String? _error;
  bool _submitting = false;

  @override
  void dispose() {
    _account.dispose();
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

    final problem = loggedIn ? null : _account.validate();
    if (problem != null) {
      setState(() => _error = problem);
      return;
    }
    if (_moveInDate == null) {
      setState(() => _error = 'Please select a move-in date.');
      return;
    }
    setState(() {
      _error = null;
      _submitting = true;
    });
    try {
      // Step 1: create the account (new visitors only).
      if (!loggedIn) {
        await app.signUp(
          firstName: _account.firstName.text.trim(),
          middleName: _account.middleName.text.trim(),
          lastName: _account.lastName.text.trim(),
          email: _account.email.text.trim(),
          phone: normalizePhone(_account.phone.text),
          password: _account.password.text,
        );
      }
      // Step 2: the emailed 6-digit code. The room isn't reserved until this is done.
      if (!mounted || !await VerifyEmailScreen.ensureVerified(context)) return;
      // Step 3: reserve the room, then go to payment.
      await app.submitBookingForCurrentUser(moveInDate: _moveInDate!);
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
                  RoomGallery(images: room.images),
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
                    '${room.floorLabel.isEmpty ? '' : '${room.floorLabel} · '}${room.sizeSqm.toStringAsFixed(0)} sqm · ${room.amenities.join(', ')} · ${room.statusTag}',
                    style: const TextStyle(
                      color: OasisColors.muted,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 14),
                  _UpfrontSummary(
                    upfront: room.upfrontTotal,
                    monthly: room.monthlyRent,
                  ),
                  const Divider(height: 36),
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
                    AccountIdentityFields(controllers: _account),
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
                    AccountPasswordFields(controllers: _account),
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

/// "Pay today to book" — the 3-month upfront total, made hard to miss, with
/// the ongoing monthly rent beside it (mirrors the website's breakdown box).
class _UpfrontSummary extends StatelessWidget {
  final double upfront;
  final double monthly;
  const _UpfrontSummary({required this.upfront, required this.monthly});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        border: Border.all(color: OasisColors.gold, width: 2),
        borderRadius: BorderRadius.circular(12),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(
            color: OasisColors.green,
            padding: const EdgeInsets.symmetric(vertical: 12, horizontal: 14),
            child: Column(
              children: [
                const Text(
                  'PAY TODAY TO BOOK',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                    letterSpacing: 0.6,
                  ),
                ),
                Text(
                  formatPeso(upfront),
                  style: const TextStyle(
                    color: OasisColors.gold,
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const Text(
                  '1 month advance + 1 month deposit + security deposit',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: Colors.white, fontSize: 11),
                ),
              ],
            ),
          ),
          Container(
            color: OasisColors.sand,
            padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 14),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text('Then, monthly rent'),
                Text(
                  '${formatPeso(monthly)} / month',
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
