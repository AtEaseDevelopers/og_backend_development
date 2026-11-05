import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/location.dart';
import '../models/models.dart';
import '../state/app_state.dart';
import '../state/sync_service.dart';
import 'widgets/common.dart';
import 'widgets/photo_field.dart';

/// Items 39–44: Break-Bulk request with reason, intermediate location, photo evidence, automatic GPS and time.
class BreakBulkScreen extends StatefulWidget {
  const BreakBulkScreen({super.key, required this.task});

  final DriverTask task;

  @override
  State<BreakBulkScreen> createState() => _BreakBulkScreenState();
}

class _BreakBulkScreenState extends State<BreakBulkScreen> {
  final _form = GlobalKey<FormState>();
  final _otherReason = TextEditingController();
  final _location = TextEditingController();
  String? _reason;
  List<String> _photos = [];
  GeoPoint? _gps;
  bool _busy = false;

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    if (_photos.isEmpty) {
      return showMessage(context, 'Upload a photo of the goods at the intermediate location.', error: true);
    }

    setState(() => _busy = true);
    final reportedAt = DateTime.now();
    final gps = _gps ?? await LocationService.current();
    if (!mounted) return;
    await context.read<SyncService>().enqueue(
      kind: 'break_bulk',
      endpoint: '/deliveries/${widget.task.id}/break-bulk',
      deliveryOrderId: widget.task.id,
      label: 'Break-Bulk · ${widget.task.displayRef}',
      files: [for (final p in _photos) OutboxFile(field: 'photo_paths', kind: 'break_bulk_photo', localPath: p)],
      payload: {
        'reason': _reason == 'other' ? _otherReason.text.trim() : _reason,
        'location': _location.text.trim(),
        'latitude': gps?.latitude,
        'longitude': gps?.longitude,
        'reported_at': reportedAt.toUtc().toIso8601String(),
      },
    );
    if (!mounted) return;
    showMessage(context, 'Break-Bulk requested. Admin will assign a replacement driver or lorry.');
    Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final reasons = context.watch<AppState>().meta?.breakBulkReasons ?? const <Option>[];
    return Scaffold(
      appBar: AppBar(title: const Text('Request Break-Bulk'), backgroundColor: Colors.brown),
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
                  children: [
                    InfoRow('Consignee', widget.task.consigneeName),
                    InfoRow('Items', widget.task.itemSummary),
                  ],
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
                      decoration: const InputDecoration(labelText: 'Reason *'),
                      items: [for (final o in reasons) DropdownMenuItem(value: o.value, child: Text(o.label))],
                      onChanged: (v) => setState(() => _reason = v),
                      validator: (v) => v == null ? 'Select a reason' : null,
                    ),
                    if (_reason == 'other') ...[
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _otherReason,
                        decoration: const InputDecoration(labelText: 'Enter reason *'),
                        validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter the reason' : null,
                      ),
                    ],
                  ],
                ),
              ),
              SectionCard(
                title: 'Intermediate location',
                icon: Icons.warehouse_outlined,
                child: TextFormField(
                  controller: _location,
                  decoration: const InputDecoration(
                    labelText: 'Where are the goods offloaded? *',
                    hintText: 'e.g. Seremban transit hub, bay 3',
                  ),
                  validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter the offload location' : null,
                ),
              ),
              SectionCard(
                title: 'Photo of goods at the location *',
                icon: Icons.photo_camera_outlined,
                child: PhotoField(photos: _photos, onChanged: (v) => setState(() => _photos = v)),
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
        label: 'Submit Break-Bulk',
        icon: Icons.call_split,
        color: Colors.brown,
        busy: _busy,
        onPressed: _submit,
      ),
    );
  }
}
