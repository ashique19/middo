import 'package:flutter/material.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../theme/middo_colors.dart';
import '../widgets/kitchen_mobile_header.dart';
import '../widgets/kitchen_ui.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _first = TextEditingController();
  final _last = TextEditingController();
  final _mobile = TextEditingController();
  final _email = TextEditingController();
  final _address = TextEditingController();
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
      final user =
          (data['user'] as Map?)?.cast<String, dynamic>() ??
              data.cast<String, dynamic>();
      _user = user;
      _first.text = user['first_name']?.toString() ?? '';
      _last.text = user['last_name']?.toString() ?? '';
      _mobile.text = user['mobile']?.toString() ?? '';
      _email.text = user['email']?.toString() ?? '';
      _address.text = user['address']?.toString() ?? '';
    } catch (e) {
      _error = '$e';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _saveProfile() async {
    final user = _user;
    if (user == null) return;
    final cityId = user['city_id'];
    final areaId = user['area_id'];
    if (cityId == null || areaId == null) {
      showKitchenSnack(
        context,
        'City/area missing on profile — update via web if needed.',
        error: true,
      );
      return;
    }
    setState(() => _saving = true);
    try {
      final body = <String, dynamic>{
        'first_name': _first.text.trim(),
        'last_name': _last.text.trim(),
        'mobile': _mobile.text.trim(),
        'city_id': cityId,
        'area_id': areaId,
      };
      final email = _email.text.trim();
      final address = _address.text.trim();
      if (email.isNotEmpty) body['email'] = email;
      if (address.isNotEmpty) body['address'] = address;
      final res = await AppScope.of(context).updateProfile(body);
      _user = (res['user'] as Map?)?.cast<String, dynamic>() ?? _user;
      if (!mounted) return;
      showKitchenSnack(context, res['message']?.toString() ?? 'Saved.');
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _changePassword() async {
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
      showKitchenSnack(context, 'Password changed.');
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  void dispose() {
    _first.dispose();
    _last.dispose();
    _mobile.dispose();
    _email.dispose();
    _address.dispose();
    _currentPw.dispose();
    _newPw.dispose();
    _confirmPw.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: const KitchenMobileHeader(title: 'Kitchen profile', showBack: true),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? KitchenError(_error!, onRetry: _load)
              : ListView(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                  keyboardDismissBehavior: ScrollViewKeyboardDismissBehavior.onDrag,
                  children: [
                    Text(
                      'Contact details and password. Tier and capacity are managed by Middo.',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: MiddoColors.inkSoft,
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                    const SizedBox(height: 12),
                    KitchenPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            '${_user?['city'] ?? '—'} · ${_user?['area'] ?? '—'}',
                            style: const TextStyle(
                              color: MiddoColors.inkSoft,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 16),
                          const Text(
                            'Contact',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                            ),
                          ),
                          const SizedBox(height: 12),
                          Row(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Expanded(
                                child: _ProfileField(
                                  label: 'First name',
                                  controller: _first,
                                  enabled: !_saving,
                                  textCapitalization: TextCapitalization.words,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: _ProfileField(
                                  label: 'Last name',
                                  controller: _last,
                                  enabled: !_saving,
                                  textCapitalization: TextCapitalization.words,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          _ProfileField(
                            label: 'Mobile',
                            controller: _mobile,
                            enabled: !_saving,
                            keyboardType: TextInputType.phone,
                          ),
                          const SizedBox(height: 12),
                          _ProfileField(
                            label: 'Email',
                            controller: _email,
                            enabled: !_saving,
                            keyboardType: TextInputType.emailAddress,
                          ),
                          const SizedBox(height: 12),
                          _ProfileField(
                            label: 'Address',
                            controller: _address,
                            enabled: !_saving,
                            maxLines: 2,
                            textCapitalization: TextCapitalization.sentences,
                          ),
                          const SizedBox(height: 16),
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton(
                              onPressed: _saving ? null : _saveProfile,
                              child: Text(_saving ? 'Saving…' : 'Save profile'),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                    KitchenPanel(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Text(
                            'Change password',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _ProfileField(
                            label: 'Current password',
                            controller: _currentPw,
                            enabled: !_saving,
                            obscureText: true,
                          ),
                          const SizedBox(height: 12),
                          _ProfileField(
                            label: 'New password',
                            controller: _newPw,
                            enabled: !_saving,
                            obscureText: true,
                          ),
                          const SizedBox(height: 12),
                          _ProfileField(
                            label: 'Confirm new password',
                            controller: _confirmPw,
                            enabled: !_saving,
                            obscureText: true,
                          ),
                          const SizedBox(height: 16),
                          SizedBox(
                            width: double.infinity,
                            child: OutlinedButton(
                              onPressed: _saving ? null : _changePassword,
                              child: const Text('Update password'),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
    );
  }
}

class _ProfileField extends StatelessWidget {
  const _ProfileField({
    required this.label,
    required this.controller,
    this.enabled = true,
    this.obscureText = false,
    this.keyboardType,
    this.maxLines = 1,
    this.textCapitalization = TextCapitalization.none,
  });

  final String label;
  final TextEditingController controller;
  final bool enabled;
  final bool obscureText;
  final TextInputType? keyboardType;
  final int maxLines;
  final TextCapitalization textCapitalization;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label.toUpperCase(),
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w800,
            letterSpacing: 0.8,
            color: MiddoColors.muted,
          ),
        ),
        const SizedBox(height: 6),
        TextField(
          controller: controller,
          enabled: enabled,
          obscureText: obscureText,
          keyboardType: keyboardType,
          maxLines: maxLines,
          textCapitalization: textCapitalization,
          decoration: InputDecoration(
            isDense: true,
            hintText: label,
            floatingLabelBehavior: FloatingLabelBehavior.never,
            contentPadding: const EdgeInsets.symmetric(
              horizontal: 12,
              vertical: 12,
            ),
          ),
        ),
      ],
    );
  }
}
