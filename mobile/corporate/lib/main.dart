import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'app_scope.dart';
import 'data/auth_store.dart';
import 'data/corporate_repository.dart';
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
  runApp(MiddoCorporateApp(repository: createCorporateRepository()));
}

class MiddoCorporateApp extends StatelessWidget {
  const MiddoCorporateApp({super.key, required this.repository});

  final CorporateRepository repository;

  @override
  Widget build(BuildContext context) {
    final router = createAppRouter();
    return AppScope(
      repository: repository,
      child: MaterialApp.router(
        title: 'Middo Corporate',
        debugShowCheckedModeBanner: false,
        theme: buildMiddoTheme(),
        routerConfig: router,
      ),
    );
  }
}
