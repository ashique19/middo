import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../theme/middo_colors.dart';

enum PaymentWebViewOutcome { success, failed, cancelled }

/// In-app payment frame. Detects Middo gateway success/failure and returns.
class PaymentWebViewScreen extends StatefulWidget {
  const PaymentWebViewScreen({
    super.key,
    required this.paymentUrl,
    this.title = 'Payment',
  });

  final String paymentUrl;
  final String title;

  /// Opens the payment URL in an in-app WebView.
  /// Returns `true` only when payment succeeded.
  static Future<bool> open(
    BuildContext context, {
    required String paymentUrl,
    String title = 'Payment',
  }) async {
    final outcome = await openForOutcome(
      context,
      paymentUrl: paymentUrl,
      title: title,
    );
    return outcome == PaymentWebViewOutcome.success;
  }

  static Future<PaymentWebViewOutcome> openForOutcome(
    BuildContext context, {
    required String paymentUrl,
    String title = 'Payment',
  }) async {
    final result = await Navigator.of(context).push<PaymentWebViewOutcome>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => PaymentWebViewScreen(
          paymentUrl: paymentUrl,
          title: title,
        ),
      ),
    );
    return result ?? PaymentWebViewOutcome.cancelled;
  }

  @override
  State<PaymentWebViewScreen> createState() => _PaymentWebViewScreenState();
}

class _PaymentWebViewScreenState extends State<PaymentWebViewScreen> {
  late final WebViewController _controller;
  var _loading = true;
  var _completed = false;
  String? _error;
  String? _banner;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (url) {
            if (mounted) setState(() => _loading = true);
            _evaluateUrl(url);
          },
          onPageFinished: (url) async {
            if (!mounted) return;
            setState(() => _loading = false);
            await _inspectPage(url);
          },
          onWebResourceError: (error) {
            if (!mounted || _completed) return;
            setState(() {
              _loading = false;
              _error = error.description;
            });
          },
          onNavigationRequest: (request) {
            final decision = _evaluateUrl(request.url);
            // Never follow Middo web login / dashboard — finish in-app instead.
            if (decision == _NavDecision.blockAndSucceed ||
                decision == _NavDecision.blockAndFail) {
              return NavigationDecision.prevent;
            }
            return NavigationDecision.navigate;
          },
          onUrlChange: (change) {
            final url = change.url;
            if (url != null) {
              _evaluateUrl(url);
            }
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.paymentUrl));
  }

  _NavDecision _evaluateUrl(String url) {
    if (_completed) return _NavDecision.allow;

    final uri = Uri.tryParse(url);
    if (uri == null) return _NavDecision.allow;

    final path = uri.path.toLowerCase();
    final host = uri.host.toLowerCase();
    final eps = (uri.queryParameters['eps_status'] ?? '').toLowerCase();
    final orderPlaced = uri.queryParameters['order_placed'] == '1';

    // Must allow EPS callback routes to hit Laravel (confirm + fulfill).
    if (_isEpsCallbackPath(path)) {
      return _NavDecision.allow;
    }

    if (eps == 'paid' || orderPlaced) {
      _finish(PaymentWebViewOutcome.success, banner: 'Payment successful');
      return _NavDecision.blockAndSucceed;
    }

    if (eps == 'unpaid' || eps == 'failed' || eps == 'cancelled') {
      _finish(PaymentWebViewOutcome.failed, banner: 'Payment was not completed');
      return _NavDecision.blockAndFail;
    }

    // EPS callback landed on Middo auth pages — payment already processed server-side.
    if (_isMiddoHost(host) && _isAuthOrDashboardPath(path)) {
      _finish(
        PaymentWebViewOutcome.success,
        banner: 'Payment finished — returning to Middo…',
      );
      return _NavDecision.blockAndSucceed;
    }

    return _NavDecision.allow;
  }

  bool _isEpsCallbackPath(String path) {
    return path.contains('/pay/eps/success/') ||
        path.contains('/pay/eps/fail/') ||
        path.contains('/pay/eps/cancel/');
  }

  bool _isMiddoHost(String host) {
    if (host.isEmpty) return false;
    return host.contains('middo') ||
        host == 'localhost' ||
        host == '127.0.0.1';
  }

  bool _isAuthOrDashboardPath(String path) {
    return path == '/login' ||
        path.startsWith('/login/') ||
        path.contains('/corporates/dashboard') ||
        path.contains('/corporates/packages') ||
        path.endsWith('/register') ||
        path.contains('/forgot-password');
  }

  Future<void> _inspectPage(String url) async {
    if (_completed) return;
    final decision = _evaluateUrl(url);
    if (decision != _NavDecision.allow) return;

    try {
      final raw = await _controller.runJavaScriptReturningResult(
        "document.body?.getAttribute('data-middo-payment-status') || ''",
      );
      final status = raw.toString().replaceAll('"', '').trim().toLowerCase();
      if (status == 'paid' || status == 'credited') {
        _finish(PaymentWebViewOutcome.success, banner: 'Payment successful');
      } else if (status == 'failed' || status == 'unpaid') {
        _finish(
          PaymentWebViewOutcome.failed,
          banner: 'Payment was not completed',
        );
      }
    } catch (_) {
      // Ignore JS failures on third-party (EPS) pages.
    }
  }

  void _finish(PaymentWebViewOutcome outcome, {String? banner}) {
    if (_completed || !mounted) return;
    _completed = true;
    if (banner != null) {
      setState(() => _banner = banner);
    }
    // Brief beat so the user sees the result, then close the frame.
    Future<void>.delayed(const Duration(milliseconds: 450), () {
      if (!mounted) return;
      Navigator.of(context).pop(outcome);
    });
  }

  void _finishCancel() {
    if (_completed || !mounted) return;
    _completed = true;
    Navigator.of(context).pop(PaymentWebViewOutcome.cancelled);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MiddoColors.cream,
      appBar: AppBar(
        title: Text(widget.title),
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          onPressed: _completed ? null : _finishCancel,
        ),
        actions: [
          TextButton(
            onPressed: _completed
                ? null
                : () => _finish(
                      PaymentWebViewOutcome.success,
                      banner: 'Returning to Middo…',
                    ),
            child: const Text('Done'),
          ),
        ],
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_loading)
            const Positioned(
              top: 0,
              left: 0,
              right: 0,
              child: LinearProgressIndicator(
                minHeight: 2,
                color: MiddoColors.orange,
                backgroundColor: Color(0x33E87722),
              ),
            ),
          if (_banner != null)
            Positioned(
              left: 16,
              right: 16,
              bottom: 24,
              child: Material(
                elevation: 3,
                borderRadius: BorderRadius.circular(14),
                color: MiddoColors.forest,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 14,
                  ),
                  child: Text(
                    _banner!,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                    ),
                  ),
                ),
              ),
            ),
          if (_error != null && !_completed)
            Positioned(
              left: 16,
              right: 16,
              bottom: 24,
              child: Material(
                elevation: 2,
                borderRadius: BorderRadius.circular(12),
                color: Colors.white,
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Text(
                    'Could not load payment page. $_error',
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: MiddoColors.orangeDeep,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

enum _NavDecision { allow, blockAndSucceed, blockAndFail }
