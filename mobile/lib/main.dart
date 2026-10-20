import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:shared_preferences/shared_preferences.dart';

import 'core/state.dart';
import 'core/theme.dart';
import 'screens/auth_screen.dart';
import 'screens/shell.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final prefs = await SharedPreferences.getInstance();
  final state = AppState(prefs);
  runApp(SafirChargeApp(state: state));
  state.refreshMe();
}

final _shellObserver = ShellRefreshObserver();

class SafirChargeApp extends StatelessWidget {
  const SafirChargeApp({super.key, required this.state});

  final AppState state;

  @override
  Widget build(BuildContext context) {
    return AppScope(
      state: state,
      child: ListenableBuilder(
        listenable: state,
        builder: (context, _) {
          SystemChrome.setSystemUIOverlayStyle(state.dark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark);
          return MaterialApp(
            title: 'SafirCharge',
            navigatorKey: kNavKey,
            navigatorObservers: [_shellObserver],
            debugShowCheckedModeBanner: false,
            locale: const Locale('fa', 'IR'),
            supportedLocales: const [Locale('fa', 'IR'), Locale('en')],
            localizationsDelegates: GlobalMaterialLocalizations.delegates,
            theme: buildTheme(state.accent, dark: false),
            darkTheme: buildTheme(state.accent, dark: true),
            themeMode: state.dark ? ThemeMode.dark : ThemeMode.light,
            builder: (context, child) => Directionality(textDirection: TextDirection.rtl, child: child!),
            home: state.loggedIn ? const Shell() : const AuthScreen(),
          );
        },
      ),
    );
  }
}
