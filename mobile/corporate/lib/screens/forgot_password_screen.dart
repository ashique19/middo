import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

enum _ForgotStep { mobile, reset }

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _mobile = TextEditingController();
  final _otp = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();

  _ForgotStep _step = _ForgotStep.mobile;
  bool _submitting = false;
  String? _error;
  String? _debugOtp;

  @override
  void dispose() {
    _mobile.dispose();
    _otp.dispose();
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  Future<void> _sendCode() async {
    final mobile = _mobile.text.trim();
    if (!RegExp(r'^01[3-9]\d{8}$').hasMatch(mobile)) {
      setState(() => _error = 'Enter a valid 11-digit BD mobile (01XXXXXXXXX).');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final debugOtp = await AppScope.of(context).forgotPassword(mobile: mobile);
      if (!mounted) return;
      setState(() {
        _step = _ForgotStep.reset;
        _debugOtp = debugOtp;
        _otp.clear();
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Reset code sent to $mobile'),
          backgroundColor: MiddoColors.forest,
        ),
      );
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _reset() async {
    final mobile = _mobile.text.trim();
    final otp = _otp.text.trim();
    final password = _password.text;
    final confirm = _confirm.text;

    if (!RegExp(r'^\d{4}$').hasMatch(otp)) {
      setState(() => _error = 'Enter the 4-digit SMS code.');
      return;
    }
    if (password.length < 8) {
      setState(() => _error = 'Password must be at least 8 characters.');
      return;
    }
    if (password != confirm) {
      setState(() => _error = 'Password confirmation does not match.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await AppScope.of(context).resetPassword(
        mobile: mobile,
        otp: otp,
        password: password,
        passwordConfirmation: confirm,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Password updated. Sign in with your new password.'),
          backgroundColor: MiddoColors.forest,
        ),
      );
      context.go('/login');
    } on ApiException catch (e) {
      setState(() => _error = e.message);
    } catch (e) {
      setState(() => _error = e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () {
            if (_step == _ForgotStep.reset && !_submitting) {
              setState(() {
                _step = _ForgotStep.mobile;
                _error = null;
              });
              return;
            }
            context.go('/login');
          },
        ),
        title: Text(
          _step == _ForgotStep.mobile ? 'Forgot password' : 'Reset password',
        ),
      ),
      body: Stack(
        children: [
          ListView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 32),
            children: [
              Text(
                _step == _ForgotStep.mobile
                    ? 'Reset with SMS'
                    : 'Enter code & new password',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.5,
                    ),
              ),
              const SizedBox(height: 4),
              Text(
                _step == _ForgotStep.mobile
                    ? 'We’ll text a 4-digit code to your registered corporate mobile.'
                    : 'Code sent to ${_mobile.text.trim()}. Valid for 5 minutes.',
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  color: MiddoColors.inkSoft,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 18),
              if (_step == _ForgotStep.mobile) ...[
                TextField(
                  controller: _mobile,
                  keyboardType: TextInputType.phone,
                  enabled: !_submitting,
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(11),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'MOBILE',
                    hintText: '01XXXXXXXXX',
                  ),
                ),
              ] else ...[
                if (_debugOtp != null) ...[
                  Text(
                    'Debug OTP: $_debugOtp',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      color: MiddoColors.orange,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 10),
                ],
                TextField(
                  controller: _otp,
                  keyboardType: TextInputType.number,
                  textAlign: TextAlign.center,
                  enabled: !_submitting,
                  style: const TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 12,
                  ),
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(4),
                  ],
                  decoration: const InputDecoration(
                    labelText: 'OTP',
                    hintText: '••••',
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _password,
                  obscureText: true,
                  enabled: !_submitting,
                  decoration: const InputDecoration(labelText: 'NEW PASSWORD'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _confirm,
                  obscureText: true,
                  enabled: !_submitting,
                  decoration:
                      const InputDecoration(labelText: 'CONFIRM PASSWORD'),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: _submitting ? null : _sendCode,
                  child: const Text('Resend code'),
                ),
              ],
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
                onPressed: _submitting
                    ? null
                    : (_step == _ForgotStep.mobile ? _sendCode : _reset),
                child: Text(
                  _submitting
                      ? (_step == _ForgotStep.mobile
                          ? 'Sending…'
                          : 'Updating…')
                      : (_step == _ForgotStep.mobile
                          ? 'Send reset code'
                          : 'Update password'),
                ),
              ),
              const SizedBox(height: 10),
              TextButton(
                onPressed: _submitting ? null : () => context.go('/login'),
                child: const Text('Back to sign in'),
              ),
            ],
          ),
          if (_submitting)
            ColoredBox(
              color: const Color(0x66F7F4EB),
              child: MiddoPageLoader(
                message: _step == _ForgotStep.mobile
                    ? 'Sending reset code…'
                    : 'Updating password…',
              ),
            ),
        ],
      ),
    );
  }
}
