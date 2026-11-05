import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'config.dart';

/// Error from the backend. [offline] means the request never reached the server (no connection / timeout), so
/// an offline record must stay pending; anything else is the server's answer.
class ApiException implements Exception {
  ApiException(this.message, {this.status, this.offline = false, this.errors});

  final String message;
  final int? status;
  final bool offline;
  final Map<String, dynamic>? errors;

  bool get unauthorized => status == 401;

  /// The server rejected the record itself (validation / business rule); retrying the same payload won't help.
  bool get rejected =>
      status != null && status! >= 400 && status! < 500 && status != 401 && status != 408 && status != 429;

  @override
  String toString() => message;
}

class ApiClient {
  ApiClient() {
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) {
          options.baseUrl = AppConfig.apiBase;
          if (_token != null) options.headers['Authorization'] = 'Bearer $_token';
          handler.next(options);
        },
      ),
    );
  }

  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'auth_token';

  final Dio _dio = Dio(
    BaseOptions(
      connectTimeout: const Duration(seconds: 12),
      receiveTimeout: const Duration(seconds: 30),
      sendTimeout: const Duration(seconds: 60),
      headers: {'Accept': 'application/json'},
    ),
  );

  String? _token;

  bool get hasToken => _token != null;

  Future<void> loadToken() async => _token = await _storage.read(key: _tokenKey);

  Future<void> setToken(String? token) async {
    _token = token;
    if (token == null) {
      await _storage.delete(key: _tokenKey);
    } else {
      await _storage.write(key: _tokenKey, value: token);
    }
  }

  Future<Map<String, dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => _dio.get(path, queryParameters: query));

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) =>
      _send(() => _dio.post(path, data: body ?? {}));

  /// Uploads one local file; the [clientUuid] makes a retried upload land on the same server path.
  Future<String> upload(String filePath, {required String kind, required String clientUuid}) async {
    final form = FormData.fromMap({
      'kind': kind,
      'client_uuid': clientUuid,
      'file': await MultipartFile.fromFile(filePath, filename: filePath.split('/').last),
    });
    final res = await _send(() => _dio.post('/uploads', data: form));
    return (res['data'] as Map)['path'] as String;
  }

  Future<Map<String, dynamic>> _send(Future<Response> Function() request) async {
    try {
      final res = await request();
      final data = res.data;
      return data is Map<String, dynamic> ? data : <String, dynamic>{'data': data};
    } on DioException catch (e) {
      final res = e.response;
      if (res == null) {
        throw ApiException(
          'No connection to the server. The record is kept on this phone and will sync later.',
          offline: true,
        );
      }
      final body = res.data is Map ? Map<String, dynamic>.from(res.data as Map) : <String, dynamic>{};
      final errors = body['errors'] is Map ? Map<String, dynamic>.from(body['errors'] as Map) : null;
      var message = body['message']?.toString() ?? 'Server error (${res.statusCode}).';
      if (errors != null && errors.isNotEmpty) {
        message = errors.values.expand((v) => v is List ? v : [v]).map((v) => v.toString()).join('\n');
      }
      throw ApiException(message, status: res.statusCode, offline: (res.statusCode ?? 500) >= 500, errors: errors);
    }
  }
}
