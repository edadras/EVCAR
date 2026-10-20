import 'package:flutter/material.dart';

import '../screens/account_screens.dart';
import '../screens/charger_screen.dart';
import '../screens/charging_screen.dart';
import '../screens/edge_screens.dart';
import '../screens/history_screen.dart';
import '../screens/misc_screens.dart';
import '../screens/navigator_screen.dart';
import '../screens/reserve_screen.dart';
import '../screens/scan_screen.dart';
import '../screens/shell.dart';
import '../screens/support_screens.dart';
import '../screens/wallet_screens.dart';

Future<T?> push<T>(BuildContext context, Widget page) =>
    Navigator.of(context).push<T>(MaterialPageRoute(builder: (_) => page));

/// باز کردن صفحه با همان نام مسیر وب‌اپ (`/app/<name>`) — پاسخ اکشن‌های API فیلد `redirect` دارند.
Future<void> openScreen(BuildContext context, String? name, [Map<String, dynamic> params = const {}]) async {
  String? p(String k) => params[k]?.toString();
  final tabs = {'home': 0, 'map': 1, 'wallet': 2, 'account': 3};
  if (name == null) return;
  if (tabs.containsKey(name)) {
    Navigator.of(context).popUntil((r) => r.isFirst);
    ShellController.instance.go(tabs[name]!);
    return;
  }

  final Widget? page = switch (name) {
    'scan' => const ScanScreen(),
    'charger' => ChargerScreen(station: p('station'), connector: int.tryParse(p('connector') ?? ''), qr: p('qr')),
    'charging' => const ChargingScreen(),
    'history' => const HistoryScreen(),
    'notifications' => const NotificationsScreen(),
    'profile' => const ProfileScreen(),
    'vehicle' => const VehiclesScreen(),
    'rfid' => const RfidScreen(),
    'reserve' => ReserveScreen(station: p('station')),
    'support' => const SupportScreen(),
    'settings' => const SettingsScreen(),
    'offers' => const OffersScreen(),
    'plate' => const PlateScreen(),
    'password' => const PasswordScreen(),
    'privacy' => const LegalScreen(kind: 'privacy'),
    'terms' => const LegalScreen(kind: 'terms'),
    'ticket' when p('id') != null => TicketScreen(id: int.parse(p('id')!)),
    'chat' when p('id') != null => TicketScreen(id: int.parse(p('id')!)),
    'ticket' || 'chat' => const SupportScreen(),
    'ticket/new' => const TicketNewScreen(),
    'topup/confirm' => const TopupScreen(),
    'topup/result' => TopupResultScreen(amount: int.tryParse(p('amount') ?? ''), payment: p('payment')),
    'navigator' => const NavigatorScreen(),
    'arrival' => const ArrivalScreen(),
    'server' => const ServerSettingsScreen(),
    'edge' => const EdgeStationScreen(),
    'assistant' => const AssistantScreen(),
    _ => null,
  };
  if (page == null) return;

  // صفحات نتیجه جایگزین صفحهٔ فعلی می‌شوند (مانند redirect وب).
  if ((name == 'charging' || name == 'history' || name == 'topup/result') && Navigator.of(context).canPop()) {
    await Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => page));
  } else {
    await push(context, page);
  }
}
