import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import '../theme/middo_colors.dart';

/// In-app payment frame. Detects Middo gateway success pages and returns `true`.
class PaymentWebViewScreen extends StatefulWidget {
  const PaymentWebViewScreen({
    super.key,
    required this.paymentUrl,
    this.title = 'Payment',
  });

  final String paymentUrl;
  final String title;

  /// Opens the payment URL in an in-app WebView and returns whether payment succeeded.
  static Future<bool> open(
    BuildContext context, {
    required String paymentUrl,
    String title = 'Payment',
  }) async {
    final result = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        fullscreenDialog: true,
        builder: (_) => PaymentWebViewScreen(
          paymentUrl: paymentUrl,
          title: title,
        ),
      ),
    );
    return result == true;
  }

  @override
  State<PaymentWebViewScreen> createState() => _PaymentWebViewScreenState();
}

class _PaymentWebViewScreenState extends State<PaymentWebViewScreen> {
  late final WebViewController _controller;
  var _loading = true;
  var _completed = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (_) {
            if (mounted) setState(() => _loading = true);
          },
          onPageFinished: (url) async {
            if (!mounted) return;
            setState(() => _loading = false);
            await _inspectForSuccess(url);
          },
          onWebResourceError: (error) {
            if (!mounted || _completed) return;
            setState(() {
              _loading = false;
              _error = error.description;
            });
          },
          onNavigationRequest: (request) {
            _maybeCompleteFromUrl(request.url);
            return NavigationDecision.navigate;
          },
          onUrlChange: (change) {
            final url = change.url;
            if (url != null) {
              _maybeCompleteFromUrl(url);
            }
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.paymentUrl));
  }

  bool _urlLooksPaid(String url) {
    final uri = Uri.tryParse(url);
    if (uri == null) return false;
    if (uri.queryParameters['order_placed'] == '1') return true;
    final state = uri.queryParameters['state']?.toLowerCase();
    if (state == 'paid' || state == 'credited') return true;
    return false;
  }

  void _maybeCompleteFromUrl(String url) {
    if (_completed) return;
    if (_urlLooksPaid(url)) {
      _finishSuccess();
    }
  }

  Future<void> _inspectForSuccess(String url) async {
    if (_completed) return;
    if (_urlLooksPaid(url)) {
      _finishSuccess();
      return;
    }
    try {
      final raw = await _controller.runJavaScriptReturningResult(
        "document.body?.getAttribute('data-middo-payment-status') || ''",
      );
      final status = raw.toString().replaceAll('"', '').trim().toLowerCase();
      if (status == 'paid' || status == 'credited') {
        _finishSuccess();
      }
    } catch (_) {
      // Ignore JS failures on third-party (EPS) pages.
    }
  }

  void _finishSuccess() {
    if (_completed || !mounted) return;
    _completed = true;
    Navigator.of(context).pop(true);
  }

  void _finishCancel() {
    if (_completed || !mounted) return;
    Navigator.of(context).pop(false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: MiddoColors.cream,
      appBar: AppBar(
        title: Text(widget.title),
        leading: IconButton(
          icon: const Icon(Icons.close_rounded),
          onPressed: _finishCancel,
        ),
        actions: [
          TextButton(
            onPressed: _completed ? null : () => _finishSuccess(),
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
