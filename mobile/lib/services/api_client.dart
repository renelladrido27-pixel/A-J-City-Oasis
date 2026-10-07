import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import 'api_exception.dart';

/// Thin JSON/Bearer-token HTTP wrapper around the Laravel API
/// (routes/api.php). See mobile/README.md for how the base URL is reached
/// from an emulator vs. a physical device.
class ApiClient {
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000/api',
  );

  /// The website the API belongs to (e.g. https://ajcityoasis.online) — for
  /// pages that only exist on the web, like "Forgot password?".
  static String get siteUrl => baseUrl.replaceFirst(RegExp(r'/api/?$'), '');

  static const _storage = FlutterSecureStorage();
  static const _tokenKey = 'auth_token';

  String? _cachedToken;

  Future<String?> get token async {
    return _cachedToken ??= await _storage.read(key: _tokenKey);
  }

  Future<void> setToken(String? token) async {
    _cachedToken = token;
    if (token == null) {
      await _storage.delete(key: _tokenKey);
    } else {
      await _storage.write(key: _tokenKey, value: token);
    }
  }

  Future<Map<String, dynamic>> get(String path) => _send('GET', path);

  Future<Map<String, dynamic>> post(
    String path, [
    Map<String, dynamic>? body,
  ]) => _send('POST', path, body);

  Future<Map<String, dynamic>> put(String path, [Map<String, dynamic>? body]) =>
      _send('PUT', path, body);

  Future<Map<String, dynamic>> delete(String path) => _send('DELETE', path);

  /// POST as multipart/form-data — for endpoints that take a file upload
  /// (e.g. a maintenance request photo) alongside plain text fields.
  Future<Map<String, dynamic>> postMultipart(
    String path, {
    required Map<String, String> fields,
    Map<String, String> files = const {},
  }) async {
    final request = http.MultipartRequest('POST', Uri.parse('$baseUrl$path'))
      ..headers['Accept'] = 'application/json'
      ..fields.addAll(fields);
    final t = await token;
    if (t != null) request.headers['Authorization'] = 'Bearer $t';

    for (final entry in files.entries) {
      request.files.add(
        await http.MultipartFile.fromPath(entry.key, entry.value),
      );
    }

    try {
      final streamed = await request.send();
      return _decode(await http.Response.fromStream(streamed));
    } on http.ClientException catch (e) {
      throw ApiException(
        'Could not reach the server. Check your connection. (${e.message})',
      );
    }
  }

  Future<Map<String, dynamic>> _send(
    String method,
    String path, [
    Map<String, dynamic>? body,
  ]) async {
    final uri = Uri.parse('$baseUrl$path');
    final headers = {
      'Accept': 'application/json',
      'Content-Type': 'application/json',
    };
    final t = await token;
    if (t != null) headers['Authorization'] = 'Bearer $t';

    http.Response response;
    try {
      switch (method) {
        case 'GET':
          response = await http.get(uri, headers: headers);
        case 'POST':
          response = await http.post(
            uri,
            headers: headers,
            body: body == null ? null : jsonEncode(body),
          );
        case 'PUT':
          response = await http.put(
            uri,
            headers: headers,
            body: body == null ? null : jsonEncode(body),
          );
        case 'DELETE':
          response = await http.delete(uri, headers: headers);
        default:
          throw ApiException('Unsupported method $method');
      }
    } on http.ClientException catch (e) {
      throw ApiException(
        'Could not reach the server. Check your connection. (${e.message})',
      );
    } catch (e) {
      throw ApiException('Could not reach the server. Check your connection.');
    }

    return _decode(response);
  }

  Map<String, dynamic> _decode(http.Response response) {
    if (response.statusCode == 204 || response.body.isEmpty) {
      return {};
    }

    late final dynamic decoded;
    try {
      decoded = jsonDecode(response.body);
    } catch (_) {
      throw ApiException(
        'Unexpected server response (${response.statusCode}).',
        statusCode: response.statusCode,
      );
    }

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return decoded as Map<String, dynamic>;
    }

    final message =
        _extractErrorMessage(decoded) ??
        'Request failed (${response.statusCode}).';
    throw ApiException(message, statusCode: response.statusCode);
  }

  String? _extractErrorMessage(dynamic decoded) {
    if (decoded is! Map<String, dynamic>) return null;
    if (decoded['errors'] is Map && (decoded['errors'] as Map).isNotEmpty) {
      final firstField = (decoded['errors'] as Map).values.first;
      if (firstField is List && firstField.isNotEmpty) {
        return firstField.first.toString();
      }
    }
    return decoded['message']?.toString();
  }
}
