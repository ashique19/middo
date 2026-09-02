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
      _user =
          (res['user'] as Map?)?.cast<String, dynamic>() ?? _user;
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
                  padding: const EdgeInsets.all(16),
                  children: [
                    Text(
                      '${_user?['city'] ?? ''} · ${_user?['area'] ?? ''}',
                      style: const TextStyle(color: MiddoColors.inkSoft),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _first,
                      decoration:
                          const InputDecoration(labelText: 'First name'),
                    ),
                    TextField(
                      controller: _last,
                      decoration: const InputDecoration(labelText: 'Last name'),
                    ),
                    TextField(
                      controller: _mobile,
                      decoration: const InputDecoration(labelText: 'Mobile'),
                      keyboardType: TextInputType.phone,
                    ),
                    TextField(
                      controller: _email,
                      decoration: const InputDecoration(labelText: 'Email'),
                      keyboardType: TextInputType.emailAddress,
                    ),
                    TextField(
                      controller: _address,
                      decoration: const InputDecoration(labelText: 'Address'),
                      maxLines: 2,
                    ),
                    const SizedBox(height: 12),
                    FilledButton(
                      onPressed: _saving ? null : _saveProfile,
                      child: Text(_saving ? 'Saving…' : 'Save profile'),
                    ),
                    const SizedBox(height: 28),
                    Text(
                      'Change password',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    TextField(
                      controller: _currentPw,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Current password',
                      ),
                    ),
                    TextField(
                      controller: _newPw,
                      obscureText: true,
                      decoration:
                          const InputDecoration(labelText: 'New password'),
                    ),
                    TextField(
                      controller: _confirmPw,
                      obscureText: true,
                      decoration: const InputDecoration(
                        labelText: 'Confirm new password',
                      ),
                    ),
                    const SizedBox(height: 12),
                    OutlinedButton(
                      onPressed: _saving ? null : _changePassword,
                      child: const Text('Update password'),
                    ),
                  ],
                ),
    );
  }
}
