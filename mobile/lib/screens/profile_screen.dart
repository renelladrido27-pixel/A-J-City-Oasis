import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/account_validators.dart';
import '../widgets/oasis_button.dart';
import '../widgets/oasis_ui.dart';
import 'verify_email_screen.dart';

/// C10 - Profile.
class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late final _firstName = TextEditingController(
    text: AppStateScope.of(context).profile?.firstName ?? '',
  );
  late final _middleName = TextEditingController(
    text: AppStateScope.of(context).profile?.middleName ?? '',
  );
  late final _lastName = TextEditingController(
    text: AppStateScope.of(context).profile?.lastName ?? '',
  );
  late final _email = TextEditingController(
    text: AppStateScope.of(context).profile?.email ?? '',
  );
  late final _phone = TextEditingController(
    text: AppStateScope.of(context).profile?.phone ?? '',
  );
  final _currentPassword = TextEditingController();
  final _newPassword = TextEditingController();
  final _picker = ImagePicker();
  bool _saving = false;
  bool _photoBusy = false;

  void _say(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  /// Take a photo or choose one, then upload it as the profile picture.
  Future<void> _changePhoto() async {
    final app = AppStateScope.of(context);
    final hasPhoto = app.profile?.photoUrl != null;

    final choice = await showModalBottomSheet<String>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, 'camera'),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, 'gallery'),
            ),
            if (hasPhoto)
              ListTile(
                leading: Icon(Icons.delete_outline, color: Colors.red.shade700),
                title: Text(
                  'Remove photo',
                  style: TextStyle(color: Colors.red.shade700),
                ),
                onTap: () => Navigator.pop(context, 'remove'),
              ),
          ],
        ),
      ),
    );
    if (choice == null) return;

    setState(() => _photoBusy = true);
    try {
      if (choice == 'remove') {
        await app.removeProfilePhoto();
        _say('Profile photo removed.');
      } else {
        // Downscaled on the phone so it stays under the server's 2 MB limit.
        final picked = await _picker.pickImage(
          source: choice == 'camera' ? ImageSource.camera : ImageSource.gallery,
          maxWidth: 800,
          maxHeight: 800,
          imageQuality: 85,
        );
        if (picked == null) return;
        await app.uploadProfilePhoto(picked.path);
        _say('Profile photo updated.');
      }
    } on ApiException catch (e) {
      _say(e.message);
    } catch (_) {
      _say(
        'Couldn\'t open the camera or gallery. Check the app\'s permissions.',
      );
    } finally {
      if (mounted) setState(() => _photoBusy = false);
    }
  }

  Future<void> _changePassword() async {
    final problem = _currentPassword.text.isEmpty
        ? 'Enter your current password.'
        : validatePassword(_newPassword.text);
    if (problem != null) return _say(problem);

    setState(() => _saving = true);
    try {
      await AppStateScope.of(context).changePassword(
        currentPassword: _currentPassword.text,
        newPassword: _newPassword.text,
      );
      _currentPassword.clear();
      _newPassword.clear();
      _say('Password changed.');
    } on ApiException catch (e) {
      _say(e.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _firstName.dispose();
    _middleName.dispose();
    _lastName.dispose();
    _email.dispose();
    _phone.dispose();
    _currentPassword.dispose();
    _newPassword.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    final problem =
        validateName(_firstName.text, 'first name') ??
        validateName(_middleName.text, 'middle name', required: false) ??
        validateName(_lastName.text, 'surname') ??
        validatePhone(_phone.text);
    if (problem != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(problem)));
      return;
    }

    setState(() => _saving = true);
    try {
      final app = AppStateScope.of(context);
      await app.updateProfile(
        firstName: _firstName.text.trim(),
        middleName: _middleName.text.trim(),
        lastName: _lastName.text.trim(),
        email: _email.text.trim(),
        phone: normalizePhone(_phone.text),
      );
      // A changed email has to be confirmed with a new code.
      if (mounted && app.needsEmailVerification) {
        await VerifyEmailScreen.ensureVerified(context);
      }
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(const SnackBar(content: Text('Profile saved.')));
      }
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _confirmLogout() async {
    final app = AppStateScope.of(context);
    final yes = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Log out?'),
        content: const Text(
          'You will need to sign in again to see your rental.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            style: FilledButton.styleFrom(backgroundColor: OasisColors.green),
            child: const Text('Log out'),
          ),
        ],
      ),
    );
    if (yes == true) await app.logout();
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    final profile = app.profile;
    final photoUrl = profile?.photoUrl;
    const gap = SizedBox(height: 12);

    final sections = <Widget>[
      // Who is signed in.
      OasisCard(
        padding: const EdgeInsets.fromLTRB(16, 20, 16, 8),
        child: Column(
          children: [
            GestureDetector(
              onTap: _photoBusy ? null : _changePhoto,
              child: Stack(
                children: [
                  Container(
                    width: 96,
                    height: 96,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: const Color(0xFFE6ECE8),
                      border: Border.all(color: Colors.white, width: 3),
                      boxShadow: [
                        BoxShadow(
                          color: Colors.black.withValues(alpha: 0.12),
                          blurRadius: 10,
                          offset: const Offset(0, 3),
                        ),
                      ],
                    ),
                    clipBehavior: Clip.antiAlias,
                    child: AnimatedSwitcher(
                      duration: const Duration(milliseconds: 260),
                      child: _photoBusy
                          ? const Center(
                              key: ValueKey('busy'),
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: OasisColors.green,
                              ),
                            )
                          : photoUrl != null
                          ? Image.network(
                              photoUrl,
                              key: ValueKey(photoUrl),
                              width: 96,
                              height: 96,
                              fit: BoxFit.cover,
                              errorBuilder: (_, _, _) => const Icon(
                                Icons.person_outline,
                                size: 42,
                                color: OasisColors.muted,
                              ),
                            )
                          : const Icon(
                              Icons.person_outline,
                              key: ValueKey('none'),
                              size: 42,
                              color: OasisColors.muted,
                            ),
                    ),
                  ),
                  Positioned(
                    right: 0,
                    bottom: 0,
                    child: Container(
                      padding: const EdgeInsets.all(6),
                      decoration: BoxDecoration(
                        color: OasisColors.green,
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.white, width: 2),
                      ),
                      child: const Icon(
                        Icons.photo_camera,
                        size: 15,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 12),
            Text(
              profile?.fullName ?? '',
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 18),
            ),
            if ((profile?.email ?? '').isNotEmpty) ...[
              const SizedBox(height: 2),
              Text(
                profile!.email,
                textAlign: TextAlign.center,
                style: const TextStyle(color: OasisColors.muted, fontSize: 13),
              ),
            ],
            const SizedBox(height: 8),
            if (profile != null)
              profile.emailVerified
                  ? const StatusChip('Email verified', tone: ChipTone.success)
                  : const StatusChip(
                      'Email not verified',
                      tone: ChipTone.warning,
                    ),
            TextButton.icon(
              onPressed: _photoBusy ? null : _changePhoto,
              icon: const Icon(Icons.photo_camera_outlined, size: 18),
              label: Text(
                photoUrl == null ? 'Add profile photo' : 'Change photo',
              ),
              style: TextButton.styleFrom(foregroundColor: OasisColors.green),
            ),
          ],
        ),
      ),

      const SectionLabel('PERSONAL DETAILS'),
      OasisCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _firstName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'First name'),
            ),
            gap,
            TextField(
              controller: _middleName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(
                labelText: 'Middle name (optional)',
              ),
            ),
            gap,
            TextField(
              controller: _lastName,
              textCapitalization: TextCapitalization.words,
              decoration: const InputDecoration(labelText: 'Surname'),
            ),
            gap,
            TextField(
              controller: _email,
              keyboardType: TextInputType.emailAddress,
              decoration: const InputDecoration(
                labelText: 'Email',
                prefixIcon: Icon(Icons.mail_outline, size: 20),
              ),
            ),
            gap,
            TextField(
              controller: _phone,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(
                labelText: 'Mobile number',
                hintText: '09XXXXXXXXX',
                prefixIcon: Icon(Icons.phone_outlined, size: 20),
              ),
            ),
            const SizedBox(height: 16),
            OasisButton(
              label: _saving ? 'Saving…' : 'Save changes',
              onPressed: _saving ? null : _save,
            ),
          ],
        ),
      ),

      const SectionLabel('CHANGE PASSWORD'),
      OasisCard(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            TextField(
              controller: _currentPassword,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'Current password',
                prefixIcon: Icon(Icons.lock_outline, size: 20),
              ),
            ),
            gap,
            TextField(
              controller: _newPassword,
              obscureText: true,
              decoration: const InputDecoration(
                labelText: 'New password',
                prefixIcon: Icon(Icons.lock_reset_outlined, size: 20),
                helperText:
                    '8+ characters with upper & lower case, a number and a symbol',
                helperMaxLines: 2,
              ),
            ),
            const SizedBox(height: 16),
            OasisButton(
              label: 'Change password',
              outlined: true,
              onPressed: _saving ? null : _changePassword,
            ),
          ],
        ),
      ),

      OasisCard(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
        onTap: _confirmLogout,
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: const BoxDecoration(
                color: Color(0xFFFBE4E2),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.logout,
                size: 20,
                color: Color(0xFFA1281E),
              ),
            ),
            const SizedBox(width: 12),
            const Expanded(
              child: Text(
                'Log out',
                style: TextStyle(
                  fontWeight: FontWeight.w700,
                  color: Color(0xFFA1281E),
                ),
              ),
            ),
            const Icon(Icons.chevron_right, color: OasisColors.muted),
          ],
        ),
      ),
    ];

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
      children: [
        const Text(
          'Profile',
          style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 14),
        for (final (i, section) in sections.indexed)
          Padding(
            // Section labels sit close to the card they introduce.
            padding: EdgeInsets.only(bottom: section is SectionLabel ? 10 : 20),
            child: ListEntrance(index: i, child: section),
          ),
      ],
    );
  }
}
