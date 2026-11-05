import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../state/app_state.dart';
import '../state/sync_service.dart';
import 'break_bulk_screen.dart';
import 'deliver_screen.dart';
import 'fail_screen.dart';
import 'proof_screen.dart';
import 'scanner_page.dart';
import 'widgets/common.dart';

class TaskDetailScreen extends StatelessWidget {
  const TaskDetailScreen({super.key, required this.taskId, required this.sheetId});

  final int taskId;
  final int sheetId;

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    context.watch<SyncService>();
    final sheet = app.jobSheets.where((s) => s.id == sheetId).firstOrNull;
    final task = sheet?.tasks.where((t) => t.id == taskId).firstOrNull;

    if (sheet == null || task == null) {
      return Scaffold(
        appBar: AppBar(),
        body: const EmptyState(
          icon: Icons.search_off,
          title: 'Task no longer on this job sheet',
          message: 'It may have been transferred by Admin.',
        ),
      );
    }

    final progress = app.progressOf(task);
    final status = progress.outcome ?? task.status;
    final canAct = sheet.isInTransit && task.isOpen && !progress.hasOutcome && !task.claimPending;

    return Scaffold(
      appBar: AppBar(title: Text(task.doNumber)),
      body: RefreshIndicator(
        onRefresh: () => app.refresh(),
        child: ListView(
          padding: const EdgeInsets.only(bottom: 24),
          children: [
            SectionCard(
              title: task.displayRef,
              icon: Icons.inventory_2_outlined,
              trailing: StatusPill(
                status,
                progress.outcome == null
                    ? task.statusLabel
                    : (progress.outcome == 'delivered' ? 'Delivered' : 'Failed'),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: [
                      KindPill(task),
                      if (progress.outcomeEntry != null) SyncPill(progress.outcomeEntry!.status),
                    ],
                  ),
                  const SizedBox(height: 8),
                  InfoRow('Sequence', task.sequence?.toString()),
                  InfoRow('Route', task.routeGroup),
                  InfoRow('CSN no.', task.csnNumber),
                  InfoRow('DO no.', task.doNumber),
                  if (task.customerDoNumber != null) InfoRow('Customer DO', task.customerDoNumber),
                  InfoRow('Source branch', task.sourceBranch),
                  if (task.parentDoNumber != null) InfoRow('Original DO', task.parentDoNumber),
                ],
              ),
            ),
            if (progress.outcomeEntry?.status == SyncStatus.failed)
              _Notice(
                color: Colors.red.shade700,
                icon: Icons.error_outline,
                text:
                    'The server rejected this record: ${progress.outcomeEntry!.error}. Open Sync status to retry or discard it.',
              ),
            if (task.claimPending)
              _Notice(
                color: Colors.red.shade700,
                icon: Icons.qr_code,
                text: 'This job was transferred to you. Scan the CSN to claim it before updating.',
                action: FilledButton.icon(
                  onPressed: () => _claim(context, task),
                  icon: const Icon(Icons.qr_code_scanner),
                  label: const Text('Scan CSN to claim'),
                ),
              ),
            SectionCard(
              title: 'Delivery information',
              icon: Icons.place_outlined,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  InfoRow('Customer', task.customerName),
                  InfoRow('Consignee', task.consigneeName),
                  InfoRow('PIC', [task.consigneePic, task.consigneePhone].whereType<String>().join(' · ')),
                  InfoRow('Address', task.address),
                  if (task.isAdditionalPickup)
                    InfoRow('Pickup from', [task.consignorName, task.consignorAddress].whereType<String>().join(', ')),
                  InfoRow(
                    'Payment',
                    task.isCod ? 'COD' : (task.raw['billing_type']?.toString().replaceAll('_', ' ').toUpperCase()),
                  ),
                  if (task.isCod) InfoRow('COD to collect', formatMoney(task.codAmount), highlight: true),
                  if (task.remarks != null) InfoRow('Remarks', task.remarks),
                ],
              ),
            ),
            SectionCard(
              title: 'Items (${task.totalQuantity.toStringAsFixed(task.totalQuantity % 1 == 0 ? 0 : 2)})',
              icon: Icons.list_alt,
              child: Column(
                children: [
                  for (final item in task.items)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 3),
                      child: Row(
                        children: [
                          Expanded(child: Text(item['item_name']?.toString() ?? '—')),
                          Text(
                            '${item['quantity']} ${item['uom'] ?? ''}',
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ],
                      ),
                    ),
                  if (task.items.isEmpty) const Align(alignment: Alignment.centerLeft, child: Text('No item lines')),
                ],
              ),
            ),
            if (task.transferCode != null || task.subsheet != null)
              SectionCard(
                title: task.isAdditionalPickup ? 'Additional task (PSI)' : 'Transfer',
                icon: Icons.swap_horiz,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (task.transferCodeLabel != null)
                      InfoRow('Transfer code', task.transferCodeLabel, highlight: true),
                    if (task.transferCodeLabel != null) InfoRow('Destination', task.destinationBranch),
                    if (task.subsheet != null) ...[
                      InfoRow('Subsheet', '${task.subsheet!['number']} · ${task.subsheet!['type_label']}'),
                      InfoRow('Segment', task.subsheet!['segment_route']?.toString()),
                      InfoRow(
                        'Main driver',
                        [task.subsheet!['main_driver'], task.subsheet!['main_lorry']].whereType<Object>().join(' · '),
                      ),
                      InfoRow('Handover', task.subsheet!['handover_status']?.toString()),
                      if (task.subsheet!['notes'] != null) InfoRow('Notes', task.subsheet!['notes']?.toString()),
                    ],
                    if (task.pickupRecordedAt != null || progress.pickupEntry != null)
                      InfoRow(
                        'Pickup proof',
                        progress.pickupEntry != null
                            ? formatDateTime(progress.pickupEntry!.createdAt)
                            : formatDateTime(task.pickupRecordedAt),
                      ),
                    if (task.handoverRecordedAt != null)
                      InfoRow('Handed over', formatDateTime(task.handoverRecordedAt)),
                  ],
                ),
              ),
            if (task.breakBulk != null) _BreakBulkCard(bb: task.breakBulk!),
            if (task.failed != null)
              SectionCard(
                title: 'Failed delivery',
                icon: Icons.report_gmailerrorred_outlined,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    InfoRow('Reason', task.failed!['reason']?.toString()),
                    InfoRow('Remarks', task.failed!['remarks']?.toString()),
                    InfoRow('Reassignment', switch (task.failed!['reassignment_option']) {
                      'standard' => 'Reassigned',
                      'duplicate' => 'Duplicated & reassigned',
                      _ => 'Awaiting Admin',
                    }),
                  ],
                ),
              ),
            if (!sheet.isInTransit && task.isOpen)
              const _Notice(
                color: Colors.orange,
                icon: Icons.lock_outline,
                text: 'Check in to the trip before updating this task.',
              ),
            if (canAct) _Actions(task: task, sheet: sheet, progress: progress),
          ],
        ),
      ),
    );
  }

  Future<void> _claim(BuildContext context, DriverTask task) async {
    final code = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (_) => const ScannerPage(title: 'Scan CSN to claim')),
    );
    if (code == null || !context.mounted) return;
    final app = context.read<AppState>();
    try {
      final res = await app.api.post('/csn/claim', {'qr_token': code});
      await app.refresh(silent: true);
      if (context.mounted) showMessage(context, res['message']?.toString() ?? 'Claimed');
    } catch (e) {
      if (context.mounted) showMessage(context, errorText(e), error: true);
    }
  }
}

class _Actions extends StatelessWidget {
  const _Actions({required this.task, required this.sheet, required this.progress});

  final DriverTask task;
  final JobSheet sheet;
  final LocalProgress progress;

  void _open(BuildContext context, Widget screen) => Navigator.push(context, MaterialPageRoute(builder: (_) => screen));

  @override
  Widget build(BuildContext context) {
    final pickupDone = task.pickupRecordedAt != null || progress.pickupEntry != null;
    final breakBulkPending = task.hasActiveBreakBulk || progress.breakBulkEntry != null;

    return Padding(
      padding: const EdgeInsets.fromLTRB(12, 8, 12, 0),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          OutlinedButton.icon(
            onPressed: () => _open(context, ProofScreen(task: task, type: ProofType.pickup)),
            icon: Icon(pickupDone ? Icons.check_circle : Icons.add_a_photo_outlined),
            label: Text(
              pickupDone
                  ? 'Pickup recorded — add more'
                  : (task.isAdditionalPickup ? 'Record pickup (photos, GPS, time)' : 'Pickup-location photos'),
            ),
          ),
          const SizedBox(height: 10),
          if (task.isAdditionalPickup)
            SizedBox(
              height: 52,
              child: FilledButton.icon(
                onPressed: () => _open(context, ProofScreen(task: task, type: ProofType.handover)),
                icon: const Icon(Icons.handshake_outlined),
                label: const Text('Confirm handover to delivery driver', style: TextStyle(fontWeight: FontWeight.w700)),
              ),
            )
          else ...[
            SizedBox(
              height: 52,
              child: FilledButton.icon(
                onPressed: () => _open(context, DeliverScreen(task: task)),
                icon: const Icon(Icons.task_alt),
                label: const Text('Deliver', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w700)),
              ),
            ),
            const SizedBox(height: 10),
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(foregroundColor: Colors.red.shade700),
                    onPressed: () => _open(context, FailScreen(task: task)),
                    icon: const Icon(Icons.report_gmailerrorred_outlined),
                    label: const Text('Failed delivery'),
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(foregroundColor: Colors.brown),
                    onPressed: breakBulkPending ? null : () => _open(context, BreakBulkScreen(task: task)),
                    icon: const Icon(Icons.call_split),
                    label: Text(breakBulkPending ? 'Break-Bulk sent' : 'Break-Bulk'),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

class _BreakBulkCard extends StatelessWidget {
  const _BreakBulkCard({required this.bb});

  final Json bb;

  @override
  Widget build(BuildContext context) {
    final assigned = bb['replacement_driver'] != null || bb['replacement_lorry'] != null;
    return SectionCard(
      title: 'Break-Bulk ${bb['number'] ?? ''}',
      icon: Icons.call_split,
      trailing: Pill(
        assigned ? 'Replacement assigned' : (bb['status'] == 'active' ? 'Awaiting Admin' : bb['status'].toString()),
        color: assigned ? Colors.green.shade700 : Colors.orange.shade800,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          InfoRow('Reason', bb['reason']?.toString()),
          InfoRow('Offloaded at', bb['location']?.toString()),
          InfoRow('Original', [bb['original_driver'], bb['original_lorry']].whereType<Object>().join(' · ')),
          InfoRow('Replacement driver', bb['replacement_driver']?.toString(), highlight: assigned),
          InfoRow('Replacement lorry', bb['replacement_lorry']?.toString(), highlight: assigned),
          InfoRow('Handover', bb['handover_status']?.toString()),
        ],
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.color, required this.icon, required this.text, this.action});

  final Color color;
  final IconData icon;
  final String text;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.fromLTRB(12, 6, 12, 6),
    padding: const EdgeInsets.all(12),
    decoration: BoxDecoration(color: color.withValues(alpha: 0.1), borderRadius: BorderRadius.circular(12)),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, color: color),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                text,
                style: TextStyle(color: color, fontWeight: FontWeight.w600),
              ),
            ),
          ],
        ),
        if (action != null) ...[const SizedBox(height: 10), action!],
      ],
    ),
  );
}
