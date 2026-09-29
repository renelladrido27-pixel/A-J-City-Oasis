import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

import '../services/api_exception.dart';
import '../state/app_state.dart';
import '../theme.dart';
import '../widgets/oasis_button.dart';

const _issueTypes = [
  'Plumbing',
  'Electrical',
  'Aircon',
  'Appliance',
  'Structural',
  'Other',
];

/// C8 - Maintenance Request.
class MaintenanceScreen extends StatefulWidget {
  const MaintenanceScreen({super.key});

  @override
  State<MaintenanceScreen> createState() => _MaintenanceScreenState();
}

class _MaintenanceScreenState extends State<MaintenanceScreen> {
  String? _issueType;
  final _description = TextEditingController();
  final _picker = ImagePicker();
  XFile? _photo;
  bool _submitting = false;

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
  }

  /// Bottom sheet: take a new photo or pick one from the gallery. Photos are
  /// downscaled/compressed on the phone so they stay well under the server's
  /// 5 MB limit and upload quickly on mobile data.
  Future<void> _pickPhoto() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      showDragHandle: true,
      builder: (context) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Take a photo'),
              onTap: () => Navigator.pop(context, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Choose from gallery'),
              onTap: () => Navigator.pop(context, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return;

    try {
      final picked = await _picker.pickImage(
        source: source,
        maxWidth: 1600,
        maxHeight: 1600,
        imageQuality: 80,
      );
      if (picked != null && mounted) setState(() => _photo = picked);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text(
            'Couldn\'t open the camera or gallery. Check the app\'s permissions.',
          ),
        ),
      );
    }
  }

  Future<void> _submit() async {
    if (_issueType == null || _description.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Select an issue type and describe the issue.'),
        ),
      );
      return;
    }
    setState(() => _submitting = true);
    try {
      await AppStateScope.of(context).submitMaintenanceRequest(
        _issueType!,
        _description.text.trim(),
        photoPath: _photo?.path,
      );
      if (!mounted) return;
      setState(() {
        _issueType = null;
        _description.clear();
        _photo = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Maintenance request submitted.')),
      );
    } on ApiException catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(e.message)));
      }
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = AppStateScope.of(context);
    return RefreshIndicator(
      onRefresh: app.refreshMaintenanceRequests,
      color: OasisColors.green,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(20, 4, 20, 24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _issueType,
              decoration: const InputDecoration(hintText: 'Issue type'),
              items: [
                for (final t in _issueTypes)
                  DropdownMenuItem(value: t, child: Text(t)),
              ],
              onChanged: (v) => setState(() => _issueType = v),
            ),
            const SizedBox(height: 14),
            TextField(
              controller: _description,
              maxLines: 4,
              decoration: const InputDecoration(hintText: 'Describe issue...'),
            ),
            const SizedBox(height: 14),
            _photo == null
                ? _PhotoDropZone(onTap: _pickPhoto)
                : _PhotoPreview(
                    file: File(_photo!.path),
                    onChange: _pickPhoto,
                    onRemove: () => setState(() => _photo = null),
                  ),
            const SizedBox(height: 18),
            OasisButton(
              label: 'Submit',
              onPressed: _submitting ? null : _submit,
            ),
            if (_submitting)
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: Center(child: CircularProgressIndicator()),
              ),
            const SizedBox(height: 24),
            for (final r in app.maintenanceRequests)
              Padding(
                padding: const EdgeInsets.only(bottom: 6),
                child: Text(
                  '- ${r.issueType} — ${r.status}',
                  style: const TextStyle(fontWeight: FontWeight.w500),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

/// Empty state: a dashed tap target, matching the web app's photo upload box.
class _PhotoDropZone extends StatelessWidget {
  final VoidCallback onTap;
  const _PhotoDropZone({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFFAFBFA),
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: CustomPaint(
          painter: _DashedBorderPainter(),
          child: const Padding(
            padding: EdgeInsets.symmetric(vertical: 22, horizontal: 16),
            child: Column(
              children: [
                CircleAvatar(
                  radius: 22,
                  backgroundColor: OasisColors.green,
                  child: Icon(Icons.photo_camera_outlined, color: Colors.white),
                ),
                SizedBox(height: 10),
                Text(
                  'Add a photo of the issue',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                SizedBox(height: 2),
                Text(
                  'Take a photo or choose from gallery (optional)',
                  style: TextStyle(color: OasisColors.muted, fontSize: 12),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _PhotoPreview extends StatelessWidget {
  final File file;
  final VoidCallback onChange;
  final VoidCallback onRemove;
  const _PhotoPreview({
    required this.file,
    required this.onChange,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFDEE2E6)),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          ClipRRect(
            borderRadius: BorderRadius.circular(8),
            child: Image.file(file, width: 84, height: 84, fit: BoxFit.cover),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Photo attached',
                  style: TextStyle(fontWeight: FontWeight.w600),
                ),
                const SizedBox(height: 6),
                Wrap(
                  spacing: 8,
                  children: [
                    OutlinedButton.icon(
                      onPressed: onChange,
                      icon: const Icon(Icons.refresh, size: 16),
                      label: const Text('Change'),
                    ),
                    TextButton.icon(
                      onPressed: onRemove,
                      icon: const Icon(Icons.delete_outline, size: 16),
                      label: const Text('Remove'),
                      style: TextButton.styleFrom(
                        foregroundColor: Colors.red.shade700,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _DashedBorderPainter extends CustomPainter {
  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = const Color(0xFFC9D3CD)
      ..strokeWidth = 2
      ..style = PaintingStyle.stroke;
    final path = Path()
      ..addRRect(
        RRect.fromRectAndRadius(Offset.zero & size, const Radius.circular(12)),
      );
    for (final metric in path.computeMetrics()) {
      for (double d = 0; d < metric.length; d += 12) {
        canvas.drawPath(metric.extractPath(d, d + 6), paint);
      }
    }
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}
