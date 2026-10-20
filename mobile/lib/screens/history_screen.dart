import 'package:flutter/material.dart';

import '../core/fmt.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

/// سوابق شارژ: خلاصهٔ ماه، صرفه‌جویی CO₂ و جلسات اخیر با جست‌وجو.
class HistoryScreen extends StatefulWidget {
  const HistoryScreen({super.key});

  @override
  State<HistoryScreen> createState() => _HistoryScreenState();
}

class _HistoryScreenState extends State<HistoryScreen> {
  final search = TextEditingController();

  @override
  void dispose() {
    search.dispose();
    super.dispose();
  }

  List<Map<String, dynamic>> _sessions = [];

  void _export() {
    final rows = _sessions.map((h) => '${enDigits('${h['date']}')},"${h['n']}",${enDigits('${h['en']}').replaceAll(' kWh', '')},${enDigits('${h['dur']}')},${enDigits('${h['amt']}').replaceAll(',', '')},${h['m']}');
    shareText(context, ['تاریخ,ایستگاه,انرژی (kWh),مدت,هزینه (تومان),روش', ...rows].join('\n'));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          PageHeader('سوابق شارژ', actions: [RoundIconButton(Icons.download_outlined, onTap: _export)]),
          Expanded(
            child: AsyncBody(
              load: () => context.api.get('history'),
              builder: (context, d, reload) {
                final c = context.c;
                final m = asMap(d['month_stats']);
                final q = search.text.trim();
                _sessions = asList(d['sessions']);
                final list = _sessions.where((h) => q.isEmpty || '${h['n']} ${h['date']} ${h['m']}'.contains(q) || enDigits('${h['date']}').contains(enDigits(q))).toList();

                Widget item(String v, String l) => Expanded(
                      child: Column(children: [
                        Text(v, style: const TextStyle(fontSize: 19, fontWeight: FontWeight.w800)),
                        const SizedBox(height: 2),
                        Text(l, style: TextStyle(fontSize: 12, color: c.muted)),
                      ]),
                    );

                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 28), children: [
                  AppCard(
                    padding: const EdgeInsets.symmetric(vertical: 18),
                    child: Row(children: [
                      item(fa(m['sessions']), 'جلسه'),
                      Container(width: 1, height: 34, color: c.border),
                      item('${m['energy'] ?? '۰'} kWh', 'انرژی'),
                      Container(width: 1, height: 34, color: c.border),
                      item('${m['cost'] ?? '۰'}', 'هزینه (ت)'),
                    ]),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(color: c.greenSoft, borderRadius: BorderRadius.circular(18)),
                    child: Row(children: [
                      const IconTile(Icons.eco_rounded, tone: 'green', size: 44),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${m['co2_kg'] ?? '۰'} کیلوگرم CO₂ کمتر', style: const TextStyle(fontWeight: FontWeight.w700)),
                          Text('معادل کاشت ${m['trees'] ?? '۰'} اصله درخت در این ماه 🌱', style: TextStyle(fontSize: 12, color: c.text2)),
                        ]),
                      ),
                    ]),
                  ),
                  const SecTitle('جلسات اخیر'),
                  TextField(
                    controller: search,
                    onChanged: (_) => setState(() {}),
                    decoration: const InputDecoration(hintText: 'جستجو در جلسات (ایستگاه، تاریخ، روش…)', prefixIcon: Icon(Icons.search_rounded)),
                  ),
                  const SizedBox(height: 12),
                  if (list.isEmpty) EmptyState(q.isEmpty ? 'هنوز جلسهٔ شارژی ثبت نشده است.' : 'جلسه‌ای با این جستجو یافت نشد.', icon: Icons.history_rounded),
                  for (final h in list) _session(c, h),
                ]);
              },
            ),
          ),
        ]),
      ),
    );
  }

  Widget _session(Palette c, Map<String, dynamic> h) {
    final st = (h['st'] as List?) ?? const ['—', 'pill-muted'];
    final ok = st.length > 1 && st[1] == 'pill-green';
    Widget stat(String l, String v) => Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(l, style: TextStyle(fontSize: 11.5, color: c.muted)),
            const SizedBox(height: 2),
            Text(v, style: const TextStyle(fontSize: 13, fontWeight: FontWeight.w800)),
          ]),
        );
    return AppCard(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      child: Column(children: [
        Row(children: [
          IconTile(Icons.bolt_rounded, tone: ok ? 'blue' : 'red', size: 42),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Row(children: [
                Expanded(child: Text('${h['n']}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 14))),
                Pill.web('${st[0]}', '${st.length > 1 ? st[1] : 'pill-muted'}', dot: false),
              ]),
              const SizedBox(height: 4),
              Text('${h['date']} · ${h['m']}', style: TextStyle(fontSize: 12, color: c.muted)),
            ]),
          ),
        ]),
        const SizedBox(height: 12),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(color: c.surface2, borderRadius: BorderRadius.circular(12)),
          child: Row(children: [
            stat('انرژی', '${h['en']}'),
            stat('مدت', '${h['dur']}'),
            stat('هزینه', '${h['amt']} ت'),
          ]),
        ),
      ]),
    );
  }
}
