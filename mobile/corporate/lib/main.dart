import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import 'app_scope.dart';
import 'data/auth_store.dart';
import 'data/corporate_repository.dart';
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

  final repository = createCorporateRepository();
  PushNotificationService.instance.attachRepository(repository);

  runApp(MiddoCorporateApp(repository: repository));
}

class MiddoCorporateApp extends StatefulWidget {
  const MiddoCorporateApp({super.key, required this.repository});

  final CorporateRepository repository;

  @override
  State<MiddoCorporateApp> createState() => _MiddoCorporateAppState();
}

class _MiddoCorporateAppState extends State<MiddoCorporateApp> {
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
        title: 'Middo',
        debugShowCheckedModeBanner: false,
        theme: buildMiddoTheme(),
        routerConfig: _router,
      ),
    );
  }
}
