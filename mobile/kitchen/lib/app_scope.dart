import 'package:flutter/material.dart';

import 'data/kitchen_repository.dart';

class AppScope extends InheritedWidget {
  const AppScope({
    super.key,
    required this.repository,
    required super.child,
  });

  final KitchenRepository repository;

  static KitchenRepository of(BuildContext context) {
    final scope = context.dependOnInheritedWidgetOfExactType<AppScope>();
    assert(scope != null, 'AppScope not found in widget tree');
    return scope!.repository;
  }

  @override
  bool updateShouldNotify(AppScope oldWidget) =>
      repository != oldWidget.repository;
}
