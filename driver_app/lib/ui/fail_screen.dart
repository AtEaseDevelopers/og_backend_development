import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/location.dart';
import '../models/models.dart';
import '../state/app_state.dart';
import '../state/sync_service.dart';
import 'widgets/common.dart';
import 'widgets/photo_field.dart';

/// Items 46–51: predefined reason, remarks, required proof photos, automatic GPS and time.
class FailScreen extends StatefulWidget {
  const FailScreen({super.key, required this.task});

  final DriverTask task;

  @override
  State<FailScreen> createState() => _FailScreenState();
}

class _FailScreenState extends State<FailScreen> {
  final _form = GlobalKey<FormState>();
  final _remarks = TextEditingController();
  String? _reason;
  List<String> _photos = [];
  GeoPoint? _gps;
  bool _busy = false;

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    if (_photos.isEmpty) return showMessage(context, 'Supporting proof photos are required.', error: true);

    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Submit failed delivery?'),
        content: Text('${widget.task.displayRef} will be marked as failed and sent to Admin for reassignment.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(
            style: FilledButton.styleFrom(backgroundColor: Colors.red.shade700),
            onPressed: () => Navigator.pop(ctx, true),
            child: const Text('Submit'),
          ),
        ],
      ),
    );
    if (ok != true || !mounted) return;

    setState(() => _busy = true);
    final failedAt = DateTime.now();
    final gps = _gps ?? await LocationService.current();
    if (!mounted) return;
    await context.read<SyncService>().enqueue(
      kind: 'fail',
      endpoint: '/deliveries/${widget.task.id}/fail',
      deliveryOrderId: widget.task.id,
      label: 'Failed · ${widget.task.displayRef}',
      files: [for (final p in _photos) OutboxFile(field: 'photo_paths', kind: 'failed_photo', localPath: p)],
      payload: {
        'reason': _reason,
        'remarks': _remarks.text.trim().isEmpty ? null : _remarks.text.trim(),
        'latitude': gps?.latitude,
        'longitude': gps?.longitude,
        'failed_at': failedAt.toUtc().toIso8601String(),
      },
    );
    if (!mounted) return;
    showMessage(context, 'Failed delivery recorded.');
    Navigator.of(context)
      ..pop()
      ..pop();
  }

  @override
  Widget build(BuildContext context) {
    final reasons = context.watch<AppState>().meta?.failureReasons ?? const <Option>[];
    return Scaffold(
      appBar: AppBar(title: const Text('Failed delivery'), backgroundColor: Colors.red.shade700),
      body: Form(
        key: _form,
        child: SingleChildScrollView(
          padding: const EdgeInsets.only(bottom: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SectionCard(
                title: widget.task.displayRef,
                icon: Icons.inventory_2_outlined,
                child: Column(
                  children: [InfoRow('Consignee', widget.task.consigneeName), InfoRow('Address', widget.task.address)],
                ),
              ),
              SectionCard(
                title: 'Reason',
                icon: Icons.rule,
                child: Column(
                  children: [
                    DropdownButtonFormField<String>(
                      initialValue: _reason,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Failure reason *'),
                      items: [for (final o in reasons) DropdownMenuItem(value: o.value, child: Text(o.label))],
                      onChanged: (v) => setState(() => _reason = v),
                      validator: (v) => v == null ? 'Select a reason' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _remarks,
                      maxLines: 3,
                      decoration: InputDecoration(labelText: _reason == 'other' ? 'Remarks *' : 'Remarks'),
                      validator: (v) =>
                          _reason == 'other' && (v == null || v.trim().isEmpty) ? 'Describe the reason' : null,
                    ),
                  ],
                ),
              ),
              SectionCard(
                title: 'Proof photos *',
                icon: Icons.photo_camera_outlined,
                child: PhotoField(
                  photos: _photos,
                  onChanged: (v) => setState(() => _photos = v),
                  addLabel: 'Take proof photo',
                ),
              ),
              SectionCard(
                title: 'GPS & time (automatic)',
                icon: Icons.my_location,
                child: AutoCapture(onChanged: (gps, _) => _gps = gps),
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: SubmitBar(
        label: 'Submit failed delivery',
        icon: Icons.report_gmailerrorred_outlined,
        color: Colors.red.shade700,
        busy: _busy,
        onPressed: _submit,
      ),
    );
  }
}
