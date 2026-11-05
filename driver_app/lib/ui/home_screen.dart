import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../state/app_state.dart';
import '../state/sync_service.dart';
import 'records_screens.dart';
import 'scan_validation_screen.dart';
import 'scanner_page.dart';
import 'sync_screen.dart';
import 'task_detail_screen.dart';
import 'widgets/common.dart';

class HomeScreen extends StatelessWidget {
  const HomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final sync = context.watch<SyncService>();
    final sheet = app.current;

    return Scaffold(
      appBar: AppBar(
        title: const Text('My Job Sheet'),
        actions: [
          _SyncButton(sync: sync),
          IconButton(
            onPressed: app.loading ? null : () => app.refresh(),
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh',
          ),
        ],
      ),
      drawer: const _AppDrawer(),
      body: RefreshIndicator(
        onRefresh: () => app.refresh(),
        child: ListView(
          padding: const EdgeInsets.only(bottom: 32),
          children: [
            if (app.sessionExpired)
              const _Banner(
                icon: Icons.lock_clock,
                color: Colors.red,
                text: 'Session expired. Log out and log in again — unsynced records stay on this phone.',
              ),
            if (!sync.online || (app.fromCache && app.error != null))
              _Banner(
                icon: Icons.cloud_off,
                color: Colors.orange.shade800,
                text: app.error ?? 'Offline. Records are saved on this phone and sync automatically.',
              ),
            if (app.loading && app.jobSheets.isEmpty)
              const Padding(
                padding: EdgeInsets.all(48),
                child: Center(child: CircularProgressIndicator()),
              ),
            if (!app.loading && sheet == null)
              const EmptyState(
                icon: Icons.event_busy,
                title: 'No job sheet today',
                message: 'Pull down to refresh after Admin assigns your trip.',
              ),
            if (app.jobSheets.length > 1) _TripSelector(app: app),
            if (sheet != null) ...[
              _SheetHeader(sheet: sheet),
              if (sheet.isDraft) _CheckInCard(sheet: sheet),
              if (!sheet.isDraft) _ValidationCard(sheet: sheet),
              if (sheet.isDraft)
                SectionCard(
                  title: '${sheet.tasks.length} task(s) assigned',
                  icon: Icons.lock_outline,
                  child: const Text('Check in to start the trip and retrieve your delivery tasks.'),
                )
              else
                ..._routeGroups(context, sheet, app),
            ],
          ],
        ),
      ),
    );
  }

  List<Widget> _routeGroups(BuildContext context, JobSheet sheet, AppState app) {
    final groups = sheet.byRoute;
    if (groups.isEmpty) {
      return [const EmptyState(icon: Icons.inbox_outlined, title: 'No tasks on this trip')];
    }
    return [
      for (final entry in groups.entries) ...[
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 14, 16, 4),
          child: Row(
            children: [
              Icon(Icons.alt_route, size: 18, color: Theme.of(context).colorScheme.primary),
              const SizedBox(width: 6),
              Text(
                'Route: ${entry.key}',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
              const Spacer(),
              Text('${entry.value.length} task(s)', style: Theme.of(context).textTheme.bodySmall),
            ],
          ),
        ),
        for (final task in entry.value) _TaskTile(task: task, sheet: sheet, progress: app.progressOf(task)),
      ],
    ];
  }
}

class _SyncButton extends StatelessWidget {
  const _SyncButton({required this.sync});

  final SyncService sync;

  @override
  Widget build(BuildContext context) {
    final count = sync.pendingCount;
    final failed = sync.failedCount;
    return IconButton(
      tooltip: 'Sync status',
      onPressed: () => Navigator.push(context, MaterialPageRoute(builder: (_) => const SyncScreen())),
      icon: Badge(
        isLabelVisible: count + failed > 0,
        backgroundColor: failed > 0 ? Colors.red : Colors.orange,
        label: Text('${count + failed}'),
        child: Icon(
          !sync.online ? Icons.cloud_off : (count > 0 ? Icons.cloud_upload_outlined : Icons.cloud_done_outlined),
        ),
      ),
    );
  }
}

class _Banner extends StatelessWidget {
  const _Banner({required this.icon, required this.color, required this.text});

  final IconData icon;
  final Color color;
  final String text;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    color: color.withValues(alpha: 0.12),
    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
    child: Row(
      children: [
        Icon(icon, color: color, size: 20),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            style: TextStyle(color: color, fontWeight: FontWeight.w600),
          ),
        ),
      ],
    ),
  );
}

class _TripSelector extends StatelessWidget {
  const _TripSelector({required this.app});

  final AppState app;

  @override
  Widget build(BuildContext context) {
    final current = app.current;
    return SizedBox(
      height: 52,
      child: ListView(
        scrollDirection: Axis.horizontal,
        padding: const EdgeInsets.fromLTRB(12, 10, 12, 0),
        children: [
          for (final s in app.jobSheets)
            Padding(
              padding: const EdgeInsets.only(right: 8),
              child: ChoiceChip(
                label: Text('${s.tripLabel} · ${s.statusLabel}'),
                selected: s.id == current?.id,
                onSelected: (_) => app.selectJobSheet(s.id),
              ),
            ),
        ],
      ),
    );
  }
}

class _SheetHeader extends StatelessWidget {
  const _SheetHeader({required this.sheet});

  final JobSheet sheet;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final done = sheet.tasks.where((t) => !t.isOpen).length;
    return Card(
      margin: const EdgeInsets.fromLTRB(12, 12, 12, 6),
      color: theme.colorScheme.primary,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: DefaultTextStyle(
          style: const TextStyle(color: Colors.white),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      sheet.number,
                      style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800, color: Colors.white),
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(color: Colors.white, borderRadius: BorderRadius.circular(20)),
                    child: Text(
                      sheet.statusLabel,
                      style: TextStyle(color: statusColor(sheet.status), fontWeight: FontWeight.w800, fontSize: 12),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 6),
              Text(
                '${sheet.tripLabel} · ${sheet.operatingDate ?? ''}${sheet.isSharedDispatch ? ' · Shared dispatch' : ''}',
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  const Icon(Icons.local_shipping_outlined, color: Colors.white70, size: 18),
                  const SizedBox(width: 6),
                  Text(sheet.lorryNo, style: const TextStyle(fontWeight: FontWeight.w700)),
                  const SizedBox(width: 16),
                  const Icon(Icons.apartment_outlined, color: Colors.white70, size: 18),
                  const SizedBox(width: 6),
                  Expanded(child: Text(sheet.operatingBranch ?? '—', overflow: TextOverflow.ellipsis)),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                sheet.checkedInAt == null
                    ? 'Not checked in'
                    : 'Checked in ${formatDateTime(sheet.checkedInAt)} · $done of ${sheet.tasks.length} task(s) closed',
                style: const TextStyle(color: Colors.white70),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CheckInCard extends StatefulWidget {
  const _CheckInCard({required this.sheet});

  final JobSheet sheet;

  @override
  State<_CheckInCard> createState() => _CheckInCardState();
}

class _CheckInCardState extends State<_CheckInCard> {
  bool _busy = false;

  Future<void> _checkIn() async {
    final ok = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Confirm lorry'),
        content: Text(
          'You are checking in to ${widget.sheet.number} with lorry ${widget.sheet.lorryNo} '
          '(${widget.sheet.operatingBranch ?? 'operating branch'}). Your time and location will be recorded.',
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Not my lorry')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Check in')),
        ],
      ),
    );
    if (ok == false && mounted) {
      showMessage(context, 'Contact Admin to correct the lorry assignment before starting.', error: true);
    }
    if (ok != true || !mounted) return;

    setState(() => _busy = true);
    try {
      final message = await context.read<AppState>().checkIn(widget.sheet);
      if (mounted) showMessage(context, message);
    } catch (e) {
      if (mounted) showMessage(context, errorText(e), error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return SectionCard(
      title: 'Lorry check-in',
      icon: Icons.how_to_reg_outlined,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Check in before departure. The system validates your lorry and its operating branch, records the time and GPS location, and moves the job sheet to In Transit.',
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 48,
            child: FilledButton.icon(
              onPressed: _busy ? null : _checkIn,
              icon: _busy
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                    )
                  : const Icon(Icons.login),
              label: const Text('Check in now', style: TextStyle(fontWeight: FontWeight.w700)),
            ),
          ),
        ],
      ),
    );
  }
}

class _ValidationCard extends StatelessWidget {
  const _ValidationCard({required this.sheet});

  final JobSheet sheet;

  @override
  Widget build(BuildContext context) {
    final sync = context.watch<SyncService>();
    final local = sync.latestForJobSheet(sheet.id, 'scan_validation');
    final server = sheet.scanValidation;
    final bool complete = local != null
        ? (local.payload['_complete'] == true)
        : (server != null && server['is_complete'] == true);
    final validated = local != null || server != null;

    final color = !validated ? Colors.orange.shade800 : (complete ? Colors.green.shade700 : Colors.amber.shade900);
    final text = !validated
        ? 'Scan the CSN / DO barcodes of your load before starting delivery.'
        : complete
        ? 'All consignments validated.'
        : 'Validation incomplete${server != null && local == null ? ' — ${server['missing_count']} missing, ${server['extra_count']} extra, ${server['unauthorized_count']} unauthorized' : ''}. Recorded for Admin.';

    return SectionCard(
      title: 'Consignment validation',
      icon: Icons.qr_code_scanner,
      trailing: local != null ? SyncPill(local.status) : null,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Icon(complete ? Icons.verified_outlined : Icons.info_outline, color: color, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  text,
                  style: TextStyle(color: color, fontWeight: FontWeight.w600),
                ),
              ),
            ],
          ),
          if (sheet.isInTransit) ...[
            const SizedBox(height: 10),
            OutlinedButton.icon(
              onPressed: () =>
                  Navigator.push(context, MaterialPageRoute(builder: (_) => ScanValidationScreen(sheet: sheet))),
              icon: const Icon(Icons.qr_code_scanner),
              label: Text(validated ? 'Scan again' : 'Start barcode validation'),
            ),
          ],
        ],
      ),
    );
  }
}

class _TaskTile extends StatelessWidget {
  const _TaskTile({required this.task, required this.sheet, required this.progress});

  final DriverTask task;
  final JobSheet sheet;
  final LocalProgress progress;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final localStatus = progress.outcome;
    final status = localStatus ?? task.status;
    final label = localStatus == null ? task.statusLabel : (localStatus == 'delivered' ? 'Delivered' : 'Failed');

    return Card(
      margin: const EdgeInsets.fromLTRB(12, 4, 12, 4),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: () => Navigator.push(
          context,
          MaterialPageRoute(
            builder: (_) => TaskDetailScreen(taskId: task.id, sheetId: sheet.id),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              CircleAvatar(
                radius: 16,
                backgroundColor: statusColor(status).withValues(alpha: 0.15),
                child: Text(
                  '${task.sequence ?? '-'}',
                  style: TextStyle(color: statusColor(status), fontWeight: FontWeight.w800),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(task.displayRef, style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800)),
                    const SizedBox(height: 4),
                    Wrap(
                      spacing: 6,
                      runSpacing: 4,
                      children: [
                        StatusPill(status, label),
                        if (task.kind != 'delivery') KindPill(task),
                        if (progress.outcomeEntry != null) SyncPill(progress.outcomeEntry!.status),
                        if (task.isCod)
                          Pill(
                            'COD ${formatMoney(task.codAmount)}',
                            color: Colors.green.shade800,
                            icon: Icons.payments_outlined,
                          ),
                        if (task.claimPending) Pill('Scan to claim', color: Colors.red.shade700, icon: Icons.qr_code),
                        if (task.hasActiveBreakBulk) Pill('Break-Bulk', color: Colors.brown, icon: Icons.call_split),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      task.consigneeName ?? task.customerName ?? '—',
                      style: theme.textTheme.bodyMedium?.copyWith(fontWeight: FontWeight.w600),
                    ),
                    Text(task.address, style: theme.textTheme.bodySmall, maxLines: 2, overflow: TextOverflow.ellipsis),
                    if (task.itemSummary.isNotEmpty)
                      Text(
                        task.itemSummary,
                        style: theme.textTheme.bodySmall?.copyWith(color: theme.hintColor),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    if (task.sourceBranch != null || task.transferCodeLabel != null)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: Text(
                          [
                            if (task.sourceBranch != null) 'From ${task.sourceBranch}',
                            if (task.transferCodeLabel != null)
                              'Transfer ${task.transferCodeLabel} → ${task.destinationBranch ?? '—'}',
                          ].join(' · '),
                          style: theme.textTheme.bodySmall?.copyWith(color: theme.colorScheme.primary),
                        ),
                      ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}

class _AppDrawer extends StatelessWidget {
  const _AppDrawer();

  Future<void> _claim(BuildContext context) async {
    Navigator.pop(context);
    final code = await Navigator.push<String>(
      context,
      MaterialPageRoute(builder: (_) => const ScannerPage(title: 'Scan CSN to claim')),
    );
    if (code == null || !context.mounted) return;
    final app = context.read<AppState>();
    try {
      final res = await app.api.post('/csn/claim', {'qr_token': code});
      await app.refresh(silent: true);
      if (context.mounted) showMessage(context, res['message']?.toString() ?? 'CSN claimed.');
    } catch (e) {
      if (context.mounted) showMessage(context, errorText(e), error: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    final app = context.watch<AppState>();
    final driver = app.meta?.driver ?? {};
    return Drawer(
      child: ListView(
        padding: EdgeInsets.zero,
        children: [
          UserAccountsDrawerHeader(
            accountName: Text(driver['name']?.toString() ?? app.user?['name']?.toString() ?? 'Driver'),
            accountEmail: Text('${app.user?['email'] ?? ''}${driver['code'] != null ? ' · ${driver['code']}' : ''}'),
            currentAccountPicture: const CircleAvatar(child: Icon(Icons.person)),
          ),
          ListTile(
            leading: const Icon(Icons.assignment_outlined),
            title: const Text('My Job Sheet'),
            onTap: () => Navigator.pop(context),
          ),
          ListTile(
            leading: const Icon(Icons.cloud_sync_outlined),
            title: const Text('Sync status'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (_) => const SyncScreen()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.call_split),
            title: const Text('Break-Bulk requests'),
            subtitle: const Text('Replacement driver / lorry'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (_) => const BreakBulksScreen()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.report_gmailerrorred_outlined),
            title: const Text('Failed deliveries'),
            subtitle: const Text('Reassigned / duplicated tasks'),
            onTap: () {
              Navigator.pop(context);
              Navigator.push(context, MaterialPageRoute(builder: (_) => const FailuresScreen()));
            },
          ),
          ListTile(
            leading: const Icon(Icons.qr_code_2),
            title: const Text('Scan CSN to claim'),
            subtitle: const Text('Transferred jobs'),
            onTap: () => _claim(context),
          ),
          const Divider(),
          ListTile(
            leading: const Icon(Icons.logout),
            title: const Text('Log out'),
            onTap: () async {
              try {
                Navigator.pop(context);
                await context.read<AppState>().logout();
              } catch (e) {
                if (context.mounted) showMessage(context, errorText(e), error: true);
              }
            },
          ),
        ],
      ),
    );
  }
}
