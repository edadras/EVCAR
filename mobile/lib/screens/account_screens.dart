import 'package:flutter/material.dart';

import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

/// حساب کاربری: پروفایل، خودروها، شارژ خودکار با پلاک، روش‌های شروع شارژ، تنظیمات و خروج.
class AccountTab extends StatelessWidget {
  const AccountTab({super.key});

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    return AsyncBody(
      load: () => context.api.get('account'),
      builder: (context, d, reload) {
        final c = context.c;
        final user = asMap(d['user']);
        final vehicles = asList(d['vehicles']);
        return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 28), children: [
          Row(children: [
            const Expanded(child: Text('حساب کاربری', style: TextStyle(fontSize: 22, fontWeight: FontWeight.w800))),
            RoundIconButton(Icons.settings_outlined, onTap: () => openScreen(context, 'settings')),
          ]),
          const SizedBox(height: 14),
          AppCard(
            onTap: () => openScreen(context, 'profile'),
            child: Row(children: [
              _Avatar(initials: '${d['initials'] ?? ''}'),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${user['name'] ?? ''}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800)),
                  Text(fa(d['phone_formatted']), style: TextStyle(fontSize: 13, color: c.muted)),
                  const SizedBox(height: 6),
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                    decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(20)),
                    child: Text(fa(d['member_since']), style: TextStyle(fontSize: 11.5, color: c.accentStrong, fontWeight: FontWeight.w700)),
                  ),
                ]),
              ),
              RoundIconButton(Icons.chevron_left_rounded, size: 22, onTap: () => openScreen(context, 'profile')),
            ]),
          ),
          SecTitle('خودروهای من', action: 'مدیریت', onAction: () => openScreen(context, 'vehicle')),
          AppCard(
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              if (vehicles.isEmpty) Padding(padding: const EdgeInsets.all(8), child: Text('خودرویی ثبت نشده است.', style: TextStyle(color: c.muted))),
              for (final v in vehicles)
                ListRow(
                  icon: Icons.directions_car_outlined,
                  title: '${v['brand_model']}',
                  subtitle: fa('${v['battery_kwh']} kWh · پلاک ${v['plate_two']}${v['plate_letter']}${v['plate_three']}‑${v['plate_province']}'),
                  trailing: v['is_default'] == true
                      ? const Pill('فعال', tone: 'blue')
                      : TextButton(onPressed: () => openScreen(context, 'vehicle'), child: const Text('انتخاب')),
                ),
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: () => openScreen(context, 'vehicle'),
                icon: const Icon(Icons.add),
                label: const Text('افزودن خودروی جدید', style: TextStyle(fontWeight: FontWeight.w700)),
                style: OutlinedButton.styleFrom(minimumSize: const Size.fromHeight(52), side: BorderSide(color: c.border), shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
              ),
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(14)),
                child: Row(children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Row(children: [
                        Icon(Icons.document_scanner_outlined, size: 18, color: c.accentStrong),
                        const SizedBox(width: 6),
                        const Text('شارژ خودکار با پلاک', style: TextStyle(fontWeight: FontWeight.w700)),
                      ]),
                      const SizedBox(height: 4),
                      Text('با ورود به ایستگاه مجهز به دوربین، شارژ به‌صورت خودکار آغاز و هزینه از کیف پول کسر می‌شود.',
                          style: TextStyle(fontSize: 12, color: c.text2, height: 1.6)),
                    ]),
                  ),
                  Switch(value: app.prefs['plate_auto_charge'] != false, onChanged: (v) => app.savePrefs({'plate_auto_charge': v})),
                ]),
              ),
            ]),
          ),
          const SecTitle('فعالیت من'),
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Column(children: [
              ListRow(icon: Icons.receipt_long_outlined, title: 'سوابق و رسید جلسات', subtitle: 'جزئیات هزینه، نمودار توان و امتیازدهی', chevron: true, onTap: () => openScreen(context, 'history')),
              const Divider(),
              ListRow(icon: Icons.insights_rounded, tone: 'green', title: 'تحلیل هزینه و مصرف', subtitle: 'روند ماهانه و صرفه‌جویی نسبت به بنزین', chevron: true, onTap: () => openScreen(context, 'insights')),
              const Divider(),
              ListRow(icon: Icons.auto_awesome_rounded, tone: 'violet', title: 'دستیار هوشمند پشتیبانی', subtitle: 'پاسخ فوری ۲۴ ساعته به سؤالات شما', chevron: true, onTap: () => openScreen(context, 'assistant')),
            ]),
          ),
          const SecTitle('مسیریابی'),
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: ListRow(icon: Icons.navigation_outlined, title: 'مسیریاب برقی', subtitle: 'برنامه‌ریزی مسیر، مصرف انرژی و توقف شارژ', chevron: true, onTap: () => openScreen(context, 'navigator')),
          ),
          const SecTitle('روش‌های شروع شارژ'),
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Column(children: [
              ListRow(icon: Icons.qr_code_2_rounded, title: 'بارکد / QR جایگاه', subtitle: 'اسکن کد روی شارژر', trailing: const Pill('فعال', tone: 'blue'), onTap: () => openScreen(context, 'scan')),
              const Divider(),
              ListRow(icon: Icons.contactless_outlined, tone: 'violet', title: 'کارت RFID', subtitle: '${d['rfid_masked']} · ${d['rfid_masked'] == '—' ? 'غیرفعال' : 'فعال'}', chevron: true, onTap: () => openScreen(context, 'rfid')),
              const Divider(),
              ListRow(icon: Icons.document_scanner_outlined, tone: 'green', title: 'تشخیص پلاک خودرو', subtitle: 'شارژ خودکار با دوربین', trailing: const Pill('فعال', tone: 'blue'), onTap: () => openScreen(context, 'plate')),
            ]),
          ),
          const SecTitle('تنظیمات'),
          AppCard(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
            child: Column(children: [
              ListRow(icon: Icons.notifications_none_rounded, title: 'اعلان‌ها', chevron: true, onTap: () => openScreen(context, 'notifications')),
              const Divider(),
              ListRow(icon: Icons.dark_mode_outlined, title: 'حالت تیره', trailing: Switch(value: app.dark, onChanged: (v) => app.savePrefs({'dark_theme': v}))),
              const Divider(),
              ListRow(icon: Icons.shield_outlined, title: 'حریم خصوصی و امنیت', chevron: true, onTap: () => openScreen(context, 'privacy')),
              const Divider(),
              ListRow(icon: Icons.key_outlined, title: 'تغییر رمز عبور', chevron: true, onTap: () => openScreen(context, 'password')),
              const Divider(),
              ListRow(icon: Icons.support_agent_rounded, title: 'پشتیبانی و تماس با ما', chevron: true, onTap: () => openScreen(context, 'support')),
              const Divider(),
              ListRow(icon: Icons.receipt_outlined, title: 'قوانین و مقررات', chevron: true, onTap: () => openScreen(context, 'terms')),
            ]),
          ),
          const SizedBox(height: 18),
          AppButton('خروج از حساب', icon: Icons.logout_rounded, kind: BtnKind.ghost, onPressed: () async {
            final ok = await showDialog<bool>(
              context: context,
              builder: (ctx) => AlertDialog(
                content: const Text('از حساب کاربری خارج می‌شوید؟'),
                actions: [
                  TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('انصراف')),
                  FilledButton(style: FilledButton.styleFrom(backgroundColor: Palette.red), onPressed: () => Navigator.pop(ctx, true), child: const Text('خروج')),
                ],
              ),
            );
            if (ok == true) await app.logout();
          }),
          const SizedBox(height: 16),
          Center(child: Text('SafirCharge · نسخهٔ ${fa('1.0.0')}', style: TextStyle(fontSize: 12, color: c.muted))),
        ]);
      },
    );
  }
}

class _Avatar extends StatelessWidget {
  const _Avatar({required this.initials, this.size = 64});

  final String initials;
  final double size;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Container(
      width: size,
      height: size,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(size * .3),
        gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, Palette.orange, .6)!], begin: Alignment.topRight, end: Alignment.bottomLeft),
      ),
      child: Text(initials, style: TextStyle(color: Colors.white, fontSize: size * .34, fontWeight: FontWeight.w800)),
    );
  }
}

/// قالب مشترک صفحات فرم: هدر + محتوا + دکمهٔ ذخیره پایین.
class FormPage extends StatelessWidget {
  const FormPage({super.key, required this.title, required this.children, this.action});

  final String title;
  final List<Widget> children;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          PageHeader(title),
          Expanded(child: ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: children)),
          if (action != null) Padding(padding: const EdgeInsets.fromLTRB(18, 8, 18, 16), child: action),
        ]),
      ),
    );
  }
}

class _Note extends StatelessWidget {
  const _Note(this.text);
  final String text;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Container(
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(color: c.surface2, border: Border.all(color: c.border), borderRadius: BorderRadius.circular(14)),
      child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Icon(Icons.shield_outlined, color: Palette.green, size: 20),
        const SizedBox(width: 10),
        Expanded(child: Text(text, style: TextStyle(fontSize: 12.5, color: c.text2, height: 1.7))),
      ]),
    );
  }
}

// ───────────────────────── پروفایل ─────────────────────────

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key});

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final first = TextEditingController();
  final last = TextEditingController();
  final national = TextEditingController();
  final email = TextEditingController();
  String city = 'تهران';
  bool saving = false;
  Map<String, dynamic> account = {};
  static const cities = ['تهران', 'کرج', 'اصفهان', 'مشهد', 'شیراز', 'تبریز'];

  Future<Map<String, dynamic>> _load() async {
    final d = await context.api.get('account');
    final u = asMap(d['user']);
    account = d;
    first.text = '${u['first_name'] ?? ''}';
    last.text = '${u['last_name'] ?? ''}';
    national.text = '${u['national_id'] ?? ''}';
    email.text = '${u['email'] ?? ''}';
    city = cities.contains(u['city']) ? '${u['city']}' : 'تهران';
    return d;
  }

  Future<void> _save() async {
    setState(() => saving = true);
    final res = await runAction(context, () => context.api.post('profile', {
          'first_name': first.text.trim(),
          'last_name': last.text.trim(),
          'national_id': enDigits(national.text.trim()),
          'email': email.text.trim().isEmpty ? null : email.text.trim(),
          'city': city,
        }));
    if (!mounted) return;
    setState(() => saving = false);
    if (res != null) await context.app.refreshMe();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('ویرایش پروفایل'),
          Expanded(
            child: AsyncBody(
              refreshable: false,
              load: _load,
              builder: (context, d, _) {
                final c = context.c;
                final u = asMap(d['user']);
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                  Center(child: _Avatar(initials: '${d['initials'] ?? ''}', size: 84)),
                  const SizedBox(height: 10),
                  Center(child: Text('${u['name'] ?? ''}', style: const TextStyle(fontSize: 18, fontWeight: FontWeight.w800))),
                  Center(child: Text(fa(d['member_since']), style: TextStyle(color: c.muted, fontSize: 12.5))),
                  const SizedBox(height: 20),
                  LabeledField(label: 'نام', controller: first),
                  LabeledField(label: 'نام خانوادگی', controller: last),
                  Padding(
                    padding: const EdgeInsets.only(bottom: 14),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text('شماره موبایل', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.text2)),
                      const SizedBox(height: 7),
                      TextField(
                        controller: TextEditingController(text: fa(d['phone_formatted'])),
                        readOnly: true,
                        textDirection: TextDirection.ltr,
                        decoration: const InputDecoration(suffixIcon: Icon(Icons.verified_rounded, color: Palette.green)),
                      ),
                    ]),
                  ),
                  LabeledField(label: 'کد ملی', controller: national, keyboard: TextInputType.number, ltr: true, maxLength: 10),
                  LabeledField(label: 'ایمیل', controller: email, keyboard: TextInputType.emailAddress, ltr: true, hint: 'name@email.com'),
                  Text('شهر', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.text2)),
                  const SizedBox(height: 7),
                  DropdownButtonFormField<String>(
                    initialValue: city,
                    items: [for (final x in cities) DropdownMenuItem(value: x, child: Text(x))],
                    onChanged: (v) => setState(() => city = v ?? city),
                  ),
                  const _Note('شماره موبایل پایهٔ احراز هویت شماست و برای تغییر آن باید با پشتیبانی تماس بگیرید.'),
                ]);
              },
            ),
          ),
          Padding(padding: const EdgeInsets.fromLTRB(18, 8, 18, 16), child: AppButton('ذخیرهٔ تغییرات', icon: Icons.check_rounded, loading: saving, onPressed: _save)),
        ]),
      ),
    );
  }
}

// ───────────────────────── پلاک ─────────────────────────

const kPlateLetters = ['الف', 'ب', 'ج', 'د', 'س', 'ص', 'ط', 'ق', 'ل', 'م', 'ن', 'و', 'ه', 'ی', 'معلولین'];

/// ورودی پلاک ایرانی: دو رقم · حرف · سه رقم | استان.
class PlateEditor extends StatelessWidget {
  const PlateEditor({super.key, required this.two, required this.three, required this.province, required this.letter, required this.onLetter});

  final TextEditingController two, three, province;
  final String letter;
  final ValueChanged<String> onLetter;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    Widget seg(TextEditingController ctl, int max, String hint, int flex) => Expanded(
          flex: flex,
          child: Column(children: [
            TextField(
              controller: ctl,
              maxLength: max,
              keyboardType: TextInputType.number,
              textAlign: TextAlign.center,
              style: const TextStyle(fontSize: 20, fontWeight: FontWeight.w800),
              decoration: const InputDecoration(counterText: '', contentPadding: EdgeInsets.symmetric(vertical: 12)),
            ),
            const SizedBox(height: 4),
            Text(hint, style: TextStyle(fontSize: 11, color: c.muted)),
          ]),
        );
    return Directionality(
      textDirection: TextDirection.ltr,
      child: Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(16), border: Border.all(color: c.text.withValues(alpha: .7), width: 2)),
        child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
          seg(two, 2, 'دو رقم', 3),
          const SizedBox(width: 6),
          Expanded(
            flex: 4,
            child: Column(children: [
              DropdownButtonFormField<String>(
                initialValue: kPlateLetters.contains(letter) ? letter : kPlateLetters.first,
                isExpanded: true,
                items: [for (final l in kPlateLetters) DropdownMenuItem(value: l, child: Center(child: Text(l, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800))))],
                onChanged: (v) => onLetter(v ?? letter),
                decoration: const InputDecoration(contentPadding: EdgeInsets.symmetric(horizontal: 8, vertical: 12)),
              ),
              const SizedBox(height: 4),
              Text('حرف', style: TextStyle(fontSize: 11, color: c.muted)),
            ]),
          ),
          const SizedBox(width: 6),
          seg(three, 3, 'سه رقم', 4),
          Container(width: 2, height: 50, color: c.text.withValues(alpha: .5), margin: const EdgeInsets.symmetric(horizontal: 6)),
          seg(province, 2, 'استان', 3),
        ]),
      ),
    );
  }
}

/// نمایش پلاک به ترتیب درست «۲۳ ب ۷۱۳ | ۴۶» (بدون جابه‌جایی دوجهته متن).
class PlateView extends StatelessWidget {
  const PlateView(this.v, {super.key, this.size = 18});

  final Map<String, dynamic> v;
  final double size;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(fontSize: size, fontWeight: FontWeight.w800);
    return Directionality(
      textDirection: TextDirection.ltr,
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Text(fa(v['plate_two']), style: style),
        const SizedBox(width: 8),
        Text('${v['plate_letter']}', style: style),
        const SizedBox(width: 8),
        Text(fa(v['plate_three']), style: style),
        Container(width: 1.5, height: size + 6, margin: const EdgeInsets.symmetric(horizontal: 10), color: context.c.muted),
        Text(fa(v['plate_province']), style: style),
      ]),
    );
  }
}

class PlateScreen extends StatefulWidget {
  const PlateScreen({super.key});

  @override
  State<PlateScreen> createState() => _PlateScreenState();
}

class _PlateScreenState extends State<PlateScreen> {
  final two = TextEditingController(), three = TextEditingController(), province = TextEditingController();
  String letter = 'ب';
  bool saving = false;

  Future<Map<String, dynamic>> _load() async {
    final d = await context.api.get('vehicles');
    final v = asList(d['vehicles']).firstOrNull;
    if (v != null) {
      two.text = fa(v['plate_two']);
      three.text = fa(v['plate_three']);
      province.text = fa(v['plate_province']);
      letter = '${v['plate_letter']}';
    }
    return d;
  }

  Future<void> _save() async {
    setState(() => saving = true);
    await runAction(context, () => context.api.post('plate', {
          'plate_two': enDigits(two.text),
          'plate_letter': letter,
          'plate_three': enDigits(three.text),
          'plate_province': enDigits(province.text),
        }));
    if (mounted) setState(() => saving = false);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('تشخیص پلاک خودرو'),
          Expanded(
            child: AsyncBody(
              refreshable: false,
              load: _load,
              builder: (context, d, _) {
                final c = context.c;
                final vehicles = asList(d['vehicles']);
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(color: c.accentSoft, borderRadius: BorderRadius.circular(18)),
                    child: Row(children: [
                      const IconTile(Icons.document_scanner_outlined, size: 48),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text('دوربین ایستگاه پلاک خودروی پیش‌فرض شما را می‌خواند و شارژ را بدون اسکن QR آغاز می‌کند.',
                            style: TextStyle(fontSize: 13, color: c.text2, height: 1.7)),
                      ),
                    ]),
                  ),
                  const SizedBox(height: 18),
                  if (vehicles.isEmpty)
                    EmptyState('ابتدا یک خودرو ثبت کنید.', icon: Icons.directions_car_outlined, action: AppButton('افزودن خودرو', expand: false, onPressed: () => openScreen(context, 'vehicle')))
                  else ...[
                    Text('پلاک ${vehicles.first['brand_model']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                    const SizedBox(height: 12),
                    PlateEditor(two: two, three: three, province: province, letter: letter, onLetter: (l) => setState(() => letter = l)),
                    const SizedBox(height: 16),
                    ListRow(
                      icon: Icons.bolt_rounded,
                      tone: 'green',
                      title: 'شارژ خودکار با پلاک',
                      subtitle: 'پس از تشخیص، شارژ آغاز و هزینه از کیف پول کسر می‌شود',
                      trailing: Switch(value: context.app.prefs['plate_auto_charge'] != false, onChanged: (v) async {
                        await context.app.savePrefs({'plate_auto_charge': v});
                        if (mounted) setState(() {});
                      }),
                    ),
                  ],
                ]);
              },
            ),
          ),
          Padding(padding: const EdgeInsets.fromLTRB(18, 8, 18, 16), child: AppButton('ذخیرهٔ پلاک', icon: Icons.check_rounded, loading: saving, onPressed: _save)),
        ]),
      ),
    );
  }
}

// ───────────────────────── خودروها ─────────────────────────

class VehiclesScreen extends StatefulWidget {
  const VehiclesScreen({super.key});

  @override
  State<VehiclesScreen> createState() => _VehiclesScreenState();
}

class _VehiclesScreenState extends State<VehiclesScreen> {
  final _body = GlobalKey<AsyncBodyState>();

  Future<void> _edit(Map<String, dynamic> d, [Map<String, dynamic>? v]) async {
    final saved = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (_) => _VehicleForm(models: (d['model_options'] as List?)?.map((e) => '$e').toList() ?? const [], vehicle: v),
    );
    if (saved == true) _body.currentState?.reload();
  }

  Future<void> _act(Future<Map<String, dynamic>> Function() fn) async {
    final res = await runAction(context, fn);
    if (res != null) _body.currentState?.reload();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('خودروهای من'),
          Expanded(
            child: AsyncBody(
              key: _body,
              load: () => context.api.get('vehicles'),
              builder: (context, d, reload) {
                final c = context.c;
                final vehicles = asList(d['vehicles']);
                final audit = asList(d['audit']);
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                  if (vehicles.isEmpty) const EmptyState('خودرویی ثبت نشده است.', icon: Icons.directions_car_outlined),
                  for (final v in vehicles)
                    AppCard(
                      margin: const EdgeInsets.only(bottom: 12),
                      border: v['is_default'] == true ? c.accent : null,
                      child: Column(children: [
                        Row(children: [
                          const IconTile(Icons.directions_car_filled_outlined, size: 48),
                          const SizedBox(width: 12),
                          Expanded(
                            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                              Text('${v['brand_model']}', style: const TextStyle(fontWeight: FontWeight.w800, fontSize: 15)),
                              const SizedBox(height: 3),
                              Text(fa('${v['battery_kwh']} kWh'), style: TextStyle(fontSize: 12, color: c.muted)),
                            ]),
                          ),
                          if (v['is_default'] == true) const Pill('پیش‌فرض', tone: 'blue'),
                        ]),
                        const SizedBox(height: 12),
                        Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(vertical: 10),
                          decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(12), border: Border.all(color: c.border)),
                          alignment: Alignment.center,
                          child: PlateView(v),
                        ),
                        const SizedBox(height: 10),
                        Row(children: [
                          if (v['is_default'] != true)
                            TextButton.icon(onPressed: () => _act(() => context.api.post('vehicles/${v['id']}/default')), icon: const Icon(Icons.star_outline_rounded), label: const Text('پیش‌فرض')),
                          const Spacer(),
                          IconButton(tooltip: 'ویرایش', onPressed: () => _edit(d, v), icon: const Icon(Icons.edit_outlined)),
                          IconButton(
                            tooltip: 'حذف',
                            color: Palette.red,
                            onPressed: () async {
                              final ok = await _confirm(context, 'خودرو «${v['brand_model']}» حذف شود؟');
                              if (ok) await _act(() => context.api.delete('vehicles/${v['id']}'));
                            },
                            icon: const Icon(Icons.delete_outline_rounded),
                          ),
                        ]),
                      ]),
                    ),
                  const SizedBox(height: 4),
                  AppButton('افزودن خودروی جدید', icon: Icons.add, kind: BtnKind.soft, onPressed: () => _edit(d)),
                  if (audit.isNotEmpty) ...[
                    const SecTitle('تاریخچهٔ تغییرات'),
                    AppCard(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                      child: Column(children: [
                        for (final a in audit.take(10)) ListRow(icon: Icons.history_rounded, tone: '${a['color']}', title: '${a['title']}', subtitle: '${a['detail']} · ${a['time']}'),
                      ]),
                    ),
                  ],
                ]);
              },
            ),
          ),
        ]),
      ),
    );
  }
}

Future<bool> _confirm(BuildContext context, String text) async =>
    await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        content: Text(text),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('انصراف')),
          FilledButton(style: FilledButton.styleFrom(backgroundColor: Palette.red), onPressed: () => Navigator.pop(ctx, true), child: const Text('تأیید')),
        ],
      ),
    ) ==
    true;

class _VehicleForm extends StatefulWidget {
  const _VehicleForm({required this.models, this.vehicle});

  final List<String> models;
  final Map<String, dynamic>? vehicle;

  @override
  State<_VehicleForm> createState() => _VehicleFormState();
}

class _VehicleFormState extends State<_VehicleForm> {
  late String? model = widget.vehicle?['brand_model']?.toString() ?? (widget.models.isNotEmpty ? widget.models.first : null);
  late final battery = TextEditingController(text: '${widget.vehicle?['battery_kwh'] ?? 60}');
  late final two = TextEditingController(text: fa(widget.vehicle?['plate_two'] ?? '۱۲'));
  late final three = TextEditingController(text: fa(widget.vehicle?['plate_three'] ?? '۳۴۵'));
  late final province = TextEditingController(text: fa(widget.vehicle?['plate_province'] ?? '۱۱'));
  late String letter = widget.vehicle?['plate_letter']?.toString() ?? 'س';
  late bool isDefault = widget.vehicle?['is_default'] == true;
  final custom = TextEditingController();
  bool saving = false;

  Future<void> _save() async {
    final brandModel = (model == '__custom' ? custom.text : model)?.trim() ?? '';
    if (brandModel.isEmpty) return toast(context, 'برند و مدل خودرو را انتخاب کنید.', error: true);
    setState(() => saving = true);
    final body = {
      'brand_model': brandModel,
      'battery_kwh': int.tryParse(enDigits(battery.text)) ?? 60,
      'plate_two': enDigits(two.text),
      'plate_letter': letter,
      'plate_three': enDigits(three.text),
      'plate_province': enDigits(province.text),
      'is_default': isDefault,
    };
    final v = widget.vehicle;
    final res = await runAction(context, () => v == null ? context.api.post('vehicles', body) : context.api.put('vehicles/${v['id']}', body));
    if (!mounted) return;
    setState(() => saving = false);
    if (res != null) Navigator.pop(context, true);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final options = {...widget.models, if (model != null && model != '__custom') model!}.toList();
    return Padding(
      padding: EdgeInsets.fromLTRB(20, 10, 20, 20 + MediaQuery.of(context).viewInsets.bottom + MediaQuery.of(context).padding.bottom),
      child: SingleChildScrollView(
        child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.stretch, children: [
          Center(child: Container(width: 42, height: 5, decoration: BoxDecoration(color: c.border, borderRadius: BorderRadius.circular(5)))),
          const SizedBox(height: 14),
          Text(widget.vehicle == null ? 'افزودن خودرو' : 'ویرایش خودرو', style: const TextStyle(fontSize: 17, fontWeight: FontWeight.w800)),
          const SizedBox(height: 16),
          Text('برند و مدل', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.text2)),
          const SizedBox(height: 7),
          DropdownButtonFormField<String>(
            initialValue: model,
            isExpanded: true,
            items: [
              for (final m in options) DropdownMenuItem(value: m, child: Text(m, overflow: TextOverflow.ellipsis)),
              const DropdownMenuItem(value: '__custom', child: Text('سایر (ورود دستی)')),
            ],
            onChanged: (v) => setState(() => model = v),
          ),
          if (model == '__custom') ...[const SizedBox(height: 10), TextField(controller: custom, decoration: const InputDecoration(hintText: 'مثلاً BYD Atto 3'))],
          const SizedBox(height: 14),
          LabeledField(label: 'ظرفیت باتری (kWh)', controller: battery, keyboard: TextInputType.number),
          Text('شماره پلاک', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.text2)),
          const SizedBox(height: 7),
          PlateEditor(two: two, three: three, province: province, letter: letter, onLetter: (l) => setState(() => letter = l)),
          SwitchListTile(
            contentPadding: EdgeInsets.zero,
            value: isDefault,
            onChanged: (v) => setState(() => isDefault = v),
            title: const Text('خودروی پیش‌فرض', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 14)),
          ),
          const SizedBox(height: 8),
          AppButton('ذخیره', icon: Icons.check_rounded, loading: saving, onPressed: _save),
        ]),
      ),
    );
  }
}

// ───────────────────────── RFID ─────────────────────────

class RfidScreen extends StatefulWidget {
  const RfidScreen({super.key});

  @override
  State<RfidScreen> createState() => _RfidScreenState();
}

class _RfidScreenState extends State<RfidScreen> {
  final _body = GlobalKey<AsyncBodyState>();
  final tag = TextEditingController();
  final label = TextEditingController();
  bool adding = false;

  Future<void> _act(Future<Map<String, dynamic>> Function() fn) async {
    final res = await runAction(context, fn);
    if (res != null) _body.currentState?.reload();
  }

  Future<void> _add() async {
    if (tag.text.trim().isEmpty) return toast(context, 'شمارهٔ کارت را وارد کنید.', error: true);
    setState(() => adding = true);
    final res = await runAction(context, () => context.api.post('rfid', {'id_tag': enDigits(tag.text.trim()).toUpperCase(), 'card_label': label.text.trim()}));
    if (!mounted) return;
    setState(() => adding = false);
    if (res != null) {
      tag.clear();
      label.clear();
      _body.currentState?.reload();
    }
  }

  Future<void> _rename(Map<String, dynamic> card) async {
    final ctl = TextEditingController(text: '${card['label'] ?? ''}');
    final name = await showDialog<String>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('نام کارت', style: TextStyle(fontWeight: FontWeight.w800, fontSize: 17)),
        content: TextField(controller: ctl, decoration: const InputDecoration(hintText: 'مثلاً کارت اصلی')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('انصراف')),
          FilledButton(onPressed: () => Navigator.pop(ctx, ctl.text), child: const Text('ذخیره')),
        ],
      ),
    );
    if (name != null) await _act(() => context.api.put('rfid/${card['id']}', {'card_label': name}));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('کارت RFID'),
          Expanded(
            child: AsyncBody(
              key: _body,
              load: () => context.api.get('rfid'),
              builder: (context, d, reload) {
                final c = context.c;
                final cards = asList(d['cards']);
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                  if (cards.isEmpty) const EmptyState('کارت RFID ثبت نشده است.', icon: Icons.contactless_outlined),
                  for (final card in cards)
                    Container(
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(18),
                      decoration: BoxDecoration(
                        borderRadius: BorderRadius.circular(20),
                        gradient: card['status'] == 'active'
                            ? LinearGradient(colors: [c.accent, Color.lerp(c.accent, Palette.violet, .6)!])
                            : LinearGradient(colors: [c.muted, c.text2]),
                      ),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Row(children: [
                          const Icon(Icons.contactless_rounded, color: Colors.white, size: 28),
                          const SizedBox(width: 10),
                          Expanded(child: Text('${card['label'] ?? 'کارت شارژ'}', style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 15))),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
                            decoration: BoxDecoration(color: Colors.white.withValues(alpha: .2), borderRadius: BorderRadius.circular(20)),
                            child: Text('${card['status_label']}', style: const TextStyle(color: Colors.white, fontSize: 11.5, fontWeight: FontWeight.w700)),
                          ),
                        ]),
                        const SizedBox(height: 18),
                        Text(fa(card['id_tag_formatted']), textDirection: TextDirection.ltr, style: const TextStyle(color: Colors.white, fontSize: 20, fontWeight: FontWeight.w800, letterSpacing: 2)),
                        const SizedBox(height: 10),
                        Row(children: [
                          _CardBtn(Icons.edit_outlined, 'نام', () => _rename(card)),
                          if (card['status'] == 'active')
                            _CardBtn(Icons.block_rounded, 'مسدود', () => _act(() => context.api.post('rfid/${card['id']}/block')))
                          else
                            _CardBtn(Icons.check_circle_outline, 'فعال‌سازی', () => _act(() => context.api.post('rfid/${card['id']}/activate'))),
                          _CardBtn(Icons.delete_outline_rounded, 'حذف', () async {
                            if (await _confirm(context, 'کارت ${card['id_tag']} حذف شود؟')) await _act(() => context.api.delete('rfid/${card['id']}'));
                          }),
                        ]),
                      ]),
                    ),
                  const SecTitle('افزودن کارت جدید'),
                  AppCard(
                    child: Column(children: [
                      LabeledField(label: 'شمارهٔ کارت RFID', controller: tag, hint: 'CN00001234', ltr: true, textAlign: TextAlign.center, maxLength: 32),
                      LabeledField(label: 'نام کارت (اختیاری)', controller: label, hint: 'مثلاً کارت اصلی'),
                      AppButton('ثبت کارت', icon: Icons.add, loading: adding, onPressed: _add),
                    ]),
                  ),
                  const _Note('با زدن کارت روی شارژر، شارژ بدون نیاز به گوشی آغاز و هزینه از کیف پول شما کسر می‌شود. در صورت گم‌شدن، کارت را مسدود کنید.'),
                ]);
              },
            ),
          ),
        ]),
      ),
    );
  }
}

class _CardBtn extends StatelessWidget {
  const _CardBtn(this.icon, this.label, this.onTap);

  final IconData icon;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsetsDirectional.only(end: 8),
        child: TextButton.icon(
          onPressed: onTap,
          style: TextButton.styleFrom(foregroundColor: Colors.white, backgroundColor: Colors.white.withValues(alpha: .14), padding: const EdgeInsets.symmetric(horizontal: 12)),
          icon: Icon(icon, size: 17),
          label: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
        ),
      );
}

// ───────────────────────── رمز عبور ─────────────────────────

class PasswordScreen extends StatefulWidget {
  const PasswordScreen({super.key});

  @override
  State<PasswordScreen> createState() => _PasswordScreenState();
}

class _PasswordScreenState extends State<PasswordScreen> {
  final cur = TextEditingController(), next = TextEditingController(), rep = TextEditingController();
  bool saving = false;

  @override
  void initState() {
    super.initState();
    next.addListener(() => setState(() {}));
  }

  Future<void> _save() async {
    if (next.text.length < 8) return toast(context, 'رمز جدید باید حداقل ۸ کاراکتر باشد.', error: true);
    if (next.text != rep.text) return toast(context, 'تکرار رمز جدید یکسان نیست.', error: true);
    setState(() => saving = true);
    final res = await runAction(context, () => context.api.post('password', {'current_password': cur.text, 'password': next.text, 'password_confirmation': rep.text}));
    if (!mounted) return;
    setState(() => saving = false);
    if (res != null) Navigator.pop(context);
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final strong = next.text.length >= 8 && RegExp(r'[0-9]').hasMatch(next.text) && RegExp(r'[A-Za-z]').hasMatch(next.text);
    return FormPage(
      title: 'تغییر رمز عبور',
      action: AppButton('ذخیرهٔ رمز جدید', icon: Icons.check_rounded, loading: saving, onPressed: _save),
      children: [
        Center(child: IconTile(Icons.key_rounded, size: 72)),
        const SizedBox(height: 18),
        LabeledField(label: 'رمز عبور فعلی', controller: cur, obscure: true, hint: 'رمز فعلی'),
        LabeledField(label: 'رمز عبور جدید', controller: next, obscure: true, hint: 'رمز جدید'),
        LabeledField(label: 'تکرار رمز جدید', controller: rep, obscure: true, hint: 'تکرار رمز جدید'),
        Row(children: [
          Icon(strong ? Icons.check_circle_rounded : Icons.info_outline, size: 18, color: strong ? Palette.green : c.muted),
          const SizedBox(width: 6),
          Expanded(child: Text('حداقل ۸ کاراکتر، ترکیبی از حروف و اعداد', style: TextStyle(fontSize: 12.5, color: c.text2))),
        ]),
      ],
    );
  }
}

// ───────────────────────── تنظیمات ─────────────────────────

class SettingsScreen extends StatelessWidget {
  const SettingsScreen({super.key});

  static const accents = ['#0E6BA8', '#16A34A', '#F97316', '#7C3AED', '#0891B2'];

  @override
  Widget build(BuildContext context) {
    final app = AppScope.of(context);
    final c = context.c;
    final p = app.prefs;
    Future<void> save(Map<String, dynamic> m) async {
      try {
        await app.savePrefs(m);
        if (context.mounted) toast(context, 'تنظیمات ذخیره شد');
      } catch (e) {
        if (context.mounted) toast(context, '$e', error: true);
      }
    }

    Widget sw(IconData icon, String title, String key, {bool def = true}) => ListRow(
          icon: icon,
          title: title,
          trailing: Switch(value: p[key] is bool ? p[key] as bool : def, onChanged: (v) => save({key: v})),
        );
    Widget seg(String key, List<(Object, String)> opts, Object def) => Segmented<Object>(options: opts, value: p[key] ?? def, onChanged: (v) => save({key: v}));

    return FormPage(title: 'تنظیمات', children: [
      const SecTitle('ظاهر برنامه', top: 4),
      AppCard(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        child: Column(children: [
          sw(Icons.dark_mode_outlined, 'حالت تیره', 'dark_theme', def: false),
          const Divider(),
          ListRow(
            icon: Icons.palette_outlined,
            title: 'رنگ برنامه',
            trailing: Row(mainAxisSize: MainAxisSize.min, children: [
              for (final a in accents)
                GestureDetector(
                  onTap: () => save({'accent': a}),
                  child: Container(
                    margin: const EdgeInsets.symmetric(horizontal: 3),
                    width: 24,
                    height: 24,
                    decoration: BoxDecoration(
                      color: Color(int.parse('FF${a.substring(1)}', radix: 16)),
                      shape: BoxShape.circle,
                      border: Border.all(color: (p['accent'] ?? accents.first) == a ? c.text : Colors.transparent, width: 2.5),
                    ),
                  ),
                ),
            ]),
          ),
        ]),
      ),
      const SecTitle('واحدها'),
      AppCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('نمایش انرژی تحویلی هنگام شارژ', style: TextStyle(fontWeight: FontWeight.w700)),
          const SizedBox(height: 8),
          seg('energy_unit', const [('kwh', 'kWh'), ('pct', 'درصد')], 'kwh'),
        ]),
      ),
      const SecTitle('اعلان‌ها'),
      AppCard(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        child: Column(children: [
          sw(Icons.notifications_none_rounded, 'پوش اپلیکیشن', 'notify_push'),
          const Divider(),
          sw(Icons.sms_outlined, 'پیامک پایان شارژ', 'notify_sms_end'),
          const Divider(),
          sw(Icons.account_balance_wallet_outlined, 'هشدار کمبود موجودی', 'notify_low_balance'),
        ]),
      ),
      const SecTitle('ترجیحات شارژ'),
      AppCard(
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          const Text('حد توقف خودکار شارژ', style: TextStyle(fontWeight: FontWeight.w700)),
          Text('شارژ تا این درصد ادامه می‌یابد', style: TextStyle(fontSize: 12, color: c.muted)),
          const SizedBox(height: 10),
          seg('charge_target_soc', const [(80, '۸۰٪'), (90, '۹۰٪'), (100, '۱۰۰٪')], 80),
          const SizedBox(height: 6),
          sw(Icons.document_scanner_outlined, 'شارژ خودکار با پلاک', 'plate_auto_charge'),
        ]),
      ),
      const SecTitle('سرور'),
      AppCard(
        padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
        child: ListRow(icon: Icons.dns_outlined, title: 'آدرس سرور', subtitle: app.baseUrl, chevron: true, onTap: () => openScreen(context, 'server')),
      ),
      const SizedBox(height: 16),
      Center(child: Text('SafirCharge · نسخهٔ ${fa('1.0.0')}', style: TextStyle(fontSize: 12, color: c.muted))),
    ]);
  }
}
