import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api_client.dart';
import '../../core/location.dart';
import '../../models/models.dart';
import '../../state/sync_service.dart';

void showMessage(BuildContext context, String message, {bool error = false}) {
  ScaffoldMessenger.of(context)
    ..hideCurrentSnackBar()
    ..showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: error ? Theme.of(context).colorScheme.error : null,
        behavior: SnackBarBehavior.floating,
      ),
    );
}

String errorText(Object e) => e is ApiException ? e.message : e.toString();

class SectionCard extends StatelessWidget {
  const SectionCard({super.key, required this.title, required this.child, this.trailing, this.icon});

  final String title;
  final Widget child;
  final Widget? trailing;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Card(
      margin: const EdgeInsets.fromLTRB(12, 6, 12, 6),
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                if (icon != null) ...[Icon(icon, size: 18, color: theme.colorScheme.primary), const SizedBox(width: 6)],
                Expanded(
                  child: Text(title, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
                ),
                ?trailing,
              ],
            ),
            const SizedBox(height: 10),
            child,
          ],
        ),
      ),
    );
  }
}

class InfoRow extends StatelessWidget {
  const InfoRow(this.label, this.value, {super.key, this.highlight = false});

  final String label;
  final String? value;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 3),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 120,
            child: Text(label, style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor)),
          ),
          Expanded(
            child: Text(
              (value == null || value!.isEmpty) ? '—' : value!,
              style: theme.textTheme.bodyMedium?.copyWith(
                fontWeight: highlight ? FontWeight.w700 : FontWeight.w500,
                color: highlight ? theme.colorScheme.primary : null,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, required this.color, this.icon});

  final String text;
  final Color color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
      decoration: BoxDecoration(color: color.withValues(alpha: 0.12), borderRadius: BorderRadius.circular(20)),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[Icon(icon, size: 13, color: color), const SizedBox(width: 4)],
          Text(
            text,
            style: TextStyle(color: color, fontSize: 11.5, fontWeight: FontWeight.w700),
          ),
        ],
      ),
    );
  }
}

Color statusColor(String status) => switch (status) {
  'delivered' || 'completed' => Colors.green.shade700,
  'failed' => Colors.red.shade700,
  'in_transit' => Colors.blue.shade700,
  'reassigned' => Colors.deepPurple,
  'transferred' || 'cancelled' => Colors.grey.shade600,
  _ => Colors.orange.shade800,
};

class StatusPill extends StatelessWidget {
  const StatusPill(this.status, this.label, {super.key});

  final String status;
  final String label;

  @override
  Widget build(BuildContext context) => Pill(label, color: statusColor(status));
}

class KindPill extends StatelessWidget {
  const KindPill(this.task, {super.key});

  final DriverTask task;

  @override
  Widget build(BuildContext context) {
    final (color, icon) = switch (task.kind) {
      'subsheet_transfer' => (Colors.indigo, Icons.swap_horiz),
      'break_bulk_continuation' => (Colors.brown, Icons.call_split),
      'additional_pickup' => (Colors.teal, Icons.handshake_outlined),
      'redelivery_duplicate' => (Colors.pink, Icons.content_copy),
      'reassigned' => (Colors.deepPurple, Icons.reply_all),
      _ => (Colors.blueGrey, Icons.local_shipping_outlined),
    };
    return Pill(task.kindLabel, color: color, icon: icon);
  }
}

class SyncPill extends StatelessWidget {
  const SyncPill(this.status, {super.key});

  final SyncStatus status;

  @override
  Widget build(BuildContext context) => switch (status) {
    SyncStatus.pending => Pill('Pending sync', color: Colors.orange.shade800, icon: Icons.schedule),
    SyncStatus.syncing => Pill('Syncing', color: Colors.blue.shade700, icon: Icons.sync),
    SyncStatus.synced => Pill('Synced', color: Colors.green.shade700, icon: Icons.cloud_done_outlined),
    SyncStatus.failed => Pill('Sync failed', color: Colors.red.shade700, icon: Icons.error_outline),
  };
}

/// GPS and time are captured automatically when the form opens (items 29, 30, 43, 44, 50, 51).
class AutoCapture extends StatefulWidget {
  const AutoCapture({super.key, required this.onChanged});

  final void Function(GeoPoint? gps, DateTime at) onChanged;

  @override
  State<AutoCapture> createState() => _AutoCaptureState();
}

class _AutoCaptureState extends State<AutoCapture> {
  GeoPoint? _gps;
  DateTime _at = DateTime.now();
  bool _locating = true;
  Timer? _clock;

  @override
  void initState() {
    super.initState();
    _locate();
    _clock = Timer.periodic(const Duration(seconds: 30), (_) => setState(() => _at = DateTime.now()));
  }

  @override
  void dispose() {
    _clock?.cancel();
    super.dispose();
  }

  Future<void> _locate() async {
    setState(() => _locating = true);
    final gps = await LocationService.current();
    if (!mounted) return;
    setState(() {
      _gps = gps;
      _at = DateTime.now();
      _locating = false;
    });
    widget.onChanged(gps, _at);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Container(
      padding: const EdgeInsets.all(10),
      decoration: BoxDecoration(
        color: theme.colorScheme.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(10),
      ),
      child: Row(
        children: [
          Icon(
            _gps == null ? Icons.location_off_outlined : Icons.my_location,
            size: 20,
            color: theme.colorScheme.primary,
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  _locating ? 'Getting GPS location…' : (_gps?.label ?? 'GPS unavailable — enable location services'),
                  style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                ),
                Text('Recorded at ${formatDateTime(_at)}', style: theme.textTheme.bodySmall),
              ],
            ),
          ),
          IconButton(onPressed: _locating ? null : _locate, icon: const Icon(Icons.refresh), tooltip: 'Refresh GPS'),
        ],
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState({super.key, required this.icon, required this.title, this.message, this.action});

  final IconData icon;
  final String title;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 56, color: theme.hintColor),
            const SizedBox(height: 12),
            Text(title, style: theme.textTheme.titleMedium, textAlign: TextAlign.center),
            if (message != null) ...[
              const SizedBox(height: 6),
              Text(
                message!,
                style: theme.textTheme.bodyMedium?.copyWith(color: theme.hintColor),
                textAlign: TextAlign.center,
              ),
            ],
            if (action != null) ...[const SizedBox(height: 16), action!],
          ],
        ),
      ),
    );
  }
}

class SubmitBar extends StatelessWidget {
  const SubmitBar({super.key, required this.label, required this.onPressed, this.busy = false, this.color, this.icon});

  final String label;
  final VoidCallback? onPressed;
  final bool busy;
  final Color? color;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      minimum: const EdgeInsets.fromLTRB(16, 8, 16, 12),
      child: SizedBox(
        height: 52,
        child: FilledButton.icon(
          style: color == null ? null : FilledButton.styleFrom(backgroundColor: color),
          onPressed: busy ? null : onPressed,
          icon: busy
              ? const SizedBox(
                  width: 18,
                  height: 18,
                  child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                )
              : Icon(icon ?? Icons.check),
          label: Text(label, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
        ),
      ),
    );
  }
}
