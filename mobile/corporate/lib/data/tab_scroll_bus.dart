import 'package:flutter/material.dart';

/// Lets the shell ask the active tab to scroll its primary list to the top
/// when the user re-taps the already-selected bottom nav item.
class TabScrollBus {
  TabScrollBus._();
  static final instance = TabScrollBus._();

  final Map<int, ScrollController> _controllers = {};

  void register(int tabIndex, ScrollController controller) {
    _controllers[tabIndex] = controller;
  }

  void unregister(int tabIndex, ScrollController controller) {
    if (_controllers[tabIndex] == controller) {
      _controllers.remove(tabIndex);
    }
  }

  Future<void> scrollToTop(int tabIndex) async {
    final controller = _controllers[tabIndex];
    if (controller == null || !controller.hasClients) return;

    await controller.animateTo(
      0,
      duration: const Duration(milliseconds: 320),
      curve: Curves.easeOutCubic,
    );
  }
}
