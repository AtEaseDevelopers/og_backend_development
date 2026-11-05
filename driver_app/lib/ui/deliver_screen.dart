import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:signature/signature.dart';

import '../core/location.dart';
import '../core/media.dart';
import '../models/models.dart';
import '../state/app_state.dart';
import '../state/sync_service.dart';
import 'widgets/common.dart';
import 'widgets/photo_field.dart';

class DeliverScreen extends StatefulWidget {
  const DeliverScreen({super.key, required this.task});

  final DriverTask task;

  @override
  State<DeliverScreen> createState() => _DeliverScreenState();
}

class _DeliverScreenState extends State<DeliverScreen> {
  final _form = GlobalKey<FormState>();
  final _recipient = TextEditingController();
  final _remarks = TextEditingController();
  late final TextEditingController _cod = TextEditingController(text: widget.task.codAmount?.toStringAsFixed(2) ?? '');
  final _signature = SignatureController(
    penStrokeWidth: 3,
    penColor: Colors.black,
    exportBackgroundColor: Colors.white,
  );
  List<String> _photos = [];
  List<String> _pod = [];
  String? _codMethod;
  GeoPoint? _gps;
  DateTime _at = DateTime.now();
  bool _busy = false;

  @override
  void dispose() {
    _signature.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_form.currentState!.validate()) return;
    if (_photos.isEmpty) return showMessage(context, 'Take at least one delivery photo.', error: true);
    if (_signature.isEmpty && _pod.isEmpty) {
      return showMessage(context, 'Capture the recipient signature or upload the signed POD.', error: true);
    }

    setState(() => _busy = true);
    try {
      final files = <OutboxFile>[
        for (final p in _photos) OutboxFile(field: 'photo_paths', kind: 'delivery_photo', localPath: p),
        for (final (i, p) in _pod.indexed)
          OutboxFile(field: i == 0 ? 'pod_document_path' : 'photo_paths', kind: 'pod_document', localPath: p),
      ];
      if (_signature.isNotEmpty) {
        final png = await _signature.toPngBytes();
        if (png != null) {
          files.add(OutboxFile(field: 'signature_path', kind: 'signature', localPath: await MediaStore.saveBytes(png)));
        }
      }

      final deliveredAt = DateTime.now();
      final gps = _gps ?? await LocationService.current();
      if (!mounted) return;

      await context.read<SyncService>().enqueue(
        kind: 'complete',
        endpoint: '/deliveries/${widget.task.id}/complete',
        deliveryOrderId: widget.task.id,
        label: 'Delivered · ${widget.task.displayRef}',
        files: files,
        payload: {
          'recipient_name': _recipient.text.trim(),
          'remarks': _remarks.text.trim().isEmpty ? null : _remarks.text.trim(),
          'latitude': gps?.latitude,
          'longitude': gps?.longitude,
          'delivered_at': deliveredAt.toUtc().toIso8601String(),
          if (widget.task.isCod) 'cod_amount_collected': double.parse(_cod.text.trim()),
          if (widget.task.isCod) 'cod_payment_method': _codMethod,
        },
      );
      if (!mounted) return;
      final online = context.read<SyncService>().online;
      showMessage(
        context,
        online ? 'Delivery recorded. Syncing…' : 'Saved offline. It will sync when you are back online.',
      );
      Navigator.of(context)
        ..pop()
        ..pop();
    } catch (e) {
      if (mounted) showMessage(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final task = widget.task;
    final meta = context.watch<AppState>().meta;

    return Scaffold(
      appBar: AppBar(title: const Text('Deliver')),
      body: Form(
        key: _form,
        child: SingleChildScrollView(
          padding: const EdgeInsets.only(bottom: 16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SectionCard(
                title: task.displayRef,
                icon: Icons.inventory_2_outlined,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    InfoRow('Consignee', task.consigneeName),
                    InfoRow('Address', task.address),
                    InfoRow('Items', task.itemSummary),
                  ],
                ),
              ),
              SectionCard(
                title: 'Delivery-location photos *',
                icon: Icons.photo_camera_outlined,
                child: PhotoField(photos: _photos, onChanged: (v) => setState(() => _photos = v)),
              ),
              SectionCard(
                title: 'Recipient',
                icon: Icons.person_outline,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextFormField(
                      controller: _recipient,
                      textCapitalization: TextCapitalization.words,
                      decoration: const InputDecoration(labelText: 'Recipient name *'),
                      validator: (v) => (v == null || v.trim().isEmpty) ? 'Enter the recipient name' : null,
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        const Expanded(
                          child: Text('Recipient signature', style: TextStyle(fontWeight: FontWeight.w600)),
                        ),
                        TextButton.icon(
                          onPressed: _signature.clear,
                          icon: const Icon(Icons.clear, size: 18),
                          label: const Text('Clear'),
                        ),
                      ],
                    ),
                    Container(
                      decoration: BoxDecoration(
                        border: Border.all(color: Colors.grey.shade400),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      clipBehavior: Clip.antiAlias,
                      child: Signature(controller: _signature, height: 180, backgroundColor: Colors.white),
                    ),
                  ],
                ),
              ),
              SectionCard(
                title: 'Signed & stamped CSN / POD',
                icon: Icons.description_outlined,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Photograph the signed and stamped CSN or supporting POD document. Blurred photos are flagged.',
                    ),
                    const SizedBox(height: 8),
                    PhotoField(
                      photos: _pod,
                      onChanged: (v) => setState(() => _pod = v),
                      blurCheck: true,
                      allowGallery: true,
                      max: 3,
                      addLabel: 'Photograph POD',
                    ),
                  ],
                ),
              ),
              if (task.isCod)
                SectionCard(
                  title: 'COD collection',
                  icon: Icons.payments_outlined,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      InfoRow('Amount due', formatMoney(task.codAmount), highlight: true),
                      const SizedBox(height: 8),
                      TextFormField(
                        controller: _cod,
                        keyboardType: const TextInputType.numberWithOptions(decimal: true),
                        decoration: const InputDecoration(labelText: 'Amount collected (RM) *'),
                        validator: (v) {
                          final amount = double.tryParse(v?.trim() ?? '');
                          if (amount == null) return 'Enter the amount collected';
                          if (amount + 0.0001 < (task.codAmount ?? 0)) {
                            return 'Collect the full COD amount (${formatMoney(task.codAmount)})';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<String>(
                        initialValue: _codMethod,
                        decoration: const InputDecoration(labelText: 'Payment method *'),
                        items: [
                          for (final o in meta?.codMethods ?? const <Option>[])
                            DropdownMenuItem(value: o.value, child: Text(o.label)),
                        ],
                        onChanged: (v) => setState(() => _codMethod = v),
                        validator: (v) => v == null ? 'Select the payment method' : null,
                      ),
                    ],
                  ),
                ),
              SectionCard(
                title: 'Remarks',
                icon: Icons.notes,
                child: TextFormField(
                  controller: _remarks,
                  maxLines: 3,
                  decoration: const InputDecoration(hintText: 'Optional delivery remarks'),
                ),
              ),
              SectionCard(
                title: 'GPS & time (automatic)',
                icon: Icons.my_location,
                child: AutoCapture(
                  onChanged: (gps, at) {
                    _gps = gps;
                    _at = at;
                  },
                ),
              ),
              Padding(
                padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Text(
                  'Delivery time is stamped when you submit (${formatDateTime(_at)} now).',
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ),
            ],
          ),
        ),
      ),
      bottomNavigationBar: SubmitBar(label: 'Mark as delivered', icon: Icons.task_alt, busy: _busy, onPressed: _submit),
    );
  }
}
