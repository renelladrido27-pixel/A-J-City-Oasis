import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:ota_update/ota_update.dart';
import 'package:package_info_plus/package_info_plus.dart';

import '../theme.dart';
import 'api_client.dart';

/// A newer build published on the server (GET /api/app/version).
class AppUpdate {
  final int build;
  final String version;
  final String apkUrl;
  final String? notes;
  const AppUpdate({
    required this.build,
    required this.version,
    required this.apkUrl,
    this.notes,
  });
}

/// In-app updates: the app can't replace its own code silently (Android
/// doesn't allow that outside the Play Store), but it can notice a newer
/// build, download it and hand it to Android's installer — one tap, no APK
/// passed around by hand, and the tenant stays signed in.
class AppUpdater {
  /// Returns the update if the server has a newer build than the one
  /// installed, otherwise null. Never throws — a failed check just means no
  /// update is offered this time.
  static Future<AppUpdate?> check() async {
    try {
      final installed = int.tryParse(
        (await PackageInfo.fromPlatform()).buildNumber,
      );
      final res = await http
          .get(
            Uri.parse('${ApiClient.baseUrl}/app/version'),
            headers: {'Accept': 'application/json'},
          )
          .timeout(const Duration(seconds: 10));
      if (installed == null || res.statusCode != 200) return null;

      final json = jsonDecode(res.body) as Map<String, dynamic>;
      final build = (json['build'] as num?)?.toInt() ?? 0;
      final url = json['apk_url'] as String?;
      if (url == null || build <= installed) return null;

      return AppUpdate(
        build: build,
        version: json['version'] as String? ?? '',
        apkUrl: url,
        notes: json['notes'] as String?,
      );
    } catch (_) {
      return null;
    }
  }

  /// Checks once and, if there's a newer build, shows the update dialog.
  static Future<void> checkAndPrompt(BuildContext context) async {
    final update = await check();
    if (update == null || !context.mounted) return;

    await showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _UpdateDialog(update: update),
    );
  }
}

class _UpdateDialog extends StatefulWidget {
  final AppUpdate update;
  const _UpdateDialog({required this.update});

  @override
  State<_UpdateDialog> createState() => _UpdateDialogState();
}

class _UpdateDialogState extends State<_UpdateDialog> {
  StreamSubscription<OtaEvent>? _download;
  int? _percent; // null = not started
  String? _error;

  @override
  void dispose() {
    _download?.cancel();
    super.dispose();
  }

  void _start() {
    setState(() {
      _percent = 0;
      _error = null;
    });

    try {
      _download = OtaUpdate()
          .execute(
            widget.update.apkUrl,
            destinationFilename: 'aj-city-oasis-update.apk',
          )
          .listen(
            (event) {
              if (!mounted) return;
              switch (event.status) {
                case OtaStatus.DOWNLOADING:
                  setState(() => _percent = int.tryParse(event.value ?? ''));
                case OtaStatus.INSTALLING:
                  // Android's installer takes over from here.
                  Navigator.of(context).pop();
                case OtaStatus.INSTALLATION_DONE:
                case OtaStatus.ALREADY_RUNNING_ERROR:
                  break;
                default:
                  setState(() {
                    _percent = null;
                    _error =
                        event.status == OtaStatus.PERMISSION_NOT_GRANTED_ERROR
                        ? 'Allow "Install unknown apps" for A&J CITY OASIS, then try again.'
                        : 'The update couldn\'t be installed. Check your connection and try again.';
                  });
              }
            },
            onError: (_) {
              if (mounted) {
                setState(() {
                  _percent = null;
                  _error =
                      'The update couldn\'t be downloaded. Check your connection and try again.';
                });
              }
            },
          );
    } catch (_) {
      setState(() {
        _percent = null;
        _error = 'The update couldn\'t be started on this device.';
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final downloading = _percent != null;
    final notes = widget.update.notes;
    return AlertDialog(
      title: const Text('Update available'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'Version ${widget.update.version} of A&J CITY OASIS is ready to install.',
          ),
          if (notes != null && notes.isNotEmpty) ...[
            const SizedBox(height: 10),
            Text(notes, style: const TextStyle(color: OasisColors.muted)),
          ],
          if (downloading) ...[
            const SizedBox(height: 16),
            LinearProgressIndicator(
              value: (_percent ?? 0) > 0 ? _percent! / 100 : null,
              color: OasisColors.green,
            ),
            const SizedBox(height: 6),
            Text(
              'Downloading… ${_percent ?? 0}%',
              style: const TextStyle(fontSize: 12, color: OasisColors.muted),
            ),
          ],
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(
              _error!,
              style: const TextStyle(color: Colors.redAccent, fontSize: 12),
            ),
          ],
        ],
      ),
      actions: [
        if (!downloading)
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Later'),
          ),
        if (!downloading)
          FilledButton(
            onPressed: _start,
            style: FilledButton.styleFrom(backgroundColor: OasisColors.green),
            child: Text(_error == null ? 'Update now' : 'Try again'),
          ),
      ],
    );
  }
}
