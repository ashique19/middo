import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _mobile = TextEditingController(text: '01310123452');
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
          padding: EdgeInsets.zero,
          children: [
            SizedBox(
              height: 240,
              child: Stack(
                fit: StackFit.expand,
                children: [
                  Image.asset('assets/images/login.jpg', fit: BoxFit.cover),
                  DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          MiddoColors.forest.withValues(alpha: 0.15),
                          MiddoColors.cream.withValues(alpha: 0.95),
                        ],
                      ),
                    ),
                  ),
                  Positioned(
                    left: 22,
                    bottom: 18,
                    right: 22,
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'MIDDO CORPORATE',
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                color: MiddoColors.orange,
                                fontWeight: FontWeight.w800,
                                letterSpacing: 0.8,
                              ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          'Office lunches,\nzero hassle.',
                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                                letterSpacing: -0.6,
                                height: 1.1,
                              ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Sign in with your corporate mobile number to schedule thalis and track Middo Boxes.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: MiddoColors.inkSoft,
                          fontWeight: FontWeight.w600,
                        ),
                  ),
                  const SizedBox(height: 20),
                  TextField(
                    controller: _mobile,
                    keyboardType: TextInputType.phone,
                    decoration: const InputDecoration(
                      labelText: 'MOBILE',
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _password,
                    obscureText: true,
                    decoration: const InputDecoration(
                      labelText: 'PASSWORD',
                    ),
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
                  const SizedBox(height: 16),
                  FilledButton(
                    onPressed: _loading ? null : _submit,
                    child: Text(_loading ? 'Signing in…' : 'Sign In'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
