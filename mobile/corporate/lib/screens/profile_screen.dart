import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _mobile = TextEditingController();
  final _email = TextEditingController();
  final _address = TextEditingController();

  CorporateUser? _user;
  List<LocationCity> _cities = const [];
  int? _cityId;
  int? _areaId;
  bool _loading = true;
  bool _editing = false;
  bool _saving = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  @override
  void dispose() {
    _firstName.dispose();
    _lastName.dispose();
    _mobile.dispose();
    _email.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final repo = AppScope.of(context);
    try {
      final results = await Future.wait([
        repo.me(),
        repo.locations(),
      ]);
      final user = results[0] as CorporateUser;
      final cities = results[1] as List<LocationCity>;
      if (!mounted) return;
      _applyUser(user, cities);
      setState(() {
        _loading = false;
        _error = null;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  void _applyUser(CorporateUser user, [List<LocationCity>? cities]) {
    _user = user;
    if (cities != null) _cities = cities;
    _firstName.text = user.firstName ?? '';
    _lastName.text = user.lastName ?? '';
    _mobile.text = user.mobile;
    _email.text = user.email ?? '';
    _address.text = user.address ?? '';

    LocationCity? city;
    if (_cities.isNotEmpty) {
      city = _cities.where((c) => c.id == user.cityId).firstOrNull ??
          _cities.first;
    }
    _cityId = city?.id ?? user.cityId;
    _areaId = city == null
        ? user.areaId
        : (city.areas.where((a) => a.id == user.areaId).firstOrNull?.id ??
            (city.areas.isNotEmpty ? city.areas.first.id : user.areaId));
  }

  List<LocationArea> get _areas {
    final city = _cities.where((c) => c.id == _cityId).firstOrNull;
    return city?.areas ?? const [];
  }

  Future<void> _save() async {
    final first = _firstName.text.trim();
    final last = _lastName.text.trim();
    final mobile = _mobile.text.trim();
    final email = _email.text.trim();
    final address = _address.text.trim();

    if (first.length < 2 || last.length < 2) {
      setState(() => _error = 'Enter first and last name.');
      return;
    }
    if (!RegExp(r'^01[3-9]\d{8}$').hasMatch(mobile)) {
      setState(() => _error = 'Enter a valid 11-digit BD mobile.');
      return;
    }
    if (_cityId == null || _areaId == null) {
      setState(() => _error = 'Select city and area.');
      return;
    }

    setState(() {
      _saving = true;
      _error = null;
    });
    try {
      final updated = await AppScope.of(context).updateProfile(
        firstName: first,
        lastName: last,
        mobile: mobile,
        email: email.isEmpty ? null : email,
        address: address.isEmpty ? null : address,
        cityId: _cityId!,
        areaId: _areaId!,
      );
      if (!mounted) return;
      setState(() {
        _applyUser(updated);
        _editing = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Profile updated'),
          backgroundColor: MiddoColors.forest,
        ),
      );
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
    if (_loading) {
      return const MiddoLoadingScaffold(
        title: 'Profile',
        message: 'Loading profile…',
      );
    }

    final user = _user;
    if (user == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Profile')),
        body: Center(child: Text(_error ?? 'Unable to load profile')),
      );
    }

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.pop(),
        ),
        title: const Text('Profile'),
        actions: [
          if (!_editing)
            TextButton(
              onPressed: () => setState(() {
                _editing = true;
                _error = null;
              }),
              child: const Text('Edit'),
            ),
        ],
      ),
      body: Stack(
        children: [
          ListView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 32),
            children: [
              Row(
                children: [
                  CircleAvatar(
                    radius: 28,
                    backgroundColor: MiddoColors.amberSoft,
                    foregroundColor: MiddoColors.orange,
                    child: Text(
                      user.initial,
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        fontSize: 22,
                      ),
                    ),
                  ),
                  const SizedBox(width: 14),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          user.companyName,
                          style: const TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                        Text(
                          'Balance ${bdt.format(user.balance)}',
                          style: const TextStyle(
                            fontSize: 12,
                            fontWeight: FontWeight.w700,
                            color: MiddoColors.inkSoft,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 20),
              if (!_editing) ...[
                _InfoRow(label: 'Name', value: user.receiverName),
                _InfoRow(label: 'Mobile', value: user.mobile),
                _InfoRow(label: 'Email', value: user.email?.isNotEmpty == true ? user.email! : '—'),
                _InfoRow(label: 'Address', value: user.address?.isNotEmpty == true ? user.address! : '—'),
                _InfoRow(
                  label: 'Location',
                  value: [
                    if (user.area != null && user.area!.isNotEmpty) user.area,
                    if (user.city != null && user.city!.isNotEmpty) user.city,
                  ].whereType<String>().join(', ').ifEmpty('—'),
                ),
                const SizedBox(height: 20),
                FilledButton(
                  onPressed: () => setState(() {
                    _editing = true;
                    _error = null;
                  }),
                  child: const Text('Edit profile'),
                ),
                const SizedBox(height: 10),
                OutlinedButton(
                  onPressed: () => context.push('/profile/password'),
                  child: const Text('Change password'),
                ),
              ] else ...[
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _firstName,
                        enabled: !_saving,
                        textCapitalization: TextCapitalization.words,
                        decoration:
                            const InputDecoration(labelText: 'FIRST NAME'),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextField(
                        controller: _lastName,
                        enabled: !_saving,
                        textCapitalization: TextCapitalization.words,
                        decoration:
                            const InputDecoration(labelText: 'LAST NAME'),
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _mobile,
                  enabled: !_saving,
                  keyboardType: TextInputType.phone,
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(11),
                  ],
                  decoration: const InputDecoration(labelText: 'MOBILE'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _email,
                  enabled: !_saving,
                  keyboardType: TextInputType.emailAddress,
                  decoration: const InputDecoration(labelText: 'EMAIL'),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _address,
                  enabled: !_saving,
                  maxLines: 2,
                  textCapitalization: TextCapitalization.sentences,
                  decoration: const InputDecoration(
                    labelText: 'ADDRESS',
                    alignLabelWithHint: true,
                  ),
                ),
                const SizedBox(height: 12),
                if (_cities.isNotEmpty) ...[
                  DropdownButtonFormField<int>(
                    value: _cityId,
                    decoration: const InputDecoration(labelText: 'CITY'),
                    items: _cities
                        .map(
                          (c) => DropdownMenuItem(
                            value: c.id,
                            child: Text(c.name),
                          ),
                        )
                        .toList(),
                    onChanged: _saving
                        ? null
                        : (value) {
                            if (value == null) return;
                            final city =
                                _cities.firstWhere((c) => c.id == value);
                            setState(() {
                              _cityId = value;
                              _areaId = city.areas.isNotEmpty
                                  ? city.areas.first.id
                                  : null;
                            });
                          },
                  ),
                  const SizedBox(height: 12),
                  DropdownButtonFormField<int>(
                    value: _areaId != null &&
                            _areas.any((a) => a.id == _areaId)
                        ? _areaId
                        : (_areas.isNotEmpty ? _areas.first.id : null),
                    decoration: const InputDecoration(labelText: 'AREA'),
                    items: _areas
                        .map(
                          (a) => DropdownMenuItem(
                            value: a.id,
                            child: Text(a.name),
                          ),
                        )
                        .toList(),
                    onChanged: _saving || _areas.isEmpty
                        ? null
                        : (value) => setState(() => _areaId = value),
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
                  onPressed: _saving ? null : _save,
                  child: Text(_saving ? 'Saving…' : 'Save changes'),
                ),
                const SizedBox(height: 8),
                TextButton(
                  onPressed: _saving
                      ? null
                      : () {
                          _applyUser(user);
                          setState(() {
                            _editing = false;
                            _error = null;
                          });
                        },
                  child: const Text('Cancel'),
                ),
              ],
            ],
          ),
          if (_saving)
            const ColoredBox(
              color: Color(0x66F7F4EB),
              child: MiddoPageLoader(message: 'Saving profile…'),
            ),
        ],
      ),
    );
  }
}

class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label.toUpperCase(),
            style: const TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: MiddoColors.muted,
              letterSpacing: 0.5,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

extension on String {
  String ifEmpty(String fallback) => isEmpty ? fallback : this;
}
