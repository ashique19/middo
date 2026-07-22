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
  List<MenuItem> _menus = const [];
  PackageQuote? _quote;
  CorporateUser? _profile;
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  String? _debugOtp;

  final _omitted = <int>{5, 6};
  final _menuDays = <int, int>{};
  late String _targetMonth;
  late List<({String value, String label})> _monthOptions;
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
  void initState() {
    super.initState();
    final now = DateTime.now();
    _monthOptions = List.generate(4, (i) {
      final d = DateTime(now.year, now.month + i, 1);
      final value =
          '${d.year.toString().padLeft(4, '0')}-${d.month.toString().padLeft(2, '0')}';
      const months = [
        'Jan',
        'Feb',
        'Mar',
        'Apr',
        'May',
        'Jun',
        'Jul',
        'Aug',
        'Sep',
        'Oct',
        'Nov',
        'Dec',
      ];
      return (value: value, label: '${months[d.month - 1]} ${d.year}');
    });
    _targetMonth = _monthOptions.first.value;
  }

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

  List<PackageMenuSelection> get _selections => _menuDays.entries
      .where((e) => e.value > 0)
      .map(
        (e) => PackageMenuSelection(menuItemId: e.key, dayCount: e.value),
      )
      .toList();

  Future<void> _bootstrap() async {
    final repo = AppScope.of(context);
    try {
      final results = await Future.wait([
        repo.packageShow(widget.packageId),
        repo.packageMenus(widget.packageId),
        repo.me(),
      ]);
      final pkg = results[0] as MealPackage;
      final menus = results[1] as List<MenuItem>;
      final user = results[2] as CorporateUser;
      _package = pkg;
      _menus = menus;
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
    if (_selections.isEmpty) {
      setState(() => _quote = null);
      return;
    }
    final quote = await AppScope.of(context).packageQuote(
      packageId: widget.packageId,
      quantity: _quantity,
      omittedWeekdays: _omitted.toList()..sort(),
      targetMonth: _targetMonth,
      menuSelections: _selections,
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
      if (_selections.isEmpty) {
        throw Exception('Pick menus for every working day this month.');
      }
      if (_quote == null ||
          _quote!.billableDays != _quote!.availableDays ||
          _quote!.availableDays < 1) {
        throw Exception(
          'Select menus for all ${_quote?.availableDays ?? 0} working days this month.',
        );
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
        targetMonth: _targetMonth,
        menuSelections: _selections,
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

  int _menuIdAsInt(MenuItem menu) => int.tryParse(menu.id) ?? 0;

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
            'Build monthly package',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
          DropdownButtonFormField<String>(
            value: _targetMonth,
            decoration: const InputDecoration(labelText: 'Target month'),
            items: [
              for (final option in _monthOptions)
                DropdownMenuItem(
                  value: option.value,
                  child: Text(option.label),
                ),
            ],
            onChanged: _otpStep
                ? null
                : (value) async {
                    if (value == null) return;
                    setState(() => _targetMonth = value);
                    await _refreshQuote();
                  },
          ),
          const SizedBox(height: 16),
          Text(
            'Omit weekdays / off-days',
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
          if (quote != null) ...[
            const SizedBox(height: 8),
            Text(
              'Available days this month: ${quote.availableDays}',
              style: const TextStyle(
                color: MiddoColors.muted,
                fontWeight: FontWeight.w600,
                fontSize: 12,
              ),
            ),
          ],
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
          const SizedBox(height: 12),
          Text(
            'Menus & day counts',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 8),
          Text(
            'Choose menus so the total equals all working days this month.',
            style: TextStyle(
              color: MiddoColors.muted,
              fontWeight: FontWeight.w600,
              fontSize: 12,
            ),
          ),
          const SizedBox(height: 8),
          for (final menu in _menus)
            Builder(
              builder: (context) {
                final id = _menuIdAsInt(menu);
                final count = _menuDays[id] ?? 0;
                final selectedTotal =
                    _menuDays.values.fold<int>(0, (sum, days) => sum + days);
                final workingDays = quote?.availableDays ?? selectedTotal;
                final canIncrease =
                    !_otpStep && (workingDays == 0 || selectedTotal < workingDays);
                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text(
                      menu.name,
                      style: const TextStyle(fontWeight: FontWeight.w700),
                    ),
                    trailing: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        IconButton(
                          onPressed: _otpStep || count <= 0
                              ? null
                              : () async {
                                  setState(() {
                                    if (count <= 1) {
                                      _menuDays.remove(id);
                                    } else {
                                      _menuDays[id] = count - 1;
                                    }
                                  });
                                  await _refreshQuote();
                                },
                          icon: const Icon(Icons.remove_circle_outline),
                        ),
                        Text(
                          '$count',
                          style: const TextStyle(fontWeight: FontWeight.w900),
                        ),
                        IconButton(
                          onPressed: !canIncrease
                              ? null
                              : () async {
                                  setState(() => _menuDays[id] = count + 1);
                                  await _refreshQuote();
                                },
                          icon: const Icon(Icons.add_circle_outline),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          if (quote != null) ...[
            const SizedBox(height: 8),
            Text(
              quote.billableDays == quote.availableDays && quote.availableDays > 0
                  ? 'Selected ${quote.billableDays} / ${quote.availableDays} working days · month filled'
                  : 'Selected ${quote.billableDays} / ${quote.availableDays} working days · fill every working day to continue',
              style: TextStyle(
                color: quote.billableDays == quote.availableDays &&
                        quote.availableDays > 0
                    ? Colors.green.shade800
                    : Colors.orange.shade800,
                fontWeight: FontWeight.w700,
                fontSize: 12,
              ),
            ),
          ],
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
                  ? 'Prepaid & create · ৳${quote?.totalAmount ?? 0}'
                  : 'Confirm & send OTP',
            ),
          ),
        ),
      ),
    );
  }
}
