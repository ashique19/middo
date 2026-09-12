import 'package:flutter/material.dart';

import 'data/auth_store.dart';
import 'data/operation_repository.dart';

class AppScope extends InheritedWidget {
  const AppScope({
    super.key,
    required this.repository,
    required super.child,
  });

  final OperationRepository repository;

  static AppScope _scope(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AppScope>();
    assert(scope != null, 'AppScope not found');
    return scope!;
  }

  static AppScope of(BuildContext context) => _scope(context);

  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
  }) =>
      repository.login(mobile: mobile, password: password);

  Future<void> logout() => repository.logout();

  Future<Map<String, dynamic>> me() => repository.me();

  Future<Map<String, dynamic>> dashboard() => repository.dashboard();

  Future<Map<String, dynamic>> boxRequests() => repository.boxRequests();

  Future<Map<String, dynamic>> lookupBoxByQr(String qr) =>
      repository.lookupBoxByQr(qr);

  Future<Map<String, dynamic>> ridersBoard() => repository.ridersBoard();

  Future<Map<String, dynamic>> cashHandovers() => repository.cashHandovers();

  Future<Map<String, dynamic>> acceptCashHandover(int id) =>
      repository.acceptCashHandover(id);

  Future<Map<String, dynamic>> slaBoard() => repository.slaBoard();

  Future<Map<String, dynamic>> opsDay() => repository.opsDay();

  Future<Map<String, dynamic>> complaints() => repository.complaints();

  bool get isAuthenticated => AuthStore.instance.isAuthenticated;

  @override
  bool updateShouldNotify(AppScope oldWidget) =>
      repository != oldWidget.repository;
}
