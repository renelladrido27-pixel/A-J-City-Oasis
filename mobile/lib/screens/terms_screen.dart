import 'package:flutter/material.dart';

import '../models/rental_terms.dart';
import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/oasis_button.dart';
import '../widgets/oasis_ui.dart';

/// The Terms and Conditions for the room being booked. "I agree" at the
/// bottom closes it with `true`; the booking form only continues to payment
/// after that.
class TermsScreen extends StatefulWidget {
  final String roomId;
  const TermsScreen({super.key, required this.roomId});

  /// Opens the terms; true when the tenant pressed "I agree".
  static Future<bool> open(BuildContext context, String roomId) async {
    final agreed = await Navigator.of(context).push<bool>(
      MaterialPageRoute(builder: (_) => TermsScreen(roomId: roomId)),
    );
    return agreed ?? false;
  }

  @override
  State<TermsScreen> createState() => _TermsScreenState();
}

class _TermsScreenState extends State<TermsScreen> {
  RentalTerms? _terms;
  String? _error;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_terms == null && _error == null) _load();
  }

  Future<void> _load() async {
    setState(() => _error = null);
    try {
      final terms = await AppStateScope.of(
        context,
      ).fetchRentalTerms(widget.roomId);
      if (mounted) setState(() => _terms = terms);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e.message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final terms = _terms;
    return Scaffold(
      appBar: AppBar(
        leading: const BackButton(),
        title: const Text('Terms and Conditions'),
      ),
      body: terms == null
          ? Center(
              child: _error == null
                  ? const CircularProgressIndicator()
                  : Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(_error!, textAlign: TextAlign.center),
                          const SizedBox(height: 12),
                          OutlinedButton(
                            onPressed: _load,
                            child: const Text('Try again'),
                          ),
                        ],
                      ),
                    ),
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              children: [
                const SectionLabel('A&J CITY OASIS · RENTAL AGREEMENT'),
                const SizedBox(height: 12),
                for (final (i, section) in terms.sections.indexed)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: Text.rich(
                      TextSpan(
                        children: [
                          TextSpan(
                            text: '${i + 1}. ${section.heading}. ',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                          TextSpan(text: section.body),
                        ],
                      ),
                      style: const TextStyle(fontSize: 13.5, height: 1.45),
                    ),
                  ),
              ],
            ),
      bottomNavigationBar: terms == null
          ? null
          : SafeArea(
              child: Container(
                padding: const EdgeInsets.fromLTRB(20, 10, 20, 12),
                decoration: const BoxDecoration(
                  color: Colors.white,
                  border: Border(top: BorderSide(color: OasisColors.hairline)),
                ),
                child: OasisButton(
                  label: 'I agree',
                  onPressed: () => Navigator.of(context).pop(true),
                ),
              ),
            ),
    );
  }
}
