import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../utils/account_validators.dart';
import '../widgets/oasis_button.dart';
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

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Center(
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
                          border: Border.all(
                            color: OasisColors.border,
                            width: 1.4,
                          ),
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: _photoBusy
                            ? const Center(
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: OasisColors.green,
                                ),
                              )
                            : app.profile?.photoUrl != null
                            ? Image.network(
                                app.profile!.photoUrl!,
                                fit: BoxFit.cover,
                                errorBuilder: (_, _, _) => const Icon(
                                  Icons.person_outline,
                                  size: 42,
                                  color: OasisColors.muted,
                                ),
                              )
                            : const Icon(
                                Icons.person_outline,
                                size: 42,
                                color: OasisColors.muted,
                              ),
                      ),
                      Positioned(
                        right: 0,
                        bottom: 0,
                        child: Container(
                          padding: const EdgeInsets.all(6),
                          decoration: const BoxDecoration(
                            color: OasisColors.green,
                            shape: BoxShape.circle,
                          ),
                          child: const Icon(
                            Icons.photo_camera,
                            size: 16,
                            color: Colors.white,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 8),
                Text(
                  app.profile?.fullName ?? '',
                  style: const TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 16,
                  ),
                ),
                TextButton(
                  onPressed: _photoBusy ? null : _changePhoto,
                  style: TextButton.styleFrom(
                    foregroundColor: OasisColors.green,
                  ),
                  child: Text(
                    app.profile?.photoUrl == null
                        ? 'Add profile photo'
                        : 'Change photo',
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _firstName,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(hintText: 'First name'),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _middleName,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(
              hintText: 'Middle name (optional)',
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _lastName,
            textCapitalization: TextCapitalization.words,
            decoration: const InputDecoration(hintText: 'Surname'),
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
            decoration: const InputDecoration(
              hintText: 'Mobile number (09XXXXXXXXX)',
            ),
          ),
          const SizedBox(height: 14),
          const SizedBox(height: 6),
          OasisButton(label: 'Save', onPressed: _saving ? null : _save),
          const SizedBox(height: 28),
          const Text(
            'Change password',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _currentPassword,
            obscureText: true,
            decoration: const InputDecoration(hintText: 'Current password'),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _newPassword,
            obscureText: true,
            decoration: const InputDecoration(
              hintText: 'New password',
              helperText:
                  '8+ characters with upper & lower case, a number and a symbol',
              helperMaxLines: 2,
            ),
          ),
          const SizedBox(height: 14),
          OutlinedButton(
            onPressed: _saving ? null : _changePassword,
            style: OutlinedButton.styleFrom(
              foregroundColor: OasisColors.green,
              side: const BorderSide(color: OasisColors.green),
              minimumSize: const Size.fromHeight(46),
            ),
            child: const Text('Change password'),
          ),
          if (_saving)
            const Padding(
              padding: EdgeInsets.only(top: 16),
              child: Center(child: CircularProgressIndicator()),
            ),
          const SizedBox(height: 20),
          InkWell(
            onTap: () => app.logout(),
            child: const Text(
              'Log out',
              style: TextStyle(
                fontWeight: FontWeight.w600,
                decoration: TextDecoration.underline,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
