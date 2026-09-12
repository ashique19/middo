import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:go_router/go_router.dart';

import 'app_scope.dart';
import 'data/auth_store.dart';
import 'data/operation_repository.dart';
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

  runApp(MiddoOperationApp(repository: createOperationRepository()));
}

class MiddoOperationApp extends StatefulWidget {
  const MiddoOperationApp({super.key, required this.repository});

  final OperationRepository repository;

  @override
  State<MiddoOperationApp> createState() => _MiddoOperationAppState();
}

class _MiddoOperationAppState extends State<MiddoOperationApp> {
  late final GoRouter _router = createAppRouter();

  @override
  Widget build(BuildContext context) {
    return AppScope(
      repository: widget.repository,
      child: MaterialApp.router(
        title: 'Middo Operation',
        debugShowCheckedModeBanner: false,
        theme: buildMiddoTheme(),
        routerConfig: _router,
      ),
    );
  }
}
