import 'dart:io';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

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
  final _nid = TextEditingController();
  final _currentPw = TextEditingController();
  final _newPw = TextEditingController();
  final _confirmPw = TextEditingController();

  Map<String, dynamic>? _user;
  bool _loading = true;
  bool _saving = false;
  String? _error;
  XFile? _nidFront;
  XFile? _nidBack;
  XFile? _selfie;

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
      _nid.text = user['nid_number']?.toString() ?? '';
      _nidFront = null;
      _nidBack = null;
      _selfie = null;
    } catch (e) {
      _error = '$e';
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _saveProfile() async {
    setState(() => _saving = true);
    try {
      final email = _email.text.trim();
      final res = await AppScope.of(context).updateProfile({
        'email': email.isEmpty ? null : email,
      });
      _user = (res['user'] as Map?)?.cast<String, dynamic>() ?? _user;
      if (!mounted) return;
      showKitchenSnack(context, res['message']?.toString() ?? 'Saved.');
    } on ApiException catch (e) {
      if (mounted) showKitchenSnack(context, e.message, error: true);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<XFile?> _pickImage() async {
    final source = await showModalBottomSheet<ImageSource>(
      context: context,
      builder: (ctx) => SafeArea(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_camera_outlined),
              title: const Text('Camera'),
              onTap: () => Navigator.pop(ctx, ImageSource.camera),
            ),
            ListTile(
              leading: const Icon(Icons.photo_library_outlined),
              title: const Text('Gallery'),
              onTap: () => Navigator.pop(ctx, ImageSource.gallery),
            ),
          ],
        ),
      ),
    );
    if (source == null) return null;
    return ImagePicker().pickImage(
      source: source,
      maxWidth: 960,
      maxHeight: 960,
      imageQuality: 52,
    );
  }

  Future<void> _saveVerification() async {
    setState(() => _saving = true);
    try {
      final res = await AppScope.of(context).updateVerification(
        nidNumber: _nid.text.trim(),
        clearNidNumber: _nid.text.trim().isEmpty,
        nidFrontPath: _nidFront?.path,
        nidBackPath: _nidBack?.path,
        selfiePath: _selfie?.path,
      );
      _user = (res['user'] as Map?)?.cast<String, dynamic>() ?? _user;
      _nidFront = null;
      _nidBack = null;
      _selfie = null;
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
    _nid.dispose();
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
                      'Email and verification photos. Name, address, and phone are managed by Middo admin.',
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
                          Row(
                            children: [
                              _PhotoPreview(
                                url: _user?['profile_photo_url']?.toString(),
                                file: _selfie,
                                label: _first.text.isEmpty
                                    ? 'K'
                                    : _first.text.substring(0, 1),
                              ),
                              const SizedBox(width: 12),
                              const Expanded(
                                child: Text(
                                  'Your chef selfie is the profile photo ops and riders see.',
                                  style: TextStyle(
                                    color: MiddoColors.inkSoft,
                                    fontWeight: FontWeight.w600,
                                  ),
                                ),
                              ),
                            ],
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
                            'NID and selfie',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                            ),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'Photos are resized before upload. Camera or gallery.',
                            style: TextStyle(
                              color: MiddoColors.inkSoft,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 12),
                          _ProfileField(
                            label: 'NID number',
                            controller: _nid,
                            enabled: !_saving,
                            keyboardType: TextInputType.number,
                          ),
                          const SizedBox(height: 12),
                          _PhotoSlot(
                            title: 'NID front',
                            url: _user?['nid_front_url']?.toString(),
                            file: _nidFront,
                            busy: _saving,
                            onPick: () async {
                              final file = await _pickImage();
                              if (file != null) setState(() => _nidFront = file);
                            },
                          ),
                          _PhotoSlot(
                            title: 'NID back',
                            url: _user?['nid_back_url']?.toString(),
                            file: _nidBack,
                            busy: _saving,
                            onPick: () async {
                              final file = await _pickImage();
                              if (file != null) setState(() => _nidBack = file);
                            },
                          ),
                          _PhotoSlot(
                            title: 'Chef selfie',
                            url: _user?['profile_photo_url']?.toString(),
                            file: _selfie,
                            busy: _saving,
                            onPick: () async {
                              final file = await _pickImage();
                              if (file != null) setState(() => _selfie = file);
                            },
                          ),
                          const SizedBox(height: 8),
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton(
                              onPressed: _saving ? null : _saveVerification,
                              child: Text(
                                _saving ? 'Saving…' : 'Save verification',
                              ),
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
                          const SizedBox(height: 4),
                          const Text(
                            'Name, phone, and address are read-only.',
                            style: TextStyle(
                              color: MiddoColors.inkSoft,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
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
                                  enabled: false,
                                  textCapitalization: TextCapitalization.words,
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: _ProfileField(
                                  label: 'Last name',
                                  controller: _last,
                                  enabled: false,
                                  textCapitalization: TextCapitalization.words,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 12),
                          _ProfileField(
                            label: 'Mobile',
                            controller: _mobile,
                            enabled: false,
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
                            enabled: false,
                            maxLines: 2,
                            textCapitalization: TextCapitalization.sentences,
                          ),
                          const SizedBox(height: 16),
                          SizedBox(
                            width: double.infinity,
                            child: FilledButton(
                              onPressed: _saving ? null : _saveProfile,
                              child: Text(_saving ? 'Saving…' : 'Save email'),
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
                            'Weekly hours',
                            style: TextStyle(
                              fontWeight: FontWeight.w800,
                              fontSize: 16,
                            ),
                          ),
                          const SizedBox(height: 4),
                          const Text(
                            'Shown to Middo ops. Edit open/close on the web kitchen profile if you need to change them.',
                            style: TextStyle(
                              color: MiddoColors.inkSoft,
                              fontSize: 12,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                          const SizedBox(height: 12),
                          for (final raw
                              in (_user?['hours'] as List?) ?? const [])
                            Padding(
                              padding: const EdgeInsets.only(bottom: 8),
                              child: Row(
                                children: [
                                  SizedBox(
                                    width: 100,
                                    child: Text(
                                      (raw as Map)['day_label']?.toString() ??
                                          '',
                                      style: const TextStyle(
                                        fontWeight: FontWeight.w700,
                                      ),
                                    ),
                                  ),
                                  Expanded(
                                    child: Text(
                                      raw['label']?.toString() ??
                                          (raw['is_closed'] == true
                                              ? 'Closed'
                                              : '${raw['opens_at']} – ${raw['closes_at']}'),
                                      style: TextStyle(
                                        fontWeight: FontWeight.w600,
                                        color: raw['is_closed'] == true
                                            ? MiddoColors.orange
                                            : MiddoColors.inkSoft,
                                      ),
                                    ),
                                  ),
                                ],
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

class _PhotoPreview extends StatelessWidget {
  const _PhotoPreview({
    required this.label,
    this.url,
    this.file,
  });

  final String label;
  final String? url;
  final XFile? file;

  @override
  Widget build(BuildContext context) {
    Widget fallback() => CircleAvatar(
          radius: 28,
          backgroundColor: MiddoColors.orange.withValues(alpha: 0.12),
          child: Text(
            label,
            style: const TextStyle(
              color: MiddoColors.orange,
              fontWeight: FontWeight.w900,
            ),
          ),
        );

    if (file != null) {
      return ClipOval(
        child: Image.file(
          File(file!.path),
          width: 56,
          height: 56,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => fallback(),
        ),
      );
    }
    final remote = url?.trim();
    if (remote != null && remote.isNotEmpty) {
      return ClipOval(
        child: Image.network(
          remote,
          width: 56,
          height: 56,
          fit: BoxFit.cover,
          errorBuilder: (_, __, ___) => fallback(),
        ),
      );
    }
    return fallback();
  }
}

class _PhotoSlot extends StatelessWidget {
  const _PhotoSlot({
    required this.title,
    required this.busy,
    required this.onPick,
    this.url,
    this.file,
  });

  final String title;
  final String? url;
  final XFile? file;
  final bool busy;
  final VoidCallback onPick;

  @override
  Widget build(BuildContext context) {
    final remote = url?.trim();
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Row(
        children: [
          _PhotoPreview(
            label: title.substring(0, 1),
            url: remote,
            file: file,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
                Text(
                  file != null
                      ? 'New photo selected'
                      : (remote != null && remote.isNotEmpty)
                          ? 'Saved'
                          : 'Not uploaded',
                  style: const TextStyle(
                    color: MiddoColors.inkSoft,
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          OutlinedButton(
            onPressed: busy ? null : onPick,
            child: const Text('Photo'),
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
