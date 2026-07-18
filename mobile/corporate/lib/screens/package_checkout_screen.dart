import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../app_scope.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

class PackageCheckoutScreen extends StatefulWidget {
  const PackageCheckoutScreen({super.key, required this.packageId});

  final String packageId;

  @override
  State<PackageCheckoutScreen> createState() => _PackageCheckoutScreenState();
}

class _PackageCheckoutScreenState extends State<PackageCheckoutScreen> {
  MealPackage? _package;
  PackageQuote? _quote;
  CorporateUser? _profile;
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  String? _debugOtp;

  final _omitted = <int>{5, 6};
  int _quantity = 1;
  final _nameCtrl = TextEditingController();
  final _mobileCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _otpCtrl = TextEditingController();
  int? _cityId;
  int? _areaId;
  bool _otpStep = false;

  static const _labels = {
    0: 'Sun',
    1: 'Mon',
    2: 'Tue',
    3: 'Wed',
    4: 'Thu',
    5: 'Fri',
    6: 'Sat',
  };

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_package == null) {
      _bootstrap();
    }
  }

  @override
  void dispose() {
    _nameCtrl.dispose();
    _mobileCtrl.dispose();
    _addressCtrl.dispose();
    _otpCtrl.dispose();
    super.dispose();
  }

  Future<void> _bootstrap() async {
    final repo = AppScope.of(context);
    try {
      final results = await Future.wait([
        repo.packageShow(widget.packageId),
        repo.me(),
      ]);
      final pkg = results[0] as MealPackage;
      final user = results[1] as CorporateUser;
      _package = pkg;
      _profile = user;
      _nameCtrl.text = user.receiverName;
      _mobileCtrl.text = user.mobile;
      _addressCtrl.text = user.address ?? '';
      _cityId = user.cityId;
      _areaId = user.areaId;
      await _refreshQuote();
      setState(() => _loading = false);
    } catch (e) {
      setState(() {
        _loading = false;
        _error = e.toString();
      });
    }
  }

  Future<void> _refreshQuote() async {
    final quote = await AppScope.of(context).packageQuote(
      packageId: widget.packageId,
      quantity: _quantity,
      omittedWeekdays: _omitted.toList()..sort(),
    );
    if (!mounted) return;
    setState(() => _quote = quote);
  }

  Future<void> _sendOtp() async {
    setState(() {
      _error = null;
      _submitting = true;
    });
    try {
      if (_cityId == null || _areaId == null) {
        throw Exception('Select city and area on your profile first.');
      }
      final otp = await AppScope.of(context).sendPackageOtp(
        mobile: _mobileCtrl.text.trim(),
      );
      setState(() {
        _otpStep = true;
        _debugOtp = otp;
        _submitting = false;
      });
    } catch (e) {
      setState(() {
        _error = e.toString();
        _submitting = false;
      });
    }
  }

  Future<void> _subscribe() async {
    setState(() {
      _error = null;
      _submitting = true;
    });
    try {
      final sub = await AppScope.of(context).subscribePackage(
        packageId: widget.packageId,
        quantity: _quantity,
        omittedWeekdays: _omitted.toList()..sort(),
        receiver: ReceiverDetails(
          receiverName: _nameCtrl.text.trim(),
          mobile: _mobileCtrl.text.trim(),
          address: _addressCtrl.text.trim(),
          cityId: _cityId!,
          areaId: _areaId!,
        ),
        otp: _otpCtrl.text.trim(),
      );
      if (!mounted) return;
      context.go('/subscriptions/${sub.id}');
    } catch (e) {
      setState(() {
        _error = e.toString();
        _submitting = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(body: MiddoPageLoader(message: 'Loading plan…'));
    }
    if (_package == null) {
      return Scaffold(body: Center(child: Text(_error ?? 'Not found')));
    }

    final quote = _quote;
    final balance = _profile?.balance ?? 0;

    return Scaffold(
      backgroundColor: MiddoColors.cream,
      appBar: AppBar(
        title: Text(_package!.name),
        backgroundColor: MiddoColors.cream,
        foregroundColor: MiddoColors.ink,
        elevation: 0,
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 120),
        children: [
          Text(
            'Omit weekdays (checked = skipped)',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final entry in _labels.entries)
                FilterChip(
                  label: Text(entry.value),
                  selected: _omitted.contains(entry.key),
                  onSelected: _otpStep
                      ? null
                      : (selected) async {
                          setState(() {
                            if (selected) {
                              _omitted.add(entry.key);
                            } else {
                              _omitted.remove(entry.key);
                            }
                          });
                          await _refreshQuote();
                        },
                ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              const Text('Qty / day', style: TextStyle(fontWeight: FontWeight.w800)),
              const Spacer(),
              IconButton(
                onPressed: _otpStep || _quantity <= 1
                    ? null
                    : () async {
                        setState(() => _quantity--);
                        await _refreshQuote();
                      },
                icon: const Icon(Icons.remove_circle_outline),
              ),
              Text('$_quantity', style: const TextStyle(fontWeight: FontWeight.w900)),
              IconButton(
                onPressed: _otpStep || _quantity >= 5
                    ? null
                    : () async {
                        setState(() => _quantity++);
                        await _refreshQuote();
                      },
                icon: const Icon(Icons.add_circle_outline),
              ),
            ],
          ),
          if (quote != null) ...[
            const SizedBox(height: 12),
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: MiddoColors.forest,
                borderRadius: BorderRadius.circular(18),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'TOTAL DUE NOW',
                    style: TextStyle(
                      color: Colors.white70,
                      fontWeight: FontWeight.w800,
                      fontSize: 11,
                    ),
                  ),
                  Text(
                    '৳${quote.totalAmount}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 28,
                    ),
                  ),
                  Text(
                    '${quote.billableDays} days × ৳${quote.pricePerDay} × qty $_quantity',
                    style: const TextStyle(color: Colors.white70),
                  ),
                  Text(
                    'Wallet: ৳${balance.toStringAsFixed(0)}',
                    style: const TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                ],
              ),
            ),
          ],
          const SizedBox(height: 18),
          TextField(
            controller: _nameCtrl,
            enabled: !_otpStep,
            decoration: const InputDecoration(labelText: 'Receiver name'),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _mobileCtrl,
            enabled: !_otpStep,
            decoration: const InputDecoration(labelText: 'Mobile'),
          ),
          const SizedBox(height: 10),
          TextField(
            controller: _addressCtrl,
            enabled: !_otpStep,
            decoration: const InputDecoration(labelText: 'Address'),
          ),
          if (_error != null) ...[
            const SizedBox(height: 12),
            Text(_error!, style: const TextStyle(color: Colors.red)),
          ],
          if (_otpStep) ...[
            const SizedBox(height: 14),
            TextField(
              controller: _otpCtrl,
              keyboardType: TextInputType.number,
              maxLength: 4,
              decoration: InputDecoration(
                labelText: 'OTP',
                helperText: _debugOtp != null ? 'Debug OTP: $_debugOtp' : null,
              ),
            ),
          ],
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
          child: FilledButton(
            onPressed: _submitting
                ? null
                : (_otpStep ? _subscribe : _sendOtp),
            style: FilledButton.styleFrom(
              backgroundColor: MiddoColors.orange,
              minimumSize: const Size.fromHeight(52),
            ),
            child: Text(
              _otpStep
                  ? 'Pay & activate · ৳${quote?.totalAmount ?? 0}'
                  : 'Confirm & send OTP',
            ),
          ),
        ),
      ),
    );
  }
}
