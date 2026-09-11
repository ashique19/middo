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
  final _email = TextEditingController();
  final _bkash = TextEditingController();
  final _nagad = TextEditingController();
  final _bankName = TextEditingController();
  final _bankCity = TextEditingController();
  final _bankBranch = TextEditingController();
  final _bankAccountName = TextEditingController();
  final _bankAccountNumber = TextEditingController();

  Map<String, dynamic>? _user;
  String _preferred = 'bkash';
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
      _email.text = _user?['email']?.toString() ?? '';
      _preferred =
          (_user?['preferred_payout_channel'] ?? 'bkash').toString();
      final methods =
          (_user?['payout_methods'] as Map?)?.cast<String, dynamic>() ?? {};
      final bank = (methods['bank'] as Map?)?.cast<String, dynamic>() ?? {};
      final bkash = (methods['bkash'] as Map?)?.cast<String, dynamic>() ?? {};
      final nagad = (methods['nagad'] as Map?)?.cast<String, dynamic>() ?? {};
      _bankName.text = bank['bank_name']?.toString() ?? '';
      _bankCity.text = bank['city']?.toString() ?? '';
      _bankBranch.text = bank['branch']?.toString() ?? '';
      _bankAccountName.text = bank['account_name']?.toString() ?? '';
      _bankAccountNumber.text = bank['account_number']?.toString() ?? '';
      _bkash.text = bkash['mobile']?.toString() ?? '';
      _nagad.text = nagad['mobile']?.toString() ?? '';
    } catch (e) {
      _error = '$e';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _savePayout() async {
    setState(() => _saving = true);
    try {
      await AppScope.of(context).updateProfile({
        'email': _email.text.trim().isEmpty ? null : _email.text.trim(),
        'preferred_payout_channel': _preferred,
        'payout_methods': {
          'preferred': _preferred,
          'bank': {
            'bank_name': _bankName.text.trim(),
            'city': _bankCity.text.trim(),
            'branch': _bankBranch.text.trim(),
            'account_name': _bankAccountName.text.trim(),
            'account_number': _bankAccountNumber.text.trim(),
          },
          'bkash': {'mobile': _bkash.text.trim()},
          'nagad': {'mobile': _nagad.text.trim()},
        },
      });
      if (!mounted) return;
      showDeliverySnack(context, 'Payout profile saved.');
      await _load();
    } on ApiException catch (e) {
      if (mounted) showDeliverySnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
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
    _email.dispose();
    _bkash.dispose();
    _nagad.dispose();
    _bankName.dispose();
    _bankCity.dispose();
    _bankBranch.dispose();
    _bankAccountName.dispose();
    _bankAccountNumber.dispose();
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
                          const SizedBox(height: 8),
                          DeliveryStatusChip(
                            (_user?['rider_shift_status']?.toString() ??
                                        'on') ==
                                    'on'
                                ? 'On shift'
                                : 'Off shift',
                            positive: (_user?['rider_shift_status']
                                        ?.toString() ??
                                    'on') ==
                                'on',
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 20),
                    const Text(
                      'Payout method',
                      style: TextStyle(
                        fontWeight: FontWeight.w900,
                        fontSize: 16,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Required before withdrawing wallet balance.',
                      style: TextStyle(
                        color: MiddoColors.muted,
                        fontSize: 13,
                      ),
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Email (optional)',
                      controller: _email,
                    ),
                    const SizedBox(height: 12),
                    InputDecorator(
                      decoration: const InputDecoration(
                        labelText: 'Preferred channel',
                        border: OutlineInputBorder(),
                      ),
                      child: DropdownButtonHideUnderline(
                        child: DropdownButton<String>(
                          value: _preferred,
                          isExpanded: true,
                          items: const [
                            DropdownMenuItem(
                                value: 'bkash', child: Text('bKash')),
                            DropdownMenuItem(
                                value: 'nagad', child: Text('Nagad')),
                            DropdownMenuItem(
                                value: 'bank', child: Text('Bank')),
                          ],
                          onChanged: _saving
                              ? null
                              : (v) {
                                  if (v != null) {
                                    setState(() => _preferred = v);
                                  }
                                },
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'bKash mobile',
                      controller: _bkash,
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Nagad mobile',
                      controller: _nagad,
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Bank name',
                      controller: _bankName,
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Bank city',
                      controller: _bankCity,
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Bank branch',
                      controller: _bankBranch,
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Account name',
                      controller: _bankAccountName,
                    ),
                    const SizedBox(height: 12),
                    DeliveryDialogField(
                      label: 'Account number',
                      controller: _bankAccountNumber,
                    ),
                    const SizedBox(height: 16),
                    FilledButton(
                      onPressed: _saving ? null : _savePayout,
                      child: Text(_saving ? 'Saving…' : 'Save payout profile'),
                    ),
                    const SizedBox(height: 28),
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
