import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import 'app_scope.dart';
import 'data/auth_store.dart';
import 'data/deep_link_service.dart';
import 'data/delivery_repository.dart';
import 'data/network_status.dart';
import 'data/offline_mutation_queue.dart';
import 'data/push_notification_service.dart';
import 'router/app_router.dart';
import 'theme/middo_theme.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
    ),
  );
  await AuthStore.instance.load();
  await NetworkStatus.instance.start();
  await OfflineMutationQueue.instance.start();
  await PushNotificationService.instance.init();

  final repository = createDeliveryRepository();
  PushNotificationService.instance.attachRepository(repository);

  runApp(MiddoDeliveryApp(repository: repository));
}

class MiddoDeliveryApp extends StatefulWidget {
  const MiddoDeliveryApp({super.key, required this.repository});

  final DeliveryRepository repository;

  @override
  State<MiddoDeliveryApp> createState() => _MiddoDeliveryAppState();
}

class _MiddoDeliveryAppState extends State<MiddoDeliveryApp> {
  late final GoRouter _router = createAppRouter();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      PushNotificationService.instance.consumePendingDeepLink(_router);
      DeepLinkService.instance.start(_router);
    });
  }

  @override
  Widget build(BuildContext context) {
    return AppScope(
      repository: widget.repository,
      child: MaterialApp.router(
        title: 'Middo Delivery',
        debugShowCheckedModeBanner: false,
        theme: buildMiddoTheme(),
        routerConfig: _router,
      ),
    );
  }
}
