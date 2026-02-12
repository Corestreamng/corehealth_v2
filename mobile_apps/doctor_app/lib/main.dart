import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'app.dart';
import 'core/config/server_config_provider.dart';
import 'core/theme/theme_provider.dart';
import 'core/storage/local_storage.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Lock to portrait
  await SystemChrome.setPreferredOrientations([
    DeviceOrientation.portraitUp,
    DeviceOrientation.portraitDown,
  ]);

  // Init local storage
  await LocalStorage.init();

  runApp(
    MultiProvider(
      providers: [
        ChangeNotifierProvider(create: (_) => ServerConfigProvider()),
        ChangeNotifierProvider(create: (_) => ThemeProvider()),
      ],
      child: const CoreHealthDoctorApp(),
    ),
  );
}
