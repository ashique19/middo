import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/push_notification_service.dart';
import '../theme/middo_colors.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _mobile = TextEditingController(text: '01310123453');
  final _password = TextEditingController(text: '12345678');
  bool _loading = false;
  String? _error;

  @override
  void dispose() {
    _mobile.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      await AppScope.of(context).login(
        mobile: _mobile.text.trim(),
        password: _password.text,
      );
      await PushNotificationService.instance.syncWithBackend();
      if (!mounted) return;
      context.go('/home');
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(22, 36, 22, 28),
          children: [
            Text(
              'MIDDO KITCHEN',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: MiddoColors.orange,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.8,
                  ),
            ),
            const SizedBox(height: 8),
            Text(
              'Cook, pack,\ndispatch.',
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.6,
                    height: 1.1,
                  ),
            ),
            const SizedBox(height: 28),
            TextField(
              controller: _mobile,
              keyboardType: TextInputType.phone,
              decoration: const InputDecoration(labelText: 'MOBILE'),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: _password,
              obscureText: true,
              decoration: const InputDecoration(labelText: 'PASSWORD'),
            ),
            if (_error != null) ...[
              const SizedBox(height: 12),
              Text(
                _error!,
                style: const TextStyle(color: Color(0xFFB42318)),
              ),
            ],
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _loading ? null : _submit,
              child: Text(_loading ? 'Signing in…' : 'Sign In'),
            ),
          ],
        ),
      ),
    );
  }
}
