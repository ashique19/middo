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

  OperationRepository get repo => repository;

  static AppScope of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AppScope>();
    assert(scope != null, 'AppScope not found');
    return scope!;
  }

  Future<Map<String, dynamic>> login({
    required String mobile,
    required String password,
  }) =>
      repository.login(mobile: mobile, password: password);

  Future<void> logout() => repository.logout();

  Future<Map<String, dynamic>> me() => repository.me();

  Future<Map<String, dynamic>> dashboard() => repository.dashboard();

  Future<Map<String, dynamic>> boards({String? date}) =>
      repository.boards(date: date);

  Future<Map<String, dynamic>> alerts() => repository.alerts();

  Future<Map<String, dynamic>> markAlertRead(int id) =>
      repository.markAlertRead(id);

  Future<Map<String, dynamic>> markAllAlertsRead() =>
      repository.markAllAlertsRead();

  Future<Map<String, dynamic>> boxes({String? custody, String? search}) =>
      repository.boxes(custody: custody, search: search);

  Future<Map<String, dynamic>> boxRequests() => repository.boxRequests();

  Future<Map<String, dynamic>> lookupBoxByQr(String qr) =>
      repository.lookupBoxByQr(qr);

  Future<Map<String, dynamic>> assignBoxRequest({
    required int requestId,
    required List<int> boxIds,
    required int riderId,
  }) =>
      repository.assignBoxRequest(
        requestId: requestId,
        boxIds: boxIds,
        riderId: riderId,
      );

  Future<Map<String, dynamic>> reassignBox({
    required int boxId,
    required String kind,
    required int riderId,
    int? requestId,
    int? kitchenId,
  }) =>
      repository.reassignBox(
        boxId: boxId,
        kind: kind,
        riderId: riderId,
        requestId: requestId,
        kitchenId: kitchenId,
      );

  Future<Map<String, dynamic>> ackBoxReturn(int boxId) =>
      repository.ackBoxReturn(boxId);

  Future<Map<String, dynamic>> ridersBoard() => repository.ridersBoard();

  Future<Map<String, dynamic>> assignLunchRider({
    required int orderId,
    required int riderId,
  }) =>
      repository.assignLunchRider(orderId: orderId, riderId: riderId);

  Future<Map<String, dynamic>> reassignLunchRider({
    required int orderId,
    required int riderId,
    String? reason,
  }) =>
      repository.reassignLunchRider(
        orderId: orderId,
        riderId: riderId,
        reason: reason,
      );

  Future<Map<String, dynamic>> createCustomRun({
    required String fromLabel,
    required String toLabel,
    required int riderId,
    String? notes,
  }) =>
      repository.createCustomRun(
        fromLabel: fromLabel,
        toLabel: toLabel,
        riderId: riderId,
        notes: notes,
      );

  Future<Map<String, dynamic>> cancelCustomRun(int id) =>
      repository.cancelCustomRun(id);

  Future<Map<String, dynamic>> cashHandovers() => repository.cashHandovers();

  Future<Map<String, dynamic>> acceptCashHandover(int id) =>
      repository.acceptCashHandover(id);

  Future<Map<String, dynamic>> rejectCashHandover(int id, {String? reason}) =>
      repository.rejectCashHandover(id, reason: reason);

  Future<Map<String, dynamic>> slaBoard() => repository.slaBoard();

  Future<Map<String, dynamic>> assignKitchen({
    required int groupId,
    required int kitchenId,
  }) =>
      repository.assignKitchen(groupId: groupId, kitchenId: kitchenId);

  Future<Map<String, dynamic>> opsDay() => repository.opsDay();

  Future<Map<String, dynamic>> complaints() => repository.complaints();

  Future<Map<String, dynamic>> showComplaint(int id) =>
      repository.showComplaint(id);

  Future<Map<String, dynamic>> replyComplaint(
    int id, {
    required String message,
  }) =>
      repository.replyComplaint(id, message: message);

  Future<Map<String, dynamic>> completeComplaint(int id) =>
      repository.completeComplaint(id);

  Future<Map<String, dynamic>> searchOrders(String q) =>
      repository.searchOrders(q);

  Future<Map<String, dynamic>> showOrder(int id) => repository.showOrder(id);

  Future<Map<String, dynamic>> forceCancelOrder(int id, {String? reason}) =>
      repository.forceCancelOrder(id, reason: reason);

  Future<Map<String, dynamic>> releaseRider(int id, {String? reason}) =>
      repository.releaseRider(id, reason: reason);

  bool get isAuthenticated => AuthStore.instance.isAuthenticated;

  @override
  bool updateShouldNotify(AppScope oldWidget) =>
      repository != oldWidget.repository;
}
