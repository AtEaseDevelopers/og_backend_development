import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/api_client.dart';
import 'core/config.dart';
import 'core/local_db.dart';
import 'state/app_state.dart';
import 'state/sync_service.dart';
import 'ui/home_screen.dart';
import 'ui/login_screen.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await AppConfig.load();

  final db = await LocalDb.open();
  final api = ApiClient();
  final sync = SyncService(db, api);
  final app = AppState(db, api, sync);
  await app.boot();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider.value(value: sync),
        ChangeNotifierProvider.value(value: app),
      ],
      child: const DriverApp(),
    ),
  );
}

class DriverApp extends StatelessWidget {
  const DriverApp({super.key});

  @override
  Widget build(BuildContext context) {
    final scheme = ColorScheme.fromSeed(seedColor: const Color(0xFF0B4F8A));
    return MaterialApp(
      title: 'O&G Driver',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: scheme,
        useMaterial3: true,
        scaffoldBackgroundColor: const Color(0xFFF3F5F8),
        cardTheme: CardThemeData(
          elevation: 0,
          color: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
            side: BorderSide(color: Colors.grey.shade200),
          ),
        ),
        inputDecorationTheme: const InputDecorationTheme(border: OutlineInputBorder(), isDense: true),
        appBarTheme: AppBarTheme(backgroundColor: scheme.primary, foregroundColor: Colors.white),
      ),
      home: Consumer<AppState>(builder: (_, app, _) => app.loggedIn ? const HomeScreen() : const LoginScreen()),
    );
  }
}
