import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/push_notification_service.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class SignupScreen extends StatefulWidget {
  const SignupScreen({super.key});

  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  final _company = TextEditingController();
  final _firstName = TextEditingController();
  final _lastName = TextEditingController();
  final _mobile = TextEditingController();
  final _password = TextEditingController();
  final _confirm = TextEditingController();
  final _address = TextEditingController();

  List<LocationCity> _cities = const [];
  int? _cityId;
  int? _areaId;
  bool _loadingLocations = true;
  bool _submitting = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _loadLocations();
  }

  @override
  void dispose() {
    _company.dispose();
    _firstName.dispose();
    _lastName.dispose();
    _mobile.dispose();
    _password.dispose();
    _confirm.dispose();
    _address.dispose();
    super.dispose();
  }

  Future<void> _loadLocations() async {
    try {
      final cities = await AppScope.of(context).locations();
      if (!mounted) return;
      setState(() {
        _cities = cities;
        if (cities.isNotEmpty) {
          _cityId = cities.first.id;
          _areaId = cities.first.areas.isNotEmpty
              ? cities.first.areas.first.id
              : null;
        }
        _loadingLocations = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loadingLocations = false;
      });
    }
  }

  List<LocationArea> get _areas {
    final city = _cities.where((c) => c.id == _cityId).firstOrNull;
    return city?.areas ?? const [];
  }

  Future<void> _submit() async {
    final company = _company.text.trim();
    final first = _firstName.text.trim();
    final last = _lastName.text.trim();
    final mobile = _mobile.text.trim();
    final password = _password.text;
    final confirm = _confirm.text;
    final address = _address.text.trim();

    if (company.length < 4) {
      setState(() => _error = 'Enter your company name (at least 4 characters).');
      return;
    }
    if (first.length < 2 || last.length < 2) {
      setState(() => _error = 'Enter contact first and last name.');
      return;
    }
    if (!RegExp(r'^01[3-9]\d{8}$').hasMatch(mobile)) {
      setState(() => _error = 'Enter a valid 11-digit BD mobile (01XXXXXXXXX).');
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
    if (address.length < 10) {
      setState(() => _error = 'Enter a full office address (at least 10 characters).');
      return;
    }
    if (_cityId == null || _areaId == null) {
      setState(() => _error = 'Select city and area.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      await AppScope.of(context).register(
        firstName: first,
        lastName: last,
        mobile: mobile,
        password: password,
        passwordConfirmation: confirm,
        companyName: company,
        address: address,
        cityId: _cityId!,
        areaId: _areaId!,
      );
      await PushNotificationService.instance.syncWithBackend();
      if (!mounted) return;
      context.go('/home');
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
    if (_loadingLocations) {
      return const MiddoLoadingScaffold(
        title: 'Create account',
        message: 'Loading signup…',
      );
    }

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: () => context.go('/login'),
        ),
        title: const Text('Create account'),
      ),
      body: Stack(
        children: [
          ListView(
            padding: const EdgeInsets.fromLTRB(18, 8, 18, 32),
            children: [
              Text(
                'Join Middo Corporate',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.5,
                    ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Schedule office lunches and manage Middo Boxes for your team.',
                style: TextStyle(
                  fontWeight: FontWeight.w600,
                  color: MiddoColors.inkSoft,
                  fontSize: 13,
                ),
              ),
              const SizedBox(height: 18),
              TextField(
                controller: _company,
                textCapitalization: TextCapitalization.words,
                enabled: !_submitting,
                decoration: const InputDecoration(labelText: 'COMPANY NAME'),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: TextField(
                      controller: _firstName,
                      textCapitalization: TextCapitalization.words,
                      enabled: !_submitting,
                      decoration: const InputDecoration(labelText: 'FIRST NAME'),
                    ),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: TextField(
                      controller: _lastName,
                      textCapitalization: TextCapitalization.words,
                      enabled: !_submitting,
                      decoration: const InputDecoration(labelText: 'LAST NAME'),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
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
              const SizedBox(height: 12),
              TextField(
                controller: _password,
                obscureText: true,
                enabled: !_submitting,
                decoration: const InputDecoration(labelText: 'PASSWORD'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _confirm,
                obscureText: true,
                enabled: !_submitting,
                decoration:
                    const InputDecoration(labelText: 'CONFIRM PASSWORD'),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: _address,
                maxLines: 2,
                enabled: !_submitting,
                textCapitalization: TextCapitalization.sentences,
                decoration: const InputDecoration(
                  labelText: 'OFFICE ADDRESS',
                  alignLabelWithHint: true,
                ),
              ),
              const SizedBox(height: 12),
              if (_cities.isEmpty)
                const Text(
                  'No delivery cities available. Contact Middo support.',
                  style: TextStyle(
                    color: MiddoColors.orangeDeep,
                    fontWeight: FontWeight.w700,
                  ),
                )
              else ...[
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
                  onChanged: _submitting
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
                  onChanged: _submitting || _areas.isEmpty
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
                onPressed: _submitting ? null : _submit,
                child: Text(_submitting ? 'Creating account…' : 'Create account'),
              ),
              const SizedBox(height: 10),
              TextButton(
                onPressed: _submitting ? null : () => context.go('/login'),
                child: const Text('Already have an account? Sign in'),
              ),
            ],
          ),
          if (_submitting)
            const ColoredBox(
              color: Color(0x66F7F4EB),
              child: MiddoPageLoader(message: 'Creating your account…'),
            ),
        ],
      ),
    );
  }
}
