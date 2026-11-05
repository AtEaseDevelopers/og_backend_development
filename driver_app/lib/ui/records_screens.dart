import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../models/models.dart';
import '../state/app_state.dart';
import 'widgets/common.dart';

/// Loads a list from the backend, keeping the last copy for offline viewing.
class _RemoteList extends StatefulWidget {
  const _RemoteList({required this.endpoint, required this.itemBuilder, required this.empty});

  final String endpoint;
  final Widget Function(BuildContext, Json) itemBuilder;
  final String empty;

  @override
  State<_RemoteList> createState() => _RemoteListState();
}

class _RemoteListState extends State<_RemoteList> {
  List<Json>? _items;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final app = context.read<AppState>();
    final key = 'list:${widget.endpoint}';
    try {
      final res = await app.api.get(widget.endpoint);
      final list = (res['data'] as List? ?? []).map((e) => Json.from(e as Map)).toList();
      await app.db.putCache(key, list);
      if (mounted) setState(() => (_items = list, _error = null));
    } catch (e) {
      final cached = await app.db.getCache(key);
      if (mounted) {
        setState(() {
          _items = cached is List ? cached.map((e) => Json.from(e as Map)).toList() : [];
          _error = 'Offline — showing the last saved list. ${errorText(e)}';
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_items == null) return const Center(child: CircularProgressIndicator());
    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.only(bottom: 24),
        children: [
          if (_error != null)
            Padding(
              padding: const EdgeInsets.all(12),
              child: Text(
                _error!,
                style: TextStyle(color: Colors.orange.shade800, fontWeight: FontWeight.w600),
              ),
            ),
          if (_items!.isEmpty) EmptyState(icon: Icons.inbox_outlined, title: widget.empty),
          for (final item in _items!) widget.itemBuilder(context, item),
        ],
      ),
    );
  }
}

/// Item 45: the driver's Break-Bulk requests and the replacement driver / lorry assigned by Admin.
class BreakBulksScreen extends StatelessWidget {
  const BreakBulksScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Break-Bulk requests')),
      body: _RemoteList(
        endpoint: '/break-bulks',
        empty: 'No Break-Bulk requests',
        itemBuilder: (context, b) {
          final assigned = b['replacement_driver'] != null || b['replacement_lorry'] != null;
          return SectionCard(
            title: '${b['number']} · ${b['csn_number'] ?? b['do_number']}',
            icon: Icons.call_split,
            trailing: Pill(
              b['status'] == 'revoked'
                  ? 'Revoked'
                  : b['status'] == 'completed'
                  ? 'Completed'
                  : (assigned ? 'Replacement assigned' : 'Awaiting Admin'),
              color: b['status'] == 'revoked'
                  ? Colors.grey
                  : (assigned ? Colors.green.shade700 : Colors.orange.shade800),
            ),
            child: Column(
              children: [
                InfoRow('DO', b['do_number']?.toString()),
                InfoRow('Consignee', b['consignee_name']?.toString()),
                InfoRow('Reason', b['reason']?.toString()),
                InfoRow('Offloaded at', b['location']?.toString()),
                InfoRow('Submitted', formatDateTime(DateTime.tryParse(b['reported_at']?.toString() ?? '')?.toLocal())),
                InfoRow('Replacement driver', b['replacement_driver']?.toString(), highlight: assigned),
                InfoRow('Replacement lorry', b['replacement_lorry']?.toString(), highlight: assigned),
                InfoRow('Handover', b['handover_status']?.toString()),
                if (b['is_mine_to_continue'] == true)
                  InfoRow('Note', 'You are assigned to continue this delivery.', highlight: true),
              ],
            ),
          );
        },
      ),
    );
  }
}

/// Item 54: failed deliveries and the reassigned or duplicated delivery Admin created for them.
class FailuresScreen extends StatelessWidget {
  const FailuresScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Failed deliveries')),
      body: _RemoteList(
        endpoint: '/failed-deliveries',
        empty: 'No failed deliveries',
        itemBuilder: (context, f) {
          final r = f['replacement'] is Map ? Json.from(f['replacement'] as Map) : null;
          return SectionCard(
            title: '${f['csn_number'] ?? ''} / ${f['do_number'] ?? ''}',
            icon: Icons.report_gmailerrorred_outlined,
            trailing: Pill(
              f['reassignment_label']?.toString() ?? '',
              color: r == null ? Colors.orange.shade800 : Colors.deepPurple,
            ),
            child: Column(
              children: [
                InfoRow('Consignee', f['consignee_name']?.toString()),
                InfoRow('Reason', f['reason']?.toString()),
                InfoRow('Remarks', f['remarks']?.toString()),
                InfoRow('Failed at', formatDateTime(DateTime.tryParse(f['failed_at']?.toString() ?? '')?.toLocal())),
                if (r != null) ...[
                  const Divider(),
                  InfoRow('New DO', r['do_number']?.toString(), highlight: true),
                  InfoRow('Status', r['status']?.toString()),
                  InfoRow('Driver / lorry', [r['driver'], r['lorry']].whereType<Object>().join(' · ')),
                  InfoRow('Job sheet', [r['job_sheet'], r['operating_date']].whereType<Object>().join(' · ')),
                  if (r['assigned_to_me'] == true) InfoRow('Note', 'This task is on your job sheet.', highlight: true),
                ],
              ],
            ),
          );
        },
      ),
    );
  }
}
