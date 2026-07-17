import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class ChangePasswordScreen extends StatefulWidget {
  const ChangePasswordScreen({super.key});

  @override
  State<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends State<ChangePasswordScreen> {
  final _current = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  bool _saving = false;
  String? _error;

  @override
  void dispose() {
    _current.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final current = _current.text;
    final password = _password.text;
    final confirm = _confirm.text;

    if (current.isEmpty) {
      setState(() => _error = 'Enter your current password.');
      return;
    }
    if (password.length < 8) {
      setState(() => _error = 'New password must be at least 8 characters.');
      return;
    }
    if (password != confirm) {
      setState(() => _error = 'Password confirmation does not match.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      await AppScope.of(context).changePassword(
        currentPassword: current,
        password: password,
        passwordConfirmation: confirm,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Password changed successfully'),
          backgroundColor: MiddoColors.forest,
        ),
      );
      context.pop();
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('Change password'),
      ),
      body: Stack(
        children: [
          ListView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 32),
            children: [
              const Text(
                'Choose a strong password you don’t use elsewhere.',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: MiddoColors.inkSoft,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 18),
              TextField(
                controller: _current,
                obscureText: true,
                enabled: !_saving,
                decoration:
                    const InputDecoration(labelText: 'CURRENT PASSWORD'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _password,
                obscureText: true,
                enabled: !_saving,
                decoration: const InputDecoration(labelText: 'NEW PASSWORD'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _confirm,
                obscureText: true,
                enabled: !_saving,
                decoration:
                    const InputDecoration(labelText: 'CONFIRM NEW PASSWORD'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 12),
                Text(
                  _error!,
                  style: const TextStyle(
                    color: Color(0xFFB91C1C),
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
              ],
              const SizedBox(height: 18),
              FilledButton(
                onPressed: _saving ? null : _submit,
                child: Text(_saving ? 'Updating…' : 'Update password'),
              ),
            ],
          ),
          if (_saving)
            const ColoredBox(
              color: Color(0x66F7F4EB),
              child: MiddoPageLoader(message: 'Updating password…'),
            ),
        ],
      ),
    );
  }
}
