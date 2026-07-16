class ApiConfig {
  /// Override at build/run time:
  /// `flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000`
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );

  /// When true, skip network and use MockRepository.
  static const bool useMock = bool.fromEnvironment(
    'USE_MOCK',
    defaultValue: false,
  );

  static String get apiRoot => '$baseUrl/api/corporate';
}
