import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/delivery_mobile_header.dart';
import '../widgets/delivery_ui.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _currentPw = TextEditingController();
  final _newPw = TextEditingController();
  final _confirmPw = TextEditingController();

  Map<String, dynamic>? _user;
  bool _loading = true;
  bool _saving = false;
  String? _error;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_user == null && _loading) {
      _load();
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final data = await AppScope.of(context).me();
      _user = (data['user'] as Map?)?.cast<String, dynamic>() ??
          data.cast<String, dynamic>();
    } catch (e) {
      _error = '$e';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _changePassword() async {
    if (_newPw.text != _confirmPw.text) {
      showDeliverySnack(context, 'Passwords do not match.', error: true);
      return;
    }
    setState(() => _saving = true);
    try {
      await AppScope.of(context).changePassword(
        currentPassword: _currentPw.text,
        password: _newPw.text,
        passwordConfirmation: _confirmPw.text,
      );
      _currentPw.clear();
      _newPw.clear();
      _confirmPw.clear();
      if (!mounted) return;
      showDeliverySnack(context, 'Password changed.');
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _currentPw.dispose();
    _newPw.dispose();
    _confirmPw.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const DeliveryMobileHeader(title: 'Profile', showBack: true),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? DeliveryError(_error!, onRetry: _load)
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 24),
                  children: [
                    DeliveryPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${_user?['first_name'] ?? ''} ${_user?['last_name'] ?? ''}'
                                .trim(),
                            style: const TextStyle(
                              fontWeight: FontWeight.w900,
                              fontSize: 18,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _user?['mobile']?.toString() ?? '',
                            style: const TextStyle(
                              color: MiddoColors.inkSoft,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          if (_user?['email'] != null) ...[
                            const SizedBox(height: 2),
                            Text(
                              _user!['email'].toString(),
                              style: const TextStyle(
                                color: MiddoColors.muted,
                                fontSize: 13,
                              ),
                            ),
                          ],
                          const SizedBox(height: 8),
                          DeliveryStatusChip(
                            'Shift: ${_user?['rider_shift_status'] ?? 'on'}',
                            positive: true,
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Change password',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Current password',
                      controller: _currentPw,
                      obscureText: true,
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'New password',
                      controller: _newPw,
                      obscureText: true,
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Confirm password',
                      controller: _confirmPw,
                      obscureText: true,
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: _saving ? null : _changePassword,
                      child: Text(_saving ? 'Saving…' : 'Update password'),
                    ),
                  ],
                ),
    );
  }
}
