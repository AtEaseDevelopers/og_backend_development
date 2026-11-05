import 'package:flutter/foundation.dart';
import 'package:intl/intl.dart';

import '../core/api_client.dart';
import '../core/local_db.dart';
import '../core/location.dart';
import '../models/models.dart';
import 'sync_service.dart';

/// Session (login, reference lists) and the driver's job sheets for the day, cached for offline use.
class AppState extends ChangeNotifier {
  AppState(this.db, this.api, this.sync) {
    sync.onSynced = () => refresh(silent: true);
    sync.onUnauthorized = () {
      sessionExpired = true;
      notifyListeners();
    };
  }

  final LocalDb db;
  final ApiClient api;
  final SyncService sync;

  Json? user;
  Meta? meta;
  bool sessionExpired = false;

  List<JobSheet> jobSheets = [];
  int? selectedJobSheetId;
  bool loading = false;
  String? error;
  DateTime? lastRefreshed;
  bool fromCache = false;

  bool get loggedIn => api.hasToken && user != null;

  String get today => DateFormat('yyyy-MM-dd').format(DateTime.now());

  JobSheet? get current {
    if (jobSheets.isEmpty) return null;
    return jobSheets.firstWhere(
      (j) => j.id == selectedJobSheetId,
      orElse: () {
        return jobSheets.firstWhere(
          (j) => j.isInTransit,
          orElse: () => jobSheets.firstWhere((j) => j.isDraft, orElse: () => jobSheets.first),
        );
      },
    );
  }

  Future<void> boot() async {
    await api.loadToken();
    if (!api.hasToken) return;
    user = (await db.getCache('user') as Map?)?.cast<String, dynamic>();
    final m = await db.getCache('meta');
    if (m is Map) meta = Meta.fromJson(Json.from(m));
    await _loadCachedJobSheets();
    await sync.start();
    refresh(silent: true);
  }

  Future<void> login(String email, String password) async {
    final res = await api.post('/login', {'email': email.trim(), 'password': password, 'device_name': 'og-driver-app'});
    await api.setToken(res['token'] as String);
    user = Json.from(res['user'] as Map);
    sessionExpired = false;
    await db.putCache('user', user!);
    await _loadMeta();
    await sync.start();
    await refresh();
  }

  Future<void> logout() async {
    if (sync.pendingCount > 0) {
      throw ApiException(
        'There are ${sync.pendingCount} unsynced record(s). Connect to the internet and sync before logging out.',
      );
    }
    try {
      await api.post('/logout');
    } catch (_) {}
    await api.setToken(null);
    await db.clearAll();
    user = null;
    meta = null;
    jobSheets = [];
    notifyListeners();
  }

  Future<void> _loadMeta() async {
    final res = await api.get('/meta');
    final data = Json.from(res['data'] as Map);
    meta = Meta.fromJson(data);
    await db.putCache('meta', data);
  }

  Future<void> _loadCachedJobSheets() async {
    final cached = await db.getCache('job_sheets:$today');
    if (cached is List) {
      jobSheets = cached.map((j) => JobSheet(Json.from(j as Map))).toList();
      lastRefreshed = await db.cacheTime('job_sheets:$today');
      fromCache = true;
      notifyListeners();
    }
  }

  /// Pulls today's trips from the server; falls back to the cached copy when offline.
  Future<void> refresh({bool silent = false}) async {
    if (!api.hasToken) return;
    if (!silent) {
      loading = true;
      notifyListeners();
    }
    try {
      if (meta == null) await _loadMeta();
      final res = await api.get('/job-sheets', query: {'date': today});
      final list = (res['data'] as List? ?? []).map((j) => Json.from(j as Map)).toList();
      jobSheets = list.map(JobSheet.new).toList();
      await db.putCache('job_sheets:$today', list);
      lastRefreshed = DateTime.now();
      fromCache = false;
      error = null;
    } on ApiException catch (e) {
      if (e.unauthorized) sessionExpired = true;
      error = e.offline ? 'Offline — showing the job sheet saved on this phone.' : e.message;
      fromCache = true;
      if (jobSheets.isEmpty) await _loadCachedJobSheets();
    } finally {
      loading = false;
      notifyListeners();
    }
  }

  void selectJobSheet(int id) {
    selectedJobSheetId = id;
    notifyListeners();
  }

  /// Check-in needs the server (it validates the lorry and its operating branch), so it is online only.
  Future<String> checkIn(JobSheet sheet) async {
    final gps = await LocationService.current();
    final res = await api.post('/check-in', {
      'job_sheet_id': sheet.id,
      'lorry_id': sheet.lorryId,
      'latitude': gps?.latitude,
      'longitude': gps?.longitude,
    });
    selectedJobSheetId = sheet.id;
    await refresh(silent: true);
    return res['message']?.toString() ?? 'Checked in.';
  }

  /// What the driver did on this phone that the server may not know yet (pending / failed sync).
  LocalProgress progressOf(DriverTask task) {
    final complete = sync.latestFor(task.id, 'complete');
    final fail = sync.latestFor(task.id, 'fail');
    final handover = sync.latestFor(task.id, 'handover');
    final pickup = sync.latestFor(task.id, 'pickup');
    final breakBulk = sync.latestFor(task.id, 'break_bulk');
    final done = complete ?? handover ?? fail;
    return LocalProgress(
      outcome: complete != null || handover != null ? 'delivered' : (fail != null ? 'failed' : null),
      outcomeEntry: done,
      pickupEntry: pickup,
      breakBulkEntry: breakBulk,
    );
  }
}

class LocalProgress {
  LocalProgress({this.outcome, this.outcomeEntry, this.pickupEntry, this.breakBulkEntry});

  final String? outcome;
  final OutboxEntry? outcomeEntry;
  final OutboxEntry? pickupEntry;
  final OutboxEntry? breakBulkEntry;

  bool get hasOutcome => outcome != null;
}
