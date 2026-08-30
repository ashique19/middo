import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import 'app_scope.dart';
import 'data/auth_store.dart';
import 'data/kitchen_repository.dart';
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
  await PushNotificationService.instance.init();

  final repository = createKitchenRepository();
  PushNotificationService.instance.attachRepository(repository);

  runApp(MiddoKitchenApp(repository: repository));
}

class MiddoKitchenApp extends StatefulWidget {
  const MiddoKitchenApp({super.key, required this.repository});

  final KitchenRepository repository;

  @override
  State<MiddoKitchenApp> createState() => _MiddoKitchenAppState();
}

class _MiddoKitchenAppState extends State<MiddoKitchenApp> {
  late final GoRouter _router = createAppRouter();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      PushNotificationService.instance.consumePendingDeepLink(_router);
    });
  }

  @override
  Widget build(BuildContext context) {
    return AppScope(
      repository: widget.repository,
      child: MaterialApp.router(
        title: 'Middo Kitchen',
        debugShowCheckedModeBanner: false,
        theme: buildMiddoTheme(),
        routerConfig: _router,
      ),
    );
  }
}
