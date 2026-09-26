import 'package:flutter/material.dart';

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
  String? _attachedPhotoName;
  bool _submitting = false;

  @override
  void dispose() {
    _description.dispose();
    super.dispose();
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
      await AppStateScope.of(
        context,
      ).submitMaintenanceRequest(_issueType!, _description.text.trim());
      if (!mounted) return;
      setState(() {
        _issueType = null;
        _description.clear();
        _attachedPhotoName = null;
      });
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
            InkWell(
              onTap: () => setState(
                () => _attachedPhotoName =
                    'photo_${app.maintenanceRequests.length + 1}.jpg',
              ),
              child: InputDecorator(
                decoration: const InputDecoration(hintText: 'Attach photo'),
                child: Row(
                  children: [
                    const Icon(
                      Icons.attach_file,
                      size: 18,
                      color: OasisColors.muted,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      _attachedPhotoName ?? 'attach photo',
                      style: TextStyle(
                        color: _attachedPhotoName == null
                            ? OasisColors.muted
                            : OasisColors.ink,
                      ),
                    ),
                  ],
                ),
              ),
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
