import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:share_plus/share_plus.dart';

import '../core/api.dart';
import '../core/theme.dart';

/// کارت سفید با گوشهٔ گرد و سایهٔ ملایم (کلاس card وب‌اپ).
class AppCard extends StatelessWidget {
  const AppCard({super.key, required this.child, this.padding = const EdgeInsets.all(16), this.onTap, this.color, this.border, this.margin});

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry? margin;
  final VoidCallback? onTap;
  final Color? color;
  final Color? border;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Container(
      margin: margin,
      decoration: BoxDecoration(
        color: color ?? c.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border ?? c.border),
        boxShadow: c.dark ? null : const [BoxShadow(color: Color(0x0F0F1E2E), blurRadius: 18, offset: Offset(0, 6))],
      ),
      child: Material(
        type: MaterialType.transparency,
        child: InkWell(
          borderRadius: BorderRadius.circular(18),
          onTap: onTap,
          child: Padding(padding: padding, child: child),
        ),
      ),
    );
  }
}

/// کاشی آیکون رنگی (l-ic / ic-blue ...).
class IconTile extends StatelessWidget {
  const IconTile(this.icon, {super.key, this.tone = 'blue', this.size = 44, this.iconSize});

  final IconData icon;
  final String tone;
  final double size;
  final double? iconSize;

  @override
  Widget build(BuildContext context) {
    final (bg, fg) = context.c.tone(tone);
    return Container(
      width: size,
      height: size,
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(size * .3)),
      child: Icon(icon, color: fg, size: iconSize ?? size * .5),
    );
  }
}

class Pill extends StatelessWidget {
  const Pill(this.text, {super.key, this.tone = 'green', this.dot = false});

  final String text;
  final String tone;
  final bool dot;

  /// تبدیل کلاس pill وب (pill-green / pill-amber / ...) به رنگ.
  factory Pill.web(String text, String cls, {bool dot = true}) =>
      Pill(text, tone: cls.replaceFirst('pill-', ''), dot: dot);

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final (bg, fg) = tone == 'muted' ? (c.surface3, c.muted) : c.tone(tone);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(color: bg, borderRadius: BorderRadius.circular(20)),
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        if (dot) ...[
          Container(width: 6, height: 6, decoration: BoxDecoration(color: fg, shape: BoxShape.circle)),
          const SizedBox(width: 6),
        ],
        Text(text, style: TextStyle(color: fg, fontSize: 11.5, fontWeight: FontWeight.w700)),
      ]),
    );
  }
}

class SecTitle extends StatelessWidget {
  const SecTitle(this.title, {super.key, this.action, this.onAction, this.top = 22});

  final String title;
  final String? action;
  final VoidCallback? onAction;
  final double top;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Padding(
      padding: EdgeInsets.only(top: top, bottom: 12),
      child: Row(children: [
        Expanded(child: Text(title, style: const TextStyle(fontSize: 16, fontWeight: FontWeight.w800))),
        if (action != null)
          GestureDetector(
            onTap: onAction,
            child: Text(action!, style: TextStyle(color: c.accent, fontSize: 12.5, fontWeight: FontWeight.w700)),
          ),
      ]),
    );
  }
}

enum BtnKind { primary, orange, red, ghost, soft }

class AppButton extends StatelessWidget {
  const AppButton(this.label, {super.key, this.icon, this.onPressed, this.kind = BtnKind.primary, this.loading = false, this.height = 52, this.expand = true});

  final String label;
  final IconData? icon;
  final VoidCallback? onPressed;
  final BtnKind kind;
  final bool loading;
  final double height;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    final (bg, fg) = switch (kind) {
      BtnKind.primary => (c.accent, Colors.white),
      BtnKind.orange => (Palette.orange, Colors.white),
      BtnKind.red => (Palette.red, Colors.white),
      BtnKind.soft => (c.accentSoft, c.accentStrong),
      BtnKind.ghost => (c.surface, c.text),
    };
    final disabled = onPressed == null || loading;
    final child = Row(mainAxisSize: MainAxisSize.min, mainAxisAlignment: MainAxisAlignment.center, children: [
      if (loading)
        SizedBox(width: 18, height: 18, child: CircularProgressIndicator(strokeWidth: 2, color: fg))
      else if (icon != null)
        Icon(icon, size: 19, color: fg),
      if (loading || icon != null) const SizedBox(width: 8),
      Flexible(child: Text(label, overflow: TextOverflow.ellipsis, style: TextStyle(color: fg, fontSize: 14.5, fontWeight: FontWeight.w800))),
    ]);
    return Opacity(
      opacity: disabled && !loading ? .5 : 1,
      child: SizedBox(
        height: height,
        width: expand ? double.infinity : null,
        child: Material(
          color: bg,
          borderRadius: BorderRadius.circular(16),
          elevation: 0,
          child: InkWell(
            borderRadius: BorderRadius.circular(16),
            onTap: disabled ? null : onPressed,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(16),
                border: kind == BtnKind.ghost ? Border.all(color: c.border) : null,
                boxShadow: kind == BtnKind.orange || kind == BtnKind.red
                    ? [BoxShadow(color: bg.withValues(alpha: .35), blurRadius: 16, offset: const Offset(0, 6))]
                    : null,
              ),
              alignment: Alignment.center,
              child: child,
            ),
          ),
        ),
      ),
    );
  }
}

/// ردیف فهرست (lrow): آیکون، عنوان، توضیح و انتهای ردیف.
class ListRow extends StatelessWidget {
  const ListRow({super.key, required this.icon, required this.title, this.subtitle, this.trailing, this.onTap, this.tone = 'blue', this.chevron = false});

  final IconData icon;
  final String title;
  final String? subtitle;
  final Widget? trailing;
  final VoidCallback? onTap;
  final String tone;
  final bool chevron;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(12),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 10),
        child: Row(children: [
          IconTile(icon, tone: tone, size: 42),
          const SizedBox(width: 12),
          Expanded(
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
              Text(title, style: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700)),
              if (subtitle != null && subtitle!.isNotEmpty)
                Padding(
                  padding: const EdgeInsets.only(top: 3),
                  child: Text(subtitle!, style: TextStyle(fontSize: 12, color: c.muted, height: 1.5)),
                ),
            ]),
          ),
          ?trailing,
          if (chevron) Directionality(textDirection: TextDirection.ltr, child: Icon(Icons.chevron_left_rounded, color: c.muted)),
        ]),
      ),
    );
  }
}

/// تب‌های قرصی (seg) مانند فیلتر «همه / شارژ سریع / عمومی».
class Segmented<T> extends StatelessWidget {
  const Segmented({super.key, required this.options, required this.value, required this.onChanged});

  final List<(T, String)> options;
  final T value;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(color: c.surface3, borderRadius: BorderRadius.circular(14)),
      child: Row(children: [
        for (final (v, label) in options)
          Expanded(
            child: GestureDetector(
              onTap: () => onChanged(v),
              child: AnimatedContainer(
                duration: const Duration(milliseconds: 180),
                height: 38,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: v == value ? c.surface : Colors.transparent,
                  borderRadius: BorderRadius.circular(11),
                  boxShadow: v == value && !c.dark ? const [BoxShadow(color: Color(0x140F1E2E), blurRadius: 6)] : null,
                ),
                child: Text(label,
                    style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: v == value ? c.text : c.text2)),
              ),
            ),
          ),
      ]),
    );
  }
}

/// نوار بالای صفحه (appbar وب‌اپ): دکمهٔ برگشت گرد + عنوان + اکشن‌ها.
class PageHeader extends StatelessWidget {
  const PageHeader(this.title, {super.key, this.subtitle, this.actions = const [], this.back = true});

  final String title;
  final String? subtitle;
  final List<Widget> actions;
  final bool back;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 8, 18, 6),
      child: Row(children: [
        if (back && Navigator.of(context).canPop()) ...[
          RoundIconButton(Icons.chevron_right_rounded, onTap: () => Navigator.of(context).maybePop(), size: 24),
          const SizedBox(width: 12),
        ],
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            if (subtitle != null) Text(subtitle!, style: TextStyle(fontSize: 12, color: c.muted)),
            Text(title, style: const TextStyle(fontSize: 21, fontWeight: FontWeight.w800)),
          ]),
        ),
        ...actions,
      ]),
    );
  }
}

class RoundIconButton extends StatelessWidget {
  const RoundIconButton(this.icon, {super.key, this.onTap, this.badge = false, this.size = 21, this.dark = false});

  final IconData icon;
  final VoidCallback? onTap;
  final bool badge;
  final double size;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 42,
        height: 42,
        decoration: BoxDecoration(
          color: dark ? Colors.white.withValues(alpha: .14) : c.surface,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: dark ? Colors.white.withValues(alpha: .18) : c.border),
        ),
        child: Stack(alignment: Alignment.center, children: [
          // آیکون‌ها با جهت ثابت رسم می‌شوند تا «برگشت» در RTL همیشه به راست اشاره کند.
          Directionality(textDirection: TextDirection.ltr, child: Icon(icon, size: size, color: dark ? Colors.white : c.text)),
          if (badge)
            Positioned(
              top: 9,
              right: 10,
              child: Container(width: 8, height: 8, decoration: BoxDecoration(color: Palette.orange, shape: BoxShape.circle, border: Border.all(color: c.surface, width: 1.5))),
            ),
        ]),
      ),
    );
  }
}

class EmptyState extends StatelessWidget {
  const EmptyState(this.text, {super.key, this.icon = Icons.inbox_outlined, this.action});

  final String text;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    final c = context.c;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 36, horizontal: 24),
      child: Column(children: [
        Icon(icon, size: 44, color: c.muted),
        const SizedBox(height: 12),
        Text(text, textAlign: TextAlign.center, style: TextStyle(color: c.text2, fontSize: 14, height: 1.7)),
        if (action != null) ...[const SizedBox(height: 16), action!],
      ]),
    );
  }
}

void toast(BuildContext context, String message, {bool error = false}) {
  final m = ScaffoldMessenger.maybeOf(context);
  m?.hideCurrentSnackBar();
  m?.showSnackBar(SnackBar(
    content: Row(children: [
      Icon(error ? Icons.error_outline : Icons.check_circle_outline, color: error ? const Color(0xFFFCA5A5) : const Color(0xFF86EFAC), size: 20),
      const SizedBox(width: 10),
      Expanded(child: Text(message)),
    ]),
  ));
}

/// اجرای یک اکشن API با نمایش خطا؛ نتیجه یا null در صورت خطا.
Future<Map<String, dynamic>?> runAction(BuildContext context, Future<Map<String, dynamic>> Function() action, {bool showSuccess = true}) async {
  try {
    final res = await action();
    if (context.mounted && showSuccess && res['message'] is String && (res['message'] as String).isNotEmpty) {
      toast(context, res['message'] as String);
    }
    return res;
  } on ApiException catch (e) {
    if (context.mounted) toast(context, e.message, error: true);
    return null;
  }
}

/// بدنهٔ صفحه با بارگذاری داده، نمایش خطا با دکمهٔ «تلاش دوباره» و کشیدن برای تازه‌سازی.
class AsyncBody extends StatefulWidget {
  const AsyncBody({super.key, required this.load, required this.builder, this.refreshable = true});

  final Future<Map<String, dynamic>> Function() load;
  final Widget Function(BuildContext context, Map<String, dynamic> data, Future<void> Function() reload) builder;
  final bool refreshable;

  @override
  State<AsyncBody> createState() => AsyncBodyState();
}

class AsyncBodyState extends State<AsyncBody> {
  Map<String, dynamic>? _data;
  ApiException? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    reload();
  }

  Future<void> reload() async {
    setState(() {
      _loading = _data == null;
      _error = null;
    });
    try {
      final d = await widget.load();
      if (mounted) setState(() => _data = d);
    } on ApiException catch (e) {
      if (mounted) setState(() => _error = e);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading && _data == null) {
      return const Center(child: Padding(padding: EdgeInsets.all(40), child: CircularProgressIndicator()));
    }
    if (_error != null && _data == null) {
      return ListView(children: [
        EmptyState(_error!.message, icon: Icons.wifi_off_rounded, action: AppButton('تلاش دوباره', icon: Icons.refresh, expand: false, onPressed: reload)),
      ]);
    }
    final child = widget.builder(context, _data!, reload);
    return widget.refreshable ? RefreshIndicator(onRefresh: reload, child: child) : child;
  }
}

/// فیلد متنی فرم با برچسب بالای آن (مطابق فرم‌های وب‌اپ).
class LabeledField extends StatelessWidget {
  const LabeledField({super.key, required this.label, required this.controller, this.hint, this.keyboard, this.obscure = false, this.maxLines = 1, this.error, this.textAlign = TextAlign.start, this.maxLength, this.ltr = false});

  final String label;
  final TextEditingController controller;
  final String? hint;
  final TextInputType? keyboard;
  final bool obscure;
  final int maxLines;
  final String? error;
  final TextAlign textAlign;
  final int? maxLength;
  final bool ltr;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
        Text(label, style: TextStyle(fontSize: 13, fontWeight: FontWeight.w700, color: context.c.text2)),
        const SizedBox(height: 7),
        TextField(
          controller: controller,
          keyboardType: keyboard,
          obscureText: obscure,
          maxLines: obscure ? 1 : maxLines,
          maxLength: maxLength,
          textAlign: textAlign,
          textDirection: ltr ? TextDirection.ltr : null,
          decoration: InputDecoration(hintText: hint, errorText: error, counterText: ''),
        ),
      ]),
    );
  }
}

/// آیکون جهت‌دار با جهت ثابت (مستقل از RTL)؛ مثلاً فلش «ادامه» در فهرست‌ها.
class LtrIcon extends StatelessWidget {
  const LtrIcon(this.icon, {super.key, this.size, this.color});

  final IconData icon;
  final double? size;
  final Color? color;

  @override
  Widget build(BuildContext context) =>
      Directionality(textDirection: TextDirection.ltr, child: Icon(icon, size: size, color: color));
}

/// اشتراک‌گذاری متن با برگهٔ اشتراک سیستم (رسید، آدرس ایستگاه و …).
Future<void> shareText(BuildContext context, String text) async {
  try {
    await SharePlus.instance.share(ShareParams(text: text));
  } catch (_) {
    await Clipboard.setData(ClipboardData(text: text));
    if (context.mounted) toast(context, 'متن کپی شد.');
  }
}
