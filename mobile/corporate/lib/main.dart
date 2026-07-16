import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import 'router/app_router.dart';
import 'theme/middo_theme.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(
    const SystemUiOverlayStyle(
      statusBarColor: Colors.transparent,
      statusBarIconBrightness: Brightness.dark,
    ),
  );
  runApp(const MiddoCorporateApp());
}

class MiddoCorporateApp extends StatelessWidget {
  const MiddoCorporateApp({super.key});

  @override
  Widget build(BuildContext context) {
    final router = createAppRouter();
    return MaterialApp.router(
      title: 'Middo Corporate',
      debugShowCheckedModeBanner: false,
      theme: buildMiddoTheme(),
      routerConfig: router,
    );
  }
}
