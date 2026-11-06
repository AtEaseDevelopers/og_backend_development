import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Server address of the O&G Transport backend (Laravel). Editable on the login screen so the same build works
/// against a local dev machine, staging or production.
class AppConfig {
  AppConfig._();

  static const _storage = FlutterSecureStorage();
  static const _serverKey = 'server_url';

  static const String defaultServer = 'https://ogtransport.at-eases.com';

  static String _server = defaultServer;

  static String get server => _server;

  static String get apiBase => '${_server.replaceAll(RegExp(r'/+$'), '')}/api/v1/driver';

  static bool _isLocalDev(String url) {
    final host = Uri.tryParse(url)?.host ?? '';
    return host == '127.0.0.1' || host == 'localhost' || host == '10.0.2.2';
  }

  static Future<void> load() async {
    final saved = (await _storage.read(key: _serverKey))?.trim();
    if (saved == null || saved.isEmpty || _isLocalDev(saved)) {
      _server = defaultServer;
      await _storage.write(key: _serverKey, value: _server);
      return;
    }
    _server = saved;
  }

  static Future<void> setServer(String value) async {
    _server = value.trim().isEmpty ? defaultServer : value.trim();
    await _storage.write(key: _serverKey, value: _server);
  }

  /// Blur threshold (variance of the Laplacian on a 480 px grayscale copy). Lower means blurrier.
  static const double blurThreshold = 90;

  /// How often pending records are retried while the app is open.
  static const Duration syncInterval = Duration(seconds: 30);
}
