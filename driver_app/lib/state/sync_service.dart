import 'dart:async';
import 'dart:convert';

import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter/widgets.dart';
import 'package:uuid/uuid.dart';

import '../core/api_client.dart';
import '../core/config.dart';
import '../core/local_db.dart';
import '../core/media.dart';

enum SyncStatus { pending, syncing, synced, failed }

/// A local file attached to an outbox record: uploaded first, then its server path goes into [field].
class OutboxFile {
  OutboxFile({required this.field, required this.kind, required this.localPath, String? uuid, this.remotePath})
    : uuid = uuid ?? const Uuid().v4();

  factory OutboxFile.fromJson(Map<String, dynamic> j) => OutboxFile(
    field: j['field'] as String,
    kind: j['kind'] as String,
    localPath: j['local_path'] as String,
    uuid: j['uuid'] as String,
    remotePath: j['remote_path'] as String?,
  );

  /// `photo_paths` collects into a list; `signature_path` / `pod_document_path` are single values.
  final String field;
  final String kind;
  final String localPath;
  final String uuid;
  String? remotePath;

  Map<String, dynamic> toJson() => {
    'field': field,
    'kind': kind,
    'local_path': localPath,
    'uuid': uuid,
    'remote_path': remotePath,
  };
}

class OutboxEntry {
  OutboxEntry(this.row)
    : files = (jsonDecode(row['files'] as String) as List)
          .map((f) => OutboxFile.fromJson(Map<String, dynamic>.from(f as Map)))
          .toList();

  final Map<String, dynamic> row;
  final List<OutboxFile> files;

  int get id => row['id'] as int;
  String get clientUuid => row['client_uuid'] as String;
  String get kind => row['kind'] as String;
  String get endpoint => row['endpoint'] as String;
  String get label => row['label'] as String;
  int? get deliveryOrderId => row['delivery_order_id'] as int?;
  int? get jobSheetId => row['job_sheet_id'] as int?;
  int get attempts => row['attempts'] as int;
  String? get error => row['error'] as String?;
  DateTime get createdAt => DateTime.parse(row['created_at'] as String);
  DateTime? get syncedAt => row['synced_at'] == null ? null : DateTime.parse(row['synced_at'] as String);
  Map<String, dynamic> get payload => Map<String, dynamic>.from(jsonDecode(row['payload'] as String) as Map);
  SyncStatus get status => SyncStatus.values.byName(row['status'] as String);
}

/// Offline-first outbox. Every record (delivery, failure, pickup, handover, Break-Bulk, scan validation) is saved
/// on the phone first with its own client uuid, then sent when a connection exists. The backend returns the
/// already-stored record for a repeated client uuid, so a retry after a lost response never creates a duplicate.
class SyncService extends ChangeNotifier with WidgetsBindingObserver {
  SyncService(this._db, this._api);

  final LocalDb _db;
  final ApiClient _api;
  final _connectivity = Connectivity();
  StreamSubscription<List<ConnectivityResult>>? _sub;
  Timer? _timer;
  bool _running = false;
  bool online = true;
  DateTime? lastSyncAt;
  List<OutboxEntry> entries = [];

  /// Called after records sync so the job sheet can be refreshed from the server.
  VoidCallback? onSynced;
  VoidCallback? onUnauthorized;

  int get pendingCount => entries.where((e) => e.status == SyncStatus.pending || e.status == SyncStatus.syncing).length;
  int get failedCount => entries.where((e) => e.status == SyncStatus.failed).length;

  bool _started = false;

  Future<void> start() async {
    if (_started) return syncNow();
    _started = true;
    WidgetsBinding.instance.addObserver(this);
    await reload();
    online = _isOnline(await _connectivity.checkConnectivity());
    _sub = _connectivity.onConnectivityChanged.listen((result) {
      final was = online;
      online = _isOnline(result);
      notifyListeners();
      if (online && !was) syncNow();
    });
    _timer = Timer.periodic(AppConfig.syncInterval, (_) {
      if (pendingCount > 0) syncNow();
    });
    syncNow();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) syncNow();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    _sub?.cancel();
    _timer?.cancel();
    super.dispose();
  }

  bool _isOnline(List<ConnectivityResult> r) => r.any((c) => c != ConnectivityResult.none);

  Future<void> reload() async {
    final rows = await _db.db.query('outbox', orderBy: 'id DESC', limit: 300);
    entries = rows.map((r) => OutboxEntry(Map<String, dynamic>.from(r))).toList();
    notifyListeners();
  }

  /// The latest unsynced-or-synced record of [kind] for a delivery order (to show local progress / block repeats).
  OutboxEntry? latestFor(int deliveryOrderId, String kind) {
    for (final e in entries) {
      if (e.deliveryOrderId == deliveryOrderId && e.kind == kind && e.status != SyncStatus.failed) return e;
    }
    return null;
  }

  OutboxEntry? latestForJobSheet(int jobSheetId, String kind) {
    for (final e in entries) {
      if (e.jobSheetId == jobSheetId && e.kind == kind) return e;
    }
    return null;
  }

  Future<OutboxEntry> enqueue({
    required String kind,
    required String endpoint,
    required Map<String, dynamic> payload,
    required String label,
    List<OutboxFile> files = const [],
    int? deliveryOrderId,
    int? jobSheetId,
  }) async {
    final uuid = const Uuid().v4();
    final id = await _db.db.insert('outbox', {
      'client_uuid': uuid,
      'kind': kind,
      'endpoint': endpoint,
      'payload': jsonEncode({...payload, 'client_uuid': uuid}),
      'files': jsonEncode(files.map((f) => f.toJson()).toList()),
      'label': label,
      'delivery_order_id': deliveryOrderId,
      'job_sheet_id': jobSheetId,
      'status': SyncStatus.pending.name,
      'created_at': DateTime.now().toIso8601String(),
    });
    await reload();
    unawaited(syncNow());
    return entries.firstWhere((e) => e.id == id);
  }

  Future<void> retry(OutboxEntry e) async {
    await _db.db.update(
      'outbox',
      {'status': SyncStatus.pending.name, 'error': null},
      where: 'id = ?',
      whereArgs: [e.id],
    );
    await reload();
    await syncNow();
  }

  Future<void> discard(OutboxEntry e) async {
    await _db.db.delete('outbox', where: 'id = ?', whereArgs: [e.id]);
    for (final f in e.files) {
      await MediaStore.delete(f.localPath);
    }
    await reload();
  }

  Future<void> syncNow() async {
    if (_running || !_api.hasToken) return;
    _running = true;
    var anySynced = false;

    try {
      final rows = await _db.db.query(
        'outbox',
        where: 'status IN (?, ?)',
        whereArgs: ['pending', 'syncing'],
        orderBy: 'id ASC',
      );

      for (final row in rows) {
        final entry = OutboxEntry(Map<String, dynamic>.from(row));
        await _setStatus(entry.id, SyncStatus.syncing);

        try {
          final payload = entry.payload;
          for (final file in entry.files) {
            file.remotePath ??= await _api.upload(file.localPath, kind: file.kind, clientUuid: file.uuid);
            await _db.db.update(
              'outbox',
              {'files': jsonEncode(entry.files.map((f) => f.toJson()).toList())},
              where: 'id = ?',
              whereArgs: [entry.id],
            );
          }
          for (final file in entry.files) {
            if (file.field == 'photo_paths') {
              payload['photo_paths'] = [...(payload['photo_paths'] as List? ?? []), file.remotePath];
            } else {
              payload[file.field] = file.remotePath;
            }
          }

          await _api.post(entry.endpoint, payload);

          await _db.db.update(
            'outbox',
            {
              'status': SyncStatus.synced.name,
              'error': null,
              'synced_at': DateTime.now().toIso8601String(),
              'attempts': entry.attempts + 1,
            },
            where: 'id = ?',
            whereArgs: [entry.id],
          );
          for (final file in entry.files) {
            await MediaStore.delete(file.localPath);
          }
          anySynced = true;
          online = true;
        } on ApiException catch (e) {
          if (e.unauthorized) {
            await _setStatus(entry.id, SyncStatus.pending);
            onUnauthorized?.call();
            break;
          }
          if (e.rejected) {
            await _db.db.update(
              'outbox',
              {'status': SyncStatus.failed.name, 'error': e.message, 'attempts': entry.attempts + 1},
              where: 'id = ?',
              whereArgs: [entry.id],
            );
            continue;
          }
          // no connection / server down: keep pending and retry later, in order
          await _db.db.update(
            'outbox',
            {'status': SyncStatus.pending.name, 'error': e.message, 'attempts': entry.attempts + 1},
            where: 'id = ?',
            whereArgs: [entry.id],
          );
          if (e.offline) online = false;
          break;
        } catch (e) {
          await _db.db.update(
            'outbox',
            {'status': SyncStatus.pending.name, 'error': e.toString(), 'attempts': entry.attempts + 1},
            where: 'id = ?',
            whereArgs: [entry.id],
          );
          break;
        }
      }
    } finally {
      _running = false;
      lastSyncAt = DateTime.now();
      await reload();
      if (anySynced) onSynced?.call();
    }
  }

  Future<void> _setStatus(int id, SyncStatus s) async {
    await _db.db.update('outbox', {'status': s.name}, where: 'id = ?', whereArgs: [id]);
    await reload();
  }
}
