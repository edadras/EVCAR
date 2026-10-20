import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../core/fmt.dart';
import '../core/nav.dart';
import '../core/state.dart';
import '../core/theme.dart';
import '../widgets/common.dart';

const _categories = [
  ('payment', 'پرداخت و کیف پول', Icons.account_balance_wallet_outlined, 'orange'),
  ('charger', 'مشکل در شارژ', Icons.bolt_rounded, 'blue'),
  ('rfid', 'کارت RFID', Icons.contactless_outlined, 'green'),
  ('station', 'ایستگاه و دستگاه', Icons.place_outlined, 'violet'),
  ('account', 'حساب کاربری', Icons.person_outline_rounded, 'blue'),
  ('other', 'سایر موارد', Icons.receipt_outlined, 'orange'),
];

(IconData, String) _catIcon(String? cat) {
  final m = _categories.where((c) => c.$1 == cat).firstOrNull;
  return m == null ? (Icons.bolt_rounded, 'orange') : (m.$3, m.$4);
}

String _statusTone(String? s) => switch (s) { 'resolved' => 'green', 'closed' => 'muted', _ => 'blue' };

/// پشتیبانی: تماس، گفتگو، درخواست‌ها (تیکت) و پرسش‌های متداول.
class SupportScreen extends StatelessWidget {
  const SupportScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('پشتیبانی و تماس با ما'),
          Expanded(
            child: AsyncBody(
              load: () => context.api.get('support'),
              builder: (context, d, reload) {
                final c = context.c;
                final contact = asMap(d['contact']);
                final tickets = asList(d['tickets']);
                final faq = asList(contact['faq']);
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                  Container(
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      borderRadius: BorderRadius.circular(22),
                      gradient: LinearGradient(colors: [c.accent, Color.lerp(c.accent, Palette.orange, .5)!]),
                    ),
                    child: Row(children: [
                      Container(
                        width: 50,
                        height: 50,
                        decoration: BoxDecoration(color: Colors.white.withValues(alpha: .18), borderRadius: BorderRadius.circular(15)),
                        child: const Icon(Icons.support_agent_rounded, color: Colors.white, size: 28),
                      ),
                      const SizedBox(width: 14),
                      const Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('چطور می‌توانیم کمک کنیم؟', style: TextStyle(color: Colors.white, fontWeight: FontWeight.w800, fontSize: 16)),
                          SizedBox(height: 4),
                          Text('پشتیبانی ۲۴ ساعته، ۷ روز هفته', style: TextStyle(color: Colors.white70, fontSize: 12.5)),
                        ]),
                      ),
                    ]),
                  ),
                  const SizedBox(height: 14),
                  Row(children: [
                    Expanded(
                      child: AppCard(
                        onTap: () => launchUrl(Uri.parse('tel:${contact['phone'] ?? '1570'}')),
                        child: Column(children: [
                          const IconTile(Icons.phone_in_talk_outlined, tone: 'green'),
                          const SizedBox(height: 8),
                          const Text('تماس تلفنی', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                          Text(fa(contact['phone'] ?? '1570'), style: TextStyle(fontSize: 12, color: c.muted)),
                        ]),
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: AppCard(
                        onTap: () => openScreen(context, tickets.isEmpty ? 'ticket/new' : 'chat', tickets.isEmpty ? {} : {'id': tickets.first['id']}),
                        child: Column(children: [
                          const IconTile(Icons.chat_bubble_outline_rounded),
                          const SizedBox(height: 8),
                          const Text('گفتگوی آنلاین', style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13)),
                          Text('پاسخ فوری', style: TextStyle(fontSize: 12, color: c.muted)),
                        ]),
                      ),
                    ),
                  ]),
                  SecTitle('درخواست‌های من', action: 'جدید +', onAction: () => openScreen(context, 'ticket/new')),
                  AppCard(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
                    child: tickets.isEmpty
                        ? Padding(padding: const EdgeInsets.all(14), child: Text('درخواستی ثبت نشده است.', style: TextStyle(color: c.muted)))
                        : Column(children: [
                            for (final (i, t) in tickets.take(5).indexed) ...[
                              if (i > 0) const Divider(),
                              ListRow(
                                icon: _catIcon(t['category']?.toString()).$1,
                                tone: _catIcon(t['category']?.toString()).$2,
                                title: '${t['subject']}',
                                subtitle: fa('${t['number']} · ${t['created']}'),
                                trailing: Pill('${t['status_label']}', tone: _statusTone(t['status']?.toString())),
                                onTap: () => openScreen(context, 'ticket', {'id': t['id']}),
                              ),
                            ],
                          ]),
                  ),
                  const SecTitle('پرسش‌های متداول'),
                  AppCard(
                    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                    child: Column(children: [
                      for (final f in faq)
                        Theme(
                          data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
                          child: ExpansionTile(
                            title: Text('${f['q']}', style: const TextStyle(fontSize: 13.5, fontWeight: FontWeight.w700)),
                            childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
                            expandedAlignment: AlignmentDirectional.centerStart,
                            children: [Text('${f['a']}', style: TextStyle(fontSize: 13, color: c.text2, height: 1.8))],
                          ),
                        ),
                    ]),
                  ),
                ]);
              },
            ),
          ),
        ]),
      ),
    );
  }
}

class TicketNewScreen extends StatefulWidget {
  const TicketNewScreen({super.key});

  @override
  State<TicketNewScreen> createState() => _TicketNewScreenState();
}

class _TicketNewScreenState extends State<TicketNewScreen> {
  String category = 'payment';
  int? sessionId;
  final subject = TextEditingController();
  final body = TextEditingController();
  bool sending = false;

  Future<void> _send() async {
    if (subject.text.trim().isEmpty || body.text.trim().isEmpty) return toast(context, 'موضوع و شرح مشکل را وارد کنید.', error: true);
    setState(() => sending = true);
    final res = await runAction(context, () => context.api.post('tickets', {
          'category': category,
          'subject': subject.text.trim(),
          'body': body.text.trim(),
          'session_id': sessionId,
        }));
    if (!mounted) return;
    setState(() => sending = false);
    final id = asInt(asMap(res?['params'])['id'], -1);
    if (res != null) {
      if (id > 0) {
        await Navigator.of(context).pushReplacement(MaterialPageRoute(builder: (_) => TicketScreen(id: id)));
      } else {
        Navigator.pop(context);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('درخواست پشتیبانی جدید'),
          Expanded(
            child: AsyncBody(
              refreshable: false,
              load: () => context.api.get('tickets/form'),
              builder: (context, d, _) {
                final c = context.c;
                final sessions = asList(d['sessions']);
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 24), children: [
                  Text('موضوع درخواست', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.text2)),
                  const SizedBox(height: 10),
                  GridView.count(
                    crossAxisCount: 3,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    mainAxisSpacing: 8,
                    crossAxisSpacing: 8,
                    childAspectRatio: 1.05,
                    children: [
                      for (final (id, label, icon, tone) in _categories)
                        GestureDetector(
                          onTap: () => setState(() => category = id),
                          child: Container(
                            decoration: BoxDecoration(
                              color: category == id ? c.accentSoft : c.surface,
                              borderRadius: BorderRadius.circular(14),
                              border: Border.all(color: category == id ? c.accent : c.border, width: 1.4),
                            ),
                            child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                              IconTile(icon, tone: tone, size: 38),
                              const SizedBox(height: 8),
                              Text(label, textAlign: TextAlign.center, style: const TextStyle(fontSize: 11.5, fontWeight: FontWeight.w700)),
                            ]),
                          ),
                        ),
                    ],
                  ),
                  const SizedBox(height: 18),
                  Text('جلسهٔ شارژ مرتبط (اختیاری)', style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: c.text2)),
                  const SizedBox(height: 7),
                  DropdownButtonFormField<int?>(
                    initialValue: sessionId,
                    isExpanded: true,
                    items: [
                      const DropdownMenuItem<int?>(value: null, child: Text('— انتخاب جلسهٔ شارژ —')),
                      for (final s in sessions) DropdownMenuItem<int?>(value: asInt(s['id']), child: Text(fa(s['label']), overflow: TextOverflow.ellipsis)),
                    ],
                    onChanged: (v) => setState(() => sessionId = v),
                  ),
                  const SizedBox(height: 14),
                  LabeledField(label: 'عنوان', controller: subject, hint: 'خلاصهٔ مشکل'),
                  LabeledField(label: 'شرح مشکل', controller: body, hint: 'جزئیات را بنویسید…', maxLines: 5),
                ]);
              },
            ),
          ),
          Padding(padding: const EdgeInsets.fromLTRB(18, 8, 18, 16), child: AppButton('ثبت درخواست', icon: Icons.send_rounded, loading: sending, onPressed: _send)),
        ]),
      ),
    );
  }
}

/// جزئیات تیکت و گفتگو با پشتیبانی (ticket + chat وب‌اپ).
class TicketScreen extends StatefulWidget {
  const TicketScreen({super.key, required this.id});

  final int id;

  @override
  State<TicketScreen> createState() => _TicketScreenState();
}

class _TicketScreenState extends State<TicketScreen> {
  final _body = GlobalKey<AsyncBodyState>();
  final msg = TextEditingController();
  bool sending = false;

  Future<void> _send() async {
    final text = msg.text.trim();
    if (text.isEmpty) return;
    setState(() => sending = true);
    final res = await runAction(context, () => context.api.post('chat', {'ticket_id': widget.id, 'message': text}), showSuccess: false);
    if (!mounted) return;
    setState(() => sending = false);
    if (res != null) {
      msg.clear();
      _body.currentState?.reload();
    }
  }

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Scaffold(
      body: SafeArea(
        child: Column(children: [
          const PageHeader('گفتگو با پشتیبانی'),
          Expanded(
            child: AsyncBody(
              key: _body,
              load: () => context.api.get('tickets/${widget.id}'),
              builder: (context, d, reload) {
                final t = asMap(d['ticket']);
                final messages = asList(d['messages']);
                return ListView(padding: const EdgeInsets.fromLTRB(18, 8, 18, 16), children: [
                  AppCard(
                    padding: const EdgeInsets.all(14),
                    child: Row(children: [
                      IconTile(_catIcon(t['category']?.toString()).$1, tone: _catIcon(t['category']?.toString()).$2),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          Text('${t['subject']}', style: const TextStyle(fontWeight: FontWeight.w800)),
                          Text(fa('${t['number']} · ${t['created']}'), style: TextStyle(fontSize: 12, color: c.muted)),
                        ]),
                      ),
                      Pill('${t['status_label']}', tone: _statusTone(t['status']?.toString())),
                    ]),
                  ),
                  const SizedBox(height: 16),
                  for (final m in messages)
                    Align(
                      alignment: m['staff'] == true ? AlignmentDirectional.centerEnd : AlignmentDirectional.centerStart,
                      child: Container(
                        constraints: BoxConstraints(maxWidth: MediaQuery.of(context).size.width * .78),
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
                        decoration: BoxDecoration(
                          color: m['staff'] == true ? c.surface : c.accent,
                          border: m['staff'] == true ? Border.all(color: c.border) : null,
                          borderRadius: BorderRadius.circular(16),
                        ),
                        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                          if (m['staff'] == true) Text('پشتیبانی SafirCharge', style: TextStyle(fontSize: 11, color: c.accent, fontWeight: FontWeight.w700)),
                          Text('${m['message']}', style: TextStyle(color: m['staff'] == true ? c.text : Colors.white, height: 1.7, fontSize: 13.5)),
                          const SizedBox(height: 4),
                          Text(fa(m['time']), style: TextStyle(fontSize: 10.5, color: m['staff'] == true ? c.muted : Colors.white70)),
                        ]),
                      ),
                    ),
                ]);
              },
            ),
          ),
          Container(
            padding: const EdgeInsets.fromLTRB(14, 10, 14, 12),
            decoration: BoxDecoration(color: c.surface, border: Border(top: BorderSide(color: c.border))),
            child: Row(children: [
              Expanded(child: TextField(controller: msg, minLines: 1, maxLines: 4, decoration: const InputDecoration(hintText: 'پیام خود را بنویسید…'))),
              const SizedBox(width: 10),
              SizedBox(
                width: 52,
                height: 52,
                child: FilledButton(
                  style: FilledButton.styleFrom(padding: EdgeInsets.zero, shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14))),
                  onPressed: sending ? null : _send,
                  child: sending ? const SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white)) : const Icon(Icons.send_rounded, size: 20),
                ),
              ),
            ]),
          ),
        ]),
      ),
    );
  }
}
