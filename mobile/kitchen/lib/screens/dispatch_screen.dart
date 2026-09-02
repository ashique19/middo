import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_mobile_header.dart';
import '../widgets/kitchen_ui.dart';

class DispatchScreen extends StatefulWidget {
  const DispatchScreen({super.key, required this.orderId});

  final int orderId;

  @override
  State<DispatchScreen> createState() => _DispatchScreenState();
}

class _DispatchScreenState extends State<DispatchScreen> {
  Future<Map<String, dynamic>>? _future;
  final Set<int> _selected = {};
  bool _busy = false;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _future ??= AppScope.of(context).dispatchOptions(widget.orderId);
  }

  Future<void> _reload() async {
    setState(() {
      _future = AppScope.of(context).dispatchOptions(widget.orderId);
      _selected.clear();
    });
    await _future;
  }

  Future<void> _dispatch(int required) async {
    if (_selected.length != required) {
      showKitchenSnack(
        context,
        'Select exactly $required box(es).',
        error: true,
      );
      return;
    }
    setState(() => _busy = true);
    try {
      final res = await AppScope.of(context).dispatchOrder(
        widget.orderId,
        boxIds: _selected.toList()..sort(),
      );
      if (!mounted) return;
      showKitchenSnack(
        context,
        res['message']?.toString() ?? 'Dispatched.',
      );
      context.go('/orders');
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _busy = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: KitchenMobileHeader(
        title: 'Dispatch #${widget.orderId}',
        showBack: true,
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _future,
        builder: (context, snap) {
          if (snap.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snap.hasError) {
            return KitchenError(snap.error!, onRetry: _reload);
          }
          final data = snap.data ?? const {};
          final can = data['can_dispatch'] == true;
          final required = (data['required_quantity'] as num?)?.toInt() ?? 0;
          final boxes = (data['available_boxes'] as List?) ?? const [];
          if (!can) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [
                Text(
                  data['message']?.toString() ?? 'Cannot dispatch yet.',
                  style: const TextStyle(color: MiddoColors.inkSoft),
                ),
                const SizedBox(height: 12),
                OutlinedButton(
                  onPressed: () => context.pop(),
                  child: const Text('Back'),
                ),
              ],
            );
          }
          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
            children: [
              Text(
                'Select $required box(es) · ${_selected.length} selected',
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 12),
              for (final raw in boxes)
                CheckboxListTile(
                  value: _selected.contains((raw as Map)['id'] as int),
                  title: Text(raw['qr_code_id']?.toString() ?? '#${raw['id']}'),
                  controlAffinity: ListTileControlAffinity.leading,
                  onChanged: (v) {
                    final id = raw['id'] as int;
                    setState(() {
                      if (v == true) {
                        if (_selected.length >= required) {
                          showKitchenSnack(
                            context,
                            'Only $required box(es) needed.',
                            error: true,
                          );
                          return;
                        }
                        _selected.add(id);
                      } else {
                        _selected.remove(id);
                      }
                    });
                  },
                ),
              const SizedBox(height: 16),
              FilledButton(
                onPressed: _busy ? null : () => _dispatch(required),
                child: Text(_busy ? 'Dispatching…' : 'Confirm dispatch'),
              ),
            ],
          );
        },
      ),
    );
  }
}
