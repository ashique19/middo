class ApiConfig {
  /// Override: `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000`
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'https://x.middo.com.bd',
  );

  static String get apiRoot => '$baseUrl/api/operation';

  /// Alias used by some error messages.
  static String get baseUrlAlias => baseUrl;
}
