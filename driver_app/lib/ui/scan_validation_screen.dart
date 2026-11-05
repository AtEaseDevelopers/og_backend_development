import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:provider/provider.dart';

import '../core/location.dart';
import '../models/models.dart';
import '../state/app_state.dart';
import '../state/sync_service.dart';
import 'scanner_page.dart';
import 'widgets/common.dart';

class _ScanResult {
  _ScanResult(this.code, this.result, this.label, this.message, {this.taskId});

  final String code;

  /// matched | extra | unauthorized | unverified (offline, not on this job sheet)
  final String result;
  final String label;
  final String message;
  final int? taskId;
}

/// Items 16–21: scan every CSN / DO barcode of the load. Missing, extra and unauthorized consignments are shown;
/// the result is recorded even when incomplete and the driver may continue.
class ScanValidationScreen extends StatefulWidget {
  const ScanValidationScreen({super.key, required this.sheet});

  final JobSheet sheet;

  @override
  State<ScanValidationScreen> createState() => _ScanValidationScreenState();
}

class _ScanValidationScreenState extends State<ScanValidationScreen> {
  final _controller = MobileScannerController();
  final List<_ScanResult> _results = [];
  final Map<String, DateTime> _recent = {};
  bool _submitting = false;

  List<DriverTask> get _expected =>
      widget.sheet.tasks.where((t) => t.status != 'cancelled' && t.status != 'transferred').toList();

  Set<int> get _matchedIds => _results.where((r) => r.result == 'matched').map((r) => r.taskId!).toSet();

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  Future<void> _onCode(String raw) async {
    final code = raw.trim();
    if (code.isEmpty) return;
    final last = _recent[code];
    if (last != null && DateTime.now().difference(last) < const Duration(seconds: 3)) return;
    _recent[code] = DateTime.now();

    if (_results.any((r) => r.code.toLowerCase() == code.toLowerCase())) {
      showMessage(context, 'Already scanned: $code');
      return;
    }

    final needle = code.toLowerCase();
    final task = _expected.where((t) => t.scanCodes.any((c) => c.toLowerCase() == needle)).firstOrNull;

    _ScanResult result;
    if (task != null) {
      if (_matchedIds.contains(task.id)) {
        showMessage(context, '${task.displayRef} already validated');
        return;
      }
      result = _ScanResult(code, 'matched', task.displayRef, 'On this job sheet', taskId: task.id);
      HapticFeedback.lightImpact();
    } else {
      result = await _classifyRemote(code);
      HapticFeedback.heavyImpact();
      if (mounted) {
        showMessage(
          context,
          result.result == 'unauthorized' ? 'UNAUTHORIZED: ${result.message}' : '${result.label}: ${result.message}',
          error: true,
        );
      }
    }
    if (mounted) setState(() => _results.insert(0, result));
  }

  Future<_ScanResult> _classifyRemote(String code) async {
    try {
      final res = await context.read<AppState>().api.post('/job-sheets/${widget.sheet.id}/scan-check', {'code': code});
      final d = Json.from(res['data'] as Map);
      return _ScanResult(code, d['result'] as String, d['label']?.toString() ?? code, d['message']?.toString() ?? '');
    } catch (_) {
      return _ScanResult(code, 'unverified', code, 'Not on this job sheet (will be checked when online)');
    }
  }

  Future<void> _finish() async {
    final missing = _expected.where((t) => !_matchedIds.contains(t.id)).toList();
    final extra = _results.where((r) => r.result == 'extra' || r.result == 'unverified').toList();
    final unauthorized = _results.where((r) => r.result == 'unauthorized').toList();
    final complete = missing.isEmpty && extra.isEmpty && unauthorized.isEmpty;

    if (!complete) {
      final go = await showDialog<bool>(
        context: context,
        builder: (ctx) => AlertDialog(
          icon: Icon(Icons.warning_amber_rounded, color: Colors.orange.shade800, size: 36),
          title: const Text('Validation incomplete'),
          content: SingleChildScrollView(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                if (missing.isNotEmpty) ...[
                  Text('Missing (${missing.length})', style: const TextStyle(fontWeight: FontWeight.w700)),
                  for (final t in missing) Text('• ${t.displayRef}'),
                  const SizedBox(height: 8),
                ],
                if (extra.isNotEmpty) ...[
                  Text('Not on this job sheet (${extra.length})', style: const TextStyle(fontWeight: FontWeight.w700)),
                  for (final r in extra) Text('• ${r.label}'),
                  const SizedBox(height: 8),
                ],
                if (unauthorized.isNotEmpty) ...[
                  Text(
                    'Unauthorized (${unauthorized.length})',
                    style: TextStyle(fontWeight: FontWeight.w700, color: Colors.red.shade700),
                  ),
                  for (final r in unauthorized) Text('• ${r.code}'),
                  const SizedBox(height: 8),
                ],
                const Text(
                  'The incomplete validation will be recorded for Admin. You can still continue with delivery.',
                ),
              ],
            ),
          ),
          actions: [
            TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Keep scanning')),
            FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Continue anyway')),
          ],
        ),
      );
      if (go != true) return;
    }

    setState(() => _submitting = true);
    final gps = await LocationService.current();
    if (!mounted) return;
    await context.read<SyncService>().enqueue(
      kind: 'scan_validation',
      endpoint: '/job-sheets/${widget.sheet.id}/scan-validation',
      jobSheetId: widget.sheet.id,
      label: 'Barcode validation · ${widget.sheet.number}${complete ? '' : ' (incomplete)'}',
      payload: {
        'scanned_codes': _results.map((r) => r.code).toList(),
        'continue_incomplete': !complete,
        'validated_at': DateTime.now().toUtc().toIso8601String(),
        'latitude': gps?.latitude,
        'longitude': gps?.longitude,
        '_complete': complete,
      },
    );
    if (!mounted) return;
    showMessage(
      context,
      complete ? 'All consignments validated.' : 'Incomplete validation recorded. You may continue.',
    );
    Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);
    final matched = _matchedIds;
    final issues = _results.where((r) => r.result != 'matched').toList();

    return Scaffold(
      appBar: AppBar(
        title: Text('Validate load (${matched.length}/${_expected.length})'),
        actions: [
          IconButton(
            onPressed: () async {
              final code = await promptManualCode(context);
              if (code != null) _onCode(code);
            },
            icon: const Icon(Icons.keyboard),
            tooltip: 'Enter code',
          ),
        ],
      ),
      body: Column(
        children: [
          SizedBox(
            height: 240,
            child: Stack(
              children: [
                MobileScanner(
                  controller: _controller,
                  onDetect: (capture) {
                    for (final b in capture.barcodes) {
                      if (b.rawValue != null) _onCode(b.rawValue!);
                    }
                  },
                ),
                const ScanFrame(),
              ],
            ),
          ),
          LinearProgressIndicator(value: _expected.isEmpty ? 1 : matched.length / _expected.length, minHeight: 6),
          Expanded(
            child: ListView(
              padding: const EdgeInsets.only(bottom: 16),
              children: [
                if (issues.isNotEmpty) ...[
                  Padding(
                    padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                    child: Text(
                      'Problems',
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: Colors.red.shade700,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  for (final r in issues)
                    ListTile(
                      dense: true,
                      leading: Icon(
                        r.result == 'unauthorized' ? Icons.gpp_bad_outlined : Icons.playlist_remove,
                        color: r.result == 'unauthorized' ? Colors.red.shade700 : Colors.orange.shade800,
                      ),
                      title: Text(r.label),
                      subtitle: Text('${r.result == 'unauthorized' ? 'Unauthorized' : 'Extra'} · ${r.message}'),
                    ),
                ],
                Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 4),
                  child: Text(
                    'Expected consignments',
                    style: theme.textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
                  ),
                ),
                for (final t in _expected)
                  ListTile(
                    dense: true,
                    leading: Icon(
                      matched.contains(t.id) ? Icons.check_circle : Icons.radio_button_unchecked,
                      color: matched.contains(t.id) ? Colors.green.shade700 : theme.hintColor,
                    ),
                    title: Text(t.displayRef),
                    subtitle: Text(
                      '${t.consigneeName ?? ''} · ${t.itemSummary}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    trailing: matched.contains(t.id)
                        ? null
                        : Text('Missing', style: TextStyle(color: Colors.orange.shade800, fontSize: 12)),
                  ),
              ],
            ),
          ),
        ],
      ),
      bottomNavigationBar: SubmitBar(
        label: 'Finish validation',
        icon: Icons.fact_check_outlined,
        busy: _submitting,
        onPressed: _finish,
      ),
    );
  }
}
