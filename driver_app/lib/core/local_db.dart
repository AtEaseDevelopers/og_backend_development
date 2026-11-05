import 'dart:convert';

import 'package:path/path.dart' as p;
import 'package:sqflite/sqflite.dart';

/// On-device storage: cached server data (job sheets, reason lists) and the outbox of records captured offline.
class LocalDb {
  LocalDb._(this.db);

  final Database db;

  static Future<LocalDb> open() async {
    final db = await openDatabase(
      p.join(await getDatabasesPath(), 'og_driver.db'),
      version: 1,
      onCreate: (db, _) async {
        await db.execute('CREATE TABLE cache (key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL)');
        await db.execute('''
          CREATE TABLE outbox (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            client_uuid TEXT NOT NULL UNIQUE,
            kind TEXT NOT NULL,
            endpoint TEXT NOT NULL,
            payload TEXT NOT NULL,
            files TEXT NOT NULL,
            label TEXT NOT NULL,
            delivery_order_id INTEGER,
            job_sheet_id INTEGER,
            status TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            error TEXT,
            created_at TEXT NOT NULL,
            synced_at TEXT
          )''');
      },
    );
    return LocalDb._(db);
  }

  Future<void> putCache(String key, Object value) => db.insert('cache', {
    'key': key,
    'value': jsonEncode(value),
    'updated_at': DateTime.now().toIso8601String(),
  }, conflictAlgorithm: ConflictAlgorithm.replace);

  Future<dynamic> getCache(String key) async {
    final rows = await db.query('cache', where: 'key = ?', whereArgs: [key], limit: 1);
    return rows.isEmpty ? null : jsonDecode(rows.first['value'] as String);
  }

  Future<DateTime?> cacheTime(String key) async {
    final rows = await db.query('cache', columns: ['updated_at'], where: 'key = ?', whereArgs: [key], limit: 1);
    return rows.isEmpty ? null : DateTime.tryParse(rows.first['updated_at'] as String);
  }

  Future<void> clearAll() async {
    await db.delete('cache');
    await db.delete('outbox');
  }
}
