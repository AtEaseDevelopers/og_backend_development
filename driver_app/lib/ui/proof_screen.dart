import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../core/location.dart';
import '../models/models.dart';
import '../state/sync_service.dart';
import 'widgets/common.dart';
import 'widgets/photo_field.dart';

enum ProofType { pickup, handover }

/// Pickup-location photos (items 22, 35) and the assisting driver's handover proof (items 36–38).
class ProofScreen extends StatefulWidget {
  const ProofScreen({super.key, required this.task, required this.type});

  final DriverTask task;
  final ProofType type;

  @override
  State<ProofScreen> createState() => _ProofScreenState();
}

class _ProofScreenState extends State<ProofScreen> {
  final _remarks = TextEditingController();
  late final TextEditingController _handoverTo = TextEditingController(
    text: widget.task.subsheet?['main_driver']?.toString() ?? '',
  );
  List<String> _photos = [];
  GeoPoint? _gps;
  bool _busy = false;

  bool get _handover => widget.type == ProofType.handover;

  Future<void> _submit() async {
    if (_photos.isEmpty) {
      return showMessage(
        context,
        _handover ? 'Upload at least one handover photo.' : 'Take at least one pickup photo.',
        error: true,
      );
    }
    if (_handover) {
      final ok = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          title: const Text('Confirm handover'),
          content: Text(
            'You handed ${widget.task.displayRef} over to ${_handoverTo.text.trim().isEmpty ? 'the delivery driver' : _handoverTo.text.trim()}. '
            'This completes your additional task and makes it eligible for PSI.',
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Confirm')),
          ],
        ),
      );
      if (ok != true) return;
    }

    setState(() => _busy = true);
    final at = DateTime.now();
    final gps = _gps ?? await LocationService.current();
    if (!mounted) return;
    await context.read<SyncService>().enqueue(
      kind: _handover ? 'handover' : 'pickup',
      endpoint: '/deliveries/${widget.task.id}/${_handover ? 'handover' : 'pickup'}',
      deliveryOrderId: widget.task.id,
      label: '${_handover ? 'Handover' : 'Pickup'} · ${widget.task.displayRef}',
      files: [
        for (final p in _photos)
          OutboxFile(field: 'photo_paths', kind: _handover ? 'handover_photo' : 'pickup_photo', localPath: p),
      ],
      payload: {
        if (_handover) 'handover_to': _handoverTo.text.trim(),
        'remarks': _remarks.text.trim().isEmpty ? null : _remarks.text.trim(),
        'latitude': gps?.latitude,
        'longitude': gps?.longitude,
        'recorded_at': at.toUtc().toIso8601String(),
      },
    );
    if (!mounted) return;
    showMessage(context, _handover ? 'Handover confirmed.' : 'Pickup proof recorded.');
    Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final task = widget.task;
    return Scaffold(
      appBar: AppBar(title: Text(_handover ? 'Confirm handover' : 'Pickup proof')),
      body: ListView(
        padding: const EdgeInsets.only(bottom: 16),
        children: [
          SectionCard(
            title: task.displayRef,
            icon: Icons.inventory_2_outlined,
            child: Column(
              children: [
                if (task.isAdditionalPickup)
                  InfoRow('Pickup from', [task.consignorName, task.consignorAddress].whereType<String>().join(', ')),
                InfoRow('Deliver to', task.consigneeName),
                if (task.subsheet != null)
                  InfoRow(
                    'Main driver',
                    [task.subsheet!['main_driver'], task.subsheet!['main_lorry']].whereType<Object>().join(' · '),
                  ),
                InfoRow('Items', task.itemSummary),
              ],
            ),
          ),
          if (_handover)
            SectionCard(
              title: 'Handed over to',
              icon: Icons.person_pin_outlined,
              child: TextField(
                controller: _handoverTo,
                decoration: const InputDecoration(labelText: 'Delivery driver / lorry'),
              ),
            ),
          SectionCard(
            title: _handover ? 'Handover evidence *' : 'Pickup photos *',
            icon: Icons.photo_camera_outlined,
            child: PhotoField(photos: _photos, onChanged: (v) => setState(() => _photos = v), allowGallery: _handover),
          ),
          SectionCard(
            title: 'Remarks',
            icon: Icons.notes,
            child: TextField(
              controller: _remarks,
              maxLines: 2,
              decoration: const InputDecoration(hintText: 'Optional'),
            ),
          ),
          SectionCard(
            title: 'GPS & time (automatic)',
            icon: Icons.my_location,
            child: AutoCapture(onChanged: (gps, _) => _gps = gps),
          ),
        ],
      ),
      bottomNavigationBar: SubmitBar(
        label: _handover ? 'Confirm handover' : 'Save pickup proof',
        icon: _handover ? Icons.handshake_outlined : Icons.add_a_photo_outlined,
        busy: _busy,
        onPressed: _submit,
      ),
    );
  }
}
