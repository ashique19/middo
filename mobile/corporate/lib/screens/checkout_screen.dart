import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';
import 'package:url_launcher/url_launcher.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../models/models.dart';
import '../theme/middo_colors.dart';
import '../widgets/widgets.dart';

enum _CheckoutStep { dates, receiver, otp }

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key, required this.menuItemId});

  final String menuItemId;

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  MenuItem? _item;
  CheckoutMeta? _meta;
  Map<DateTime, int> _quantities = {};
  bool _loading = true;
  bool _submitting = false;
  String? _error;
  _CheckoutStep _step = _CheckoutStep.dates;

  final _nameCtrl = TextEditingController();
  final _mobileCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _otpCtrl = TextEditingController();

  int? _cityId;
  int? _areaId;
  String? _formError;
  String? _debugOtp;
  PrepaymentQuote? _prepayment;
  String _paymentMethod = 'cash_on_delivery';
  bool _codAllowed = false;
  List<String> _paymentMethods = const ['cash_on_delivery'];
  String? _paymentToken;
  CorporateUser? _profile;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (_item == null) {
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
        repo.menu(),
        repo.checkoutMeta(),
        repo.me(),
      ]);
      final menu = results[0] as List<MenuItem>;
      final meta = results[1] as CheckoutMeta;
      final user = results[2] as CorporateUser;
      final item = menu.firstWhere(
        (m) => m.id == widget.menuItemId,
        orElse: () => repo.menuById(widget.menuItemId),
      );
      final quantities = <DateTime, int>{
        for (var i = 0; i < meta.dates.length; i++)
          meta.dates[i]: i == 0 ? 1 : 0,
      };

      LocationCity? city;
      if (meta.cities.isNotEmpty) {
        city = meta.cities.where((c) => c.id == user.cityId).firstOrNull ??
            meta.cities.first;
      }
      final areaId = city == null
          ? null
          : (city.areas.where((a) => a.id == user.areaId).firstOrNull?.id ??
              (city.areas.isNotEmpty ? city.areas.first.id : null));

      if (!mounted) return;
      setState(() {
        _item = item;
        _meta = meta;
        _quantities = quantities;
        _profile = user;
        _nameCtrl.text = user.receiverName;
        _mobileCtrl.text = user.mobile;
        _addressCtrl.text = user.address ?? '';
        _cityId = city?.id;
        _areaId = areaId;
        _loading = false;
      });
    } catch (e) {
      if (!mounted) return;
      setState(() {
        _error = e.toString();
        _loading = false;
      });
    }
  }

  int get _totalQty => _quantities.values.fold<int>(0, (a, b) => a + b);

  List<LocationArea> get _areasForCity {
    final meta = _meta;
    if (meta == null || _cityId == null) return const [];
    final city = meta.cities.where((c) => c.id == _cityId).firstOrNull;
    return city?.areas ?? const [];
  }

  ReceiverDetails? _validatedReceiver() {
    final name = _nameCtrl.text.trim();
    final mobile = _mobileCtrl.text.trim();
    final address = _addressCtrl.text.trim();
    final cityId = _cityId;
    final areaId = _areaId;

    if (name.length < 2) {
      setState(() => _formError = 'Enter the receiver name.');
      return null;
    }
    if (!RegExp(r'^01[3-9]\d{8}$').hasMatch(mobile)) {
      setState(() => _formError = 'Enter a valid 11-digit BD mobile (01XXXXXXXXX).');
      return null;
    }
    if (address.length < 5) {
      setState(() => _formError = 'Enter a full delivery address.');
      return null;
    }
    if (cityId == null) {
      setState(() => _formError = 'Select a delivery city.');
      return null;
    }
    if (areaId == null) {
      setState(() => _formError = 'Select a delivery area.');
      return null;
    }

    setState(() => _formError = null);
    return ReceiverDetails(
      receiverName: name,
      mobile: mobile,
      address: address,
      cityId: cityId,
      areaId: areaId,
    );
  }

  void _goBack() {
    switch (_step) {
      case _CheckoutStep.dates:
        context.pop();
      case _CheckoutStep.receiver:
        setState(() {
          _step = _CheckoutStep.dates;
          _formError = null;
        });
      case _CheckoutStep.otp:
        setState(() {
          _step = _CheckoutStep.receiver;
          _formError = null;
          _otpCtrl.clear();
        });
    }
  }

  int get _activeDateCount =>
      _quantities.values.where((qty) => qty > 0).length;

  void _continueFromDates() {
    if (_totalQty == 0) return;
    setState(() {
      _step = _CheckoutStep.receiver;
      _formError = null;
      if (_activeDateCount == 1 &&
          !(_prepayment?.required ?? false) &&
          _paymentMethod != 'balance' &&
          _paymentMethod != 'gateway') {
        _paymentMethod = 'cash_on_delivery';
      }
    });
  }

  Future<void> _sendOtp() async {
    final item = _item;
    final receiver = _validatedReceiver();
    if (item == null || receiver == null) return;

    setState(() => _submitting = true);
    try {
      final result = await AppScope.of(context).sendOrderOtp(
        menuItemId: item.id,
        quantities: _quantities,
        receiver: receiver,
      );
      if (!mounted) return;

      var paymentToken = _paymentToken;
      var paymentMethod = _paymentMethod;
      final methods = result.paymentMethods;
      final codAllowed = result.codAllowed;

      if (!methods.contains(paymentMethod)) {
        paymentMethod = methods.contains('cash_on_delivery')
            ? 'cash_on_delivery'
            : (methods.contains('balance') ? 'balance' : methods.first);
      }

      final needsCharge = result.prepayment.required ||
          (paymentMethod == 'balance' || paymentMethod == 'gateway');

      if (needsCharge && paymentMethod == 'gateway') {
        final gateway = await AppScope.of(context).createGatewayPrepay(
          menuItemId: item.id,
          quantities: _quantities,
          receiver: receiver,
        );
        paymentToken = gateway.paymentToken;
        final uri = Uri.tryParse(gateway.paymentUrl);
        if (uri != null) {
          await launchUrl(uri, mode: LaunchMode.externalApplication);
        }
        if (!mounted) return;
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              'Complete ৳${gateway.amount.toStringAsFixed(0)} payment in the browser, then verify OTP.',
            ),
            backgroundColor: MiddoColors.forest,
          ),
        );
      } else if (needsCharge &&
          paymentMethod == 'balance' &&
          result.prepayment.required &&
          !result.prepayment.balanceSufficient) {
        setState(() {
          _prepayment = result.prepayment;
          _codAllowed = codAllowed;
          _paymentMethods = methods;
          _formError =
              'Insufficient Middo Balance. Need ${bdt.format(result.prepayment.amount)}, available ${bdt.format(result.prepayment.balance)}. Top up or pay online.';
        });
        return;
      } else if (paymentMethod == 'cash_on_delivery') {
        paymentToken = null;
      }

      setState(() {
        _step = _CheckoutStep.otp;
        _debugOtp = result.debugOtp;
        _prepayment = result.prepayment;
        _codAllowed = codAllowed;
        _paymentMethods = methods;
        _paymentMethod = paymentMethod;
        _paymentToken = paymentToken;
        _otpCtrl.clear();
        _formError = null;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('OTP sent to ${receiver.mobile}'),
          backgroundColor: MiddoColors.forest,
        ),
      );
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _formError = e.message);
    } catch (e) {
      if (!mounted) return;
      setState(() => _formError = e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  Future<void> _confirmWithOtp() async {
    final item = _item;
    final receiver = _validatedReceiver();
    final otp = _otpCtrl.text.trim();
    if (item == null || receiver == null) return;

    if (!RegExp(r'^\d{4}$').hasMatch(otp)) {
      setState(() => _formError = 'Enter the 4-digit SMS code.');
      return;
    }

    setState(() {
      _submitting = true;
      _formError = null;
    });
    try {
      await AppScope.of(context).placeOrder(
        menuItemId: item.id,
        quantities: _quantities,
        receiver: receiver,
        otp: otp,
        paymentMethod: _paymentMethod,
        paymentToken:
            _paymentMethod == 'gateway' ? _paymentToken : null,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            'Scheduled $_totalQty meals · ${bdt.format(_totalQty * item.price)}',
          ),
          backgroundColor: MiddoColors.forest,
        ),
      );
      context.go('/schedule');
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _formError = e.message);
    } catch (e) {
      if (!mounted) return;
      setState(() => _formError = e.toString());
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const MiddoLoadingScaffold(
        title: 'Checkout',
        message: 'Loading checkout…',
      );
    }
    if (_error != null || _item == null || _meta == null) {
      return Scaffold(
        appBar: AppBar(title: const Text('Checkout')),
        body: Center(child: Text(_error ?? 'Unable to load checkout')),
      );
    }

    final item = _item!;
    final total = _totalQty * item.price;
    final title = switch (_step) {
      _CheckoutStep.dates => 'Checkout',
      _CheckoutStep.receiver => 'Receiver details',
      _CheckoutStep.otp => 'Verify OTP',
    };

    return Scaffold(
      appBar: AppBar(
        leading: IconButton(
          icon: const Icon(Icons.arrow_back_rounded),
          onPressed: _goBack,
        ),
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'NEW ORDER · STEP ${_step.index + 1}/3',
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: MiddoColors.orange,
                    fontWeight: FontWeight.w800,
                    letterSpacing: 0.6,
                  ),
            ),
            Text(title),
          ],
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(18, 8, 18, 120),
        children: [
          _mealHeader(item),
          const SizedBox(height: 16),
          if (_step == _CheckoutStep.dates) ..._datesSection(item, total),
          if (_step == _CheckoutStep.receiver) ..._receiverSection(),
          if (_step == _CheckoutStep.otp) ..._otpSection(),
          if (_formError != null) ...[
            const SizedBox(height: 12),
            Text(
              _formError!,
              style: const TextStyle(
                color: MiddoColors.orangeDeep,
                fontWeight: FontWeight.w700,
                fontSize: 13,
              ),
            ),
          ],
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 8, 18, 12),
          child: FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: MiddoColors.forest,
              padding: const EdgeInsets.symmetric(vertical: 16),
            ),
            onPressed: _submitting
                ? null
                : switch (_step) {
                    _CheckoutStep.dates =>
                      _totalQty == 0 ? null : _continueFromDates,
                    _CheckoutStep.receiver => _sendOtp,
                    _CheckoutStep.otp => _confirmWithOtp,
                  },
            child: Text(
              _submitting
                  ? switch (_step) {
                      _CheckoutStep.dates => 'Loading…',
                      _CheckoutStep.receiver => 'Sending OTP…',
                      _CheckoutStep.otp => 'Scheduling…',
                    }
                  : switch (_step) {
                      _CheckoutStep.dates => 'Continue to receiver',
                      _CheckoutStep.receiver => 'Send SMS OTP',
                      _CheckoutStep.otp => 'Verify & Schedule',
                    },
            ),
          ),
        ),
      ),
    );
  }

  Widget _mealHeader(MenuItem item) {
    return Container(
      decoration: BoxDecoration(
        color: MiddoColors.white,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: MiddoColors.creamBorder),
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          if (_step == _CheckoutStep.dates)
            MealImage(item: item, height: 160),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item.name,
                  style: const TextStyle(
                    fontSize: 17,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  '$_totalQty meals · ${bdt.format(_totalQty * item.price)} · 12:00 PM',
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                    color: MiddoColors.inkSoft,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  List<Widget> _datesSection(MenuItem item, double total) {
    return [
      const Text(
        'Delivery dates & quantities',
        style: TextStyle(fontSize: 13, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 4),
      Text(
        _meta!.isPastCutoff
            ? 'Same-day cutoff passed (${_meta!.cutoffLabel})'
            : 'Same-day cutoff open until ${_meta!.cutoffLabel}',
        style: const TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w600,
          color: MiddoColors.inkSoft,
        ),
      ),
      const SizedBox(height: 10),
      GridView.count(
        crossAxisCount: 3,
        shrinkWrap: true,
        physics: const NeverScrollableScrollPhysics(),
        crossAxisSpacing: 8,
        mainAxisSpacing: 8,
        childAspectRatio: 1.05,
        children: _quantities.entries.map((entry) {
          final active = entry.value > 0;
          return InkWell(
            onTap: () {
              setState(() {
                _quantities[entry.key] = active ? 0 : 1;
              });
            },
            onLongPress: () {
              setState(() {
                _quantities[entry.key] = (entry.value + 1).clamp(0, 5);
              });
            },
            borderRadius: BorderRadius.circular(14),
            child: Ink(
              decoration: BoxDecoration(
                color: active ? MiddoColors.forest : const Color(0xFFFCF8F2),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(
                  color: active ? MiddoColors.forest : const Color(0xFFDDD3BE),
                ),
              ),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    DateFormat('E').format(entry.key),
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w700,
                      color: active ? Colors.white70 : MiddoColors.inkSoft,
                    ),
                  ),
                  Text(
                    '${entry.key.day}',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w800,
                      color: active ? Colors.white : MiddoColors.ink,
                    ),
                  ),
                  Text(
                    '×${entry.value}',
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.w800,
                      color: active ? Colors.white : MiddoColors.inkSoft,
                    ),
                  ),
                ],
              ),
            ),
          );
        }).toList(),
      ),
      const SizedBox(height: 8),
      const Text(
        'Tap to toggle · long-press to increase quantity',
        style: TextStyle(
          fontSize: 10,
          fontWeight: FontWeight.w600,
          color: MiddoColors.muted,
        ),
      ),
      const SizedBox(height: 16),
      Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: MiddoColors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: MiddoColors.creamBorder),
        ),
        child: Column(
          children: [
            _row('Meals', '$_totalQty × ${bdt.format(item.price)}'),
            _row('Delivery window', '12:00 PM'),
            _row(
              'Pay from',
              _profile != null
                  ? 'Middo Balance (${bdt.format(_profile!.balance)})'
                  : 'Middo Balance',
            ),
            const Divider(height: 20),
            _row('Total', bdt.format(total), bold: true),
          ],
        ),
      ),
    ];
  }

  List<Widget> _receiverSection() {
    final cities = _meta?.cities ?? const <LocationCity>[];
    final areas = _areasForCity;

    return [
      const Text(
        'Confirm who receives the meal',
        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 4),
      const Text(
        'We’ll text a confirmation code to this mobile before scheduling.',
        style: TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: MiddoColors.inkSoft,
        ),
      ),
      const SizedBox(height: 14),
      TextField(
        controller: _nameCtrl,
        textCapitalization: TextCapitalization.words,
        decoration: const InputDecoration(
          labelText: 'RECEIVER NAME',
          hintText: 'Desk / contact person',
        ),
      ),
      const SizedBox(height: 12),
      TextField(
        controller: _mobileCtrl,
        keyboardType: TextInputType.phone,
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
      Container(
        width: double.infinity,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: const Color(0xFFFFF8EE),
          borderRadius: BorderRadius.circular(14),
          border: Border.all(color: const Color(0xFFE8D7B8)),
        ),
        child: const Text(
          'Different receiver name/mobile than your profile requires full prepayment. '
          'Reaching 3+ meals (same day or across days) also requires full prepayment (admin-configurable).',
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: MiddoColors.inkSoft,
            height: 1.35,
          ),
        ),
      ),
      const SizedBox(height: 12),
      Text(
        _activeDateCount == 1
            ? 'Payment method'
            : 'If prepayment is required',
        style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 6),
      SegmentedButton<String>(
        segments: [
          if (_activeDateCount == 1 || _codAllowed)
            const ButtonSegment(
              value: 'cash_on_delivery',
              label: Text('COD'),
            ),
          const ButtonSegment(value: 'balance', label: Text('Balance')),
          const ButtonSegment(value: 'gateway', label: Text('Online')),
        ],
        selected: {_paymentMethod},
        onSelectionChanged: (value) {
          setState(() {
            _paymentMethod = value.first;
            _paymentToken = null;
          });
        },
      ),
      if (_paymentMethod == 'cash_on_delivery') ...[
        const SizedBox(height: 8),
        const Text(
          'Cash on Delivery — pay the rider when your meal arrives.',
          style: TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: MiddoColors.inkSoft,
          ),
        ),
      ],
      const SizedBox(height: 12),
      TextField(
        controller: _addressCtrl,
        maxLines: 2,
        textCapitalization: TextCapitalization.sentences,
        decoration: const InputDecoration(
          labelText: 'DELIVERY ADDRESS',
          hintText: 'Floor, desk, landmark',
        ),
      ),
      const SizedBox(height: 12),
      if (cities.isEmpty)
        const Text(
          'No delivery cities configured yet. Contact Middo support.',
          style: TextStyle(
            color: MiddoColors.orangeDeep,
            fontWeight: FontWeight.w700,
            fontSize: 13,
          ),
        )
      else ...[
        DropdownButtonFormField<int>(
          value: _cityId != null && cities.any((c) => c.id == _cityId)
              ? _cityId
              : cities.first.id,
          decoration: const InputDecoration(labelText: 'CITY'),
          items: cities
              .map(
                (c) => DropdownMenuItem(value: c.id, child: Text(c.name)),
              )
              .toList(),
          onChanged: (value) {
            if (value == null) return;
            final city = cities.firstWhere((c) => c.id == value);
            setState(() {
              _cityId = value;
              _areaId =
                  city.areas.isNotEmpty ? city.areas.first.id : null;
            });
          },
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<int>(
          value: _areaId != null && areas.any((a) => a.id == _areaId)
              ? _areaId
              : (areas.isNotEmpty ? areas.first.id : null),
          decoration: const InputDecoration(labelText: 'AREA'),
          items: areas
              .map(
                (a) => DropdownMenuItem(value: a.id, child: Text(a.name)),
              )
              .toList(),
          onChanged: areas.isEmpty
              ? null
              : (value) => setState(() => _areaId = value),
        ),
      ],
    ];
  }

  List<Widget> _otpSection() {
    final mobile = _mobileCtrl.text.trim();
    return [
      const Text(
        'Enter SMS confirmation code',
        style: TextStyle(fontSize: 15, fontWeight: FontWeight.w800),
      ),
      const SizedBox(height: 4),
      Text(
        'We sent a 4-digit code to $mobile. Valid for 5 minutes.',
        style: const TextStyle(
          fontSize: 12,
          fontWeight: FontWeight.w600,
          color: MiddoColors.inkSoft,
        ),
      ),
      if (_debugOtp != null) ...[
        const SizedBox(height: 8),
        Text(
          'Debug OTP: $_debugOtp',
          style: const TextStyle(
            fontSize: 12,
            fontWeight: FontWeight.w800,
            color: MiddoColors.orange,
          ),
        ),
      ],
      if (_prepayment?.required == true) ...[
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFFFF8EE),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFE8D7B8)),
          ),
          child: Text(
            '${_prepayment!.message ?? 'Prepayment required.'}\n'
            'Charge ${bdt.format(_prepayment!.amount)} via '
            '${_paymentMethod == 'gateway' ? 'online payment' : 'Middo Balance'}.',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: MiddoColors.ink,
              height: 1.35,
            ),
          ),
        ),
      ],
      if (_paymentMethod == 'cash_on_delivery') ...[
        const SizedBox(height: 12),
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: const Color(0xFFEFF8F1),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: const Color(0xFFB7D9C0)),
          ),
          child: const Text(
            'Cash on Delivery selected — pay the rider when your meal arrives.',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w700,
              color: MiddoColors.ink,
              height: 1.35,
            ),
          ),
        ),
      ],
      const SizedBox(height: 16),
      TextField(
        controller: _otpCtrl,
        keyboardType: TextInputType.number,
        textAlign: TextAlign.center,
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
      TextButton(
        onPressed: _submitting ? null : _sendOtp,
        child: const Text('Resend code'),
      ),
    ];
  }

  Widget _row(String left, String right, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Row(
        children: [
          Text(
            left,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
              fontSize: bold ? 15 : 13,
              color: bold ? MiddoColors.ink : MiddoColors.inkSoft,
            ),
          ),
          const Spacer(),
          Text(
            right,
            style: TextStyle(
              fontWeight: bold ? FontWeight.w800 : FontWeight.w700,
              fontSize: bold ? 15 : 13,
              color: MiddoColors.ink,
            ),
          ),
        ],
      ),
    );
  }
}
