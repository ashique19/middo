import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:image_picker/image_picker.dart';

import '../app_scope.dart';
import '../data/api_client.dart';
import '../data/middo_haptics.dart';
import 'delivery_ui.dart';

class DeliverOtpResult {
  const DeliverOtpResult({required this.otp, this.photoPath});

  final String otp;
  final String? photoPath;
}

/// Sends delivery OTP, then shows OTP + optional POD photo dialog.
Future<DeliverOtpResult?> promptDeliverWithOtp(
  BuildContext context, {
  required int runId,
}) async {
  String? debugHint;
  try {
    final res = await AppScope.of(context).sendDeliveryOtp(runId);
    debugHint = res['debug_otp']?.toString();
    final msg = res['message']?.toString();
    if (context.mounted && msg != null && msg.isNotEmpty) {
      showDeliverySnack(context, msg);
    }
  } on ApiException catch (e) {
    if (context.mounted) {
      showDeliverySnack(context, e.message, error: true);
    }
    return null;
  }

  if (!context.mounted) return null;

  final otpCtrl = TextEditingController();
  XFile? photo;

  final ok = await showDialog<bool>(
    context: context,
    barrierDismissible: false,
    builder: (ctx) {
      return StatefulBuilder(
        builder: (ctx, setLocal) {
          return AlertDialog(
            title: Text('Deliver · #$runId'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Enter the OTP sent to the receiver. Attach a POD photo if needed.',
                    style: TextStyle(fontSize: 13, height: 1.35),
                  ),
                  if (debugHint != null && debugHint.isNotEmpty) ...[
                    const SizedBox(height: 8),
                    Text(
                      'Debug OTP: $debugHint',
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                  const SizedBox(height: 12),
                  DeliveryDialogField(
                    label: 'OTP',
                    controller: otpCtrl,
                    keyboardType: TextInputType.number,
                    autofocus: true,
                    inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                  ),
                  const SizedBox(height: 12),
                  OutlinedButton.icon(
                    onPressed: () async {
                      final file = await ImagePicker().pickImage(
                        source: ImageSource.camera,
                        imageQuality: 85,
                      );
                      if (file != null) setLocal(() => photo = file);
                    },
                    icon: const Icon(Icons.photo_camera_outlined),
                    label: Text(
                      photo == null ? 'Take POD photo' : 'Photo selected',
                    ),
                  ),
                  TextButton(
                    onPressed: () async {
                      final file = await ImagePicker().pickImage(
                        source: ImageSource.gallery,
                        imageQuality: 85,
                      );
                      if (file != null) setLocal(() => photo = file);
                    },
                    child: const Text('Choose from gallery'),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.pop(ctx, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(ctx, true),
                child: const Text('Deliver'),
              ),
            ],
          );
        },
      );
    },
  );

  final otp = otpCtrl.text.trim();
  final path = photo?.path;
  otpCtrl.dispose();

  if (ok != true || !context.mounted) return null;
  if (otp.isEmpty) {
    showDeliverySnack(context, 'Enter the delivery OTP.', error: true);
    return null;
  }
  return DeliverOtpResult(otp: otp, photoPath: path);
}

Future<bool> runDeliverFlow(
  BuildContext context, {
  required int runId,
  VoidCallback? onBusy,
  VoidCallback? onIdle,
  Future<void> Function()? onSuccess,
}) async {
  final prompt = await promptDeliverWithOtp(context, runId: runId);
  if (prompt == null || !context.mounted) return false;

  onBusy?.call();
  try {
    final res = await AppScope.of(context).deliverRun(
      runId,
      otp: prompt.otp,
      podPhotoPath: prompt.photoPath,
    );
    MiddoHaptics.success();
    if (!context.mounted) return true;
    showDeliverySnack(
      context,
      res['message']?.toString() ?? 'Delivered.',
    );
    await onSuccess?.call();
    return true;
  } on ApiException catch (e) {
    if (context.mounted) showDeliverySnack(context, e.message, error: true);
    return false;
  } finally {
    onIdle?.call();
  }
}
