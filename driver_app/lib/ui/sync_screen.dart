import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../state/sync_service.dart';
import 'widgets/common.dart';

/// Item 60: every record captured on the phone with its sync status (pending, synced or failed).
class SyncScreen extends StatelessWidget {
  const SyncScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final sync = context.watch<SyncService>();
    final entries = sync.entries;

    return Scaffold(
      appBar: AppBar(title: const Text('Sync status')),
      body: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(16),
            color: Colors.white,
            child: Row(
              children: [
                Icon(
                  sync.online ? Icons.wifi : Icons.wifi_off,
                  color: sync.online ? Colors.green.shade700 : Colors.orange.shade800,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        sync.online ? 'Online' : 'Offline — records are kept on this phone',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                      Text(
                        '${sync.pendingCount} pending · ${sync.failedCount} failed · last attempt ${formatDateTime(sync.lastSyncAt)}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                FilledButton.tonalIcon(
                  onPressed: () => sync.syncNow(),
                  icon: const Icon(Icons.sync),
                  label: const Text('Sync now'),
                ),
              ],
            ),
          ),
          Expanded(
            child: entries.isEmpty
                ? const EmptyState(icon: Icons.cloud_done_outlined, title: 'Nothing recorded yet')
                : ListView.separated(
                    itemCount: entries.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (_, i) => _EntryTile(entry: entries[i]),
                  ),
          ),
        ],
      ),
    );
  }
}

class _EntryTile extends StatelessWidget {
  const _EntryTile({required this.entry});

  final OutboxEntry entry;

  IconData get _icon => switch (entry.kind) {
    'complete' => Icons.task_alt,
    'fail' => Icons.report_gmailerrorred_outlined,
    'pickup' => Icons.add_a_photo_outlined,
    'handover' => Icons.handshake_outlined,
    'break_bulk' => Icons.call_split,
    'scan_validation' => Icons.qr_code_scanner,
    _ => Icons.description_outlined,
  };

  @override
  Widget build(BuildContext context) {
    final sync = context.read<SyncService>();
    final payload = entry.payload;
    return ListTile(
      leading: Icon(_icon),
      title: Text(entry.label, style: const TextStyle(fontWeight: FontWeight.w600)),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 4),
          Wrap(
            spacing: 6,
            runSpacing: 4,
            children: [
              SyncPill(entry.status),
              if (entry.files.isNotEmpty)
                Pill('${entry.files.length} file(s)', color: Colors.blueGrey, icon: Icons.attach_file),
              if (payload['cod_amount_collected'] != null)
                Pill('COD ${formatMoney(payload['cod_amount_collected'] as num)}', color: Colors.green.shade800),
            ],
          ),
          const SizedBox(height: 4),
          Text(
            'Recorded ${formatDateTime(entry.createdAt)}'
            '${entry.syncedAt != null ? ' · synced ${formatDateTime(entry.syncedAt!.toLocal())}' : ''}'
            '${entry.attempts > 0 && entry.status != SyncStatus.synced ? ' · ${entry.attempts} attempt(s)' : ''}',
            style: Theme.of(context).textTheme.bodySmall,
          ),
          if (entry.error != null && entry.status != SyncStatus.synced)
            Text(
              entry.error!,
              style: TextStyle(
                color: entry.status == SyncStatus.failed ? Colors.red.shade700 : Colors.orange.shade800,
                fontSize: 12,
              ),
            ),
        ],
      ),
      isThreeLine: true,
      trailing: entry.status == SyncStatus.failed
          ? PopupMenuButton<String>(
              onSelected: (v) async {
                if (v == 'retry') await sync.retry(entry);
                if (v == 'discard' && context.mounted) {
                  final ok = await showDialog<bool>(
                    context: context,
                    builder: (ctx) => AlertDialog(
                      title: const Text('Discard record?'),
                      content: const Text(
                        'The record and its photos will be removed from this phone. Do this only when Admin has recorded it another way.',
                      ),
                      actions: [
                        TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep')),
                        FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Discard')),
                      ],
                    ),
                  );
                  if (ok == true) await sync.discard(entry);
                }
              },
              itemBuilder: (_) => const [
                PopupMenuItem(value: 'retry', child: Text('Retry')),
                PopupMenuItem(value: 'discard', child: Text('Discard')),
              ],
            )
          : null,
    );
  }
}
