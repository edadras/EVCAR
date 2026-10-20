@php use App\Support\Persian; @endphp
{{-- پنل پلاک‌خوان — PLPR Lite روی Edge --}}
<div class="card mt-16" id="stationAnprPanel">
    <div class="card-head">
        <div>
            <h3>پلاک‌خوان (ANPR)</h3>
            <div class="ch-sub">PLPR Lite · شارژ خودکار با تشخیص پلاک ایرانی</div>
        </div>
        <div class="head-actions">
            @if(!empty($anprPanel['enabled']))
                <span class="pill pill-green"><span class="pdot"></span>فعال · {{ Persian::digits($anprPanel['kpis']['camera_count'] ?? 0) }} دوربین</span>
            @else
                <span class="pill pill-amber">غیرفعال</span>
            @endif
            @if(!empty($anprPanel['anpr_page_url']))
                <a href="{{ $anprPanel['anpr_page_url'] }}" class="btn btn-sm">گزارش کامل ANPR</a>
            @endif
            @if(!empty($anprPanel['edit_url']))
                <a href="{{ $anprPanel['edit_url'] }}#anprConfig" class="btn btn-sm btn-primary">تنظیمات</a>
            @endif
        </div>
    </div>
    <div class="card-pad">
        @if(empty($anprPanel['enabled']))
            <div class="anpr-note">
                <span data-ic="shield"></span>
                <span>پلاک‌خوان برای این ایستگاه فعال نیست. از <a href="{{ $anprPanel['edit_url'] ?? '#' }}">ویرایش ایستگاه</a> می‌توانید دوربین PLPR Lite را پیکربندی کنید.</span>
            </div>
        @else
            <div class="kpi-grid" style="margin-bottom:16px;grid-template-columns:repeat(4,1fr)">
                <div class="mini-stat"><div class="ms-ic ic-blue" data-ic="camera"></div><div><div class="ms-v tnum">{{ Persian::digits($anprPanel['kpis']['camera_count'] ?? 0) }}</div><div class="ms-l">دوربین</div></div></div>
                <div class="mini-stat"><div class="ms-ic ic-orange" data-ic="bolt"></div><div><div class="ms-v tnum">{{ Persian::digits($anprPanel['kpis']['today'] ?? 0) }}</div><div class="ms-l">شارژ خودکار امروز</div></div></div>
                <div class="mini-stat"><div class="ms-ic ic-violet" data-ic="check"></div><div><div class="ms-v tnum">{{ Persian::digits($anprPanel['kpis']['accuracy_pct'] ?? 0) }}٪</div><div class="ms-l">دقت ۷ روز</div></div></div>
                <div class="mini-stat"><div class="ms-ic ic-green" data-ic="car"></div><div><div class="ms-v tnum">{{ Persian::digits(number_format($anprPanel['kpis']['registered_plates'] ?? 0)) }}</div><div class="ms-l">پلاک ثبت‌شده</div></div></div>
            </div>

            <div class="grid col-2-1">
                <div class="cam">
                    <div class="cam-scene" style="height:200px">
                        <div class="cam-grid"></div>
                        <div class="cam-car" id="stCamCar">
                            <svg viewBox="0 0 200 90" width="160"><rect x="14" y="30" width="172" height="40" rx="12" fill="#1f3146"/><circle cx="52" cy="72" r="13" fill="#0d1b28" stroke="#3a536c" stroke-width="3"/><circle cx="156" cy="72" r="13" fill="#0d1b28" stroke="#3a536c" stroke-width="3"/></svg>
                        </div>
                        <div class="cam-box" id="stCamBox" style="opacity:{{ !empty($anprPanel['demo']) ? '1' : '0' }}">
                            <span class="cb-corner tl"></span><span class="cb-corner tr"></span><span class="cb-corner bl"></span><span class="cb-corner br"></span>
                        </div>
                        <div class="cam-tag" id="stCamTag">{{ $anprPanel['demo']['d2'] ?? 'در حال پایش' }} {{ $anprPanel['demo']['let'] ?? '' }}</div>
                        <div class="cam-meta"><span id="stCamSource">{{ ($anprPanel['demo']['camera'] ?? 'CAM-01').' · '.($anprPanel['edge_code'] ?? '') }}</span><span class="tnum" id="stCamTime">{{ $anprPanel['demo']['time'] ?? '—' }}</span></div>
                    </div>
                </div>
                <div>
                    <div class="detect-result" id="stDetectResult">
                        <div class="dr-plate" id="stDrPlate"></div>
                        <div style="flex:1">
                            <div class="fw7" id="stDrName">{{ $anprPanel['demo']['name'] ?? 'در انتظار شناسایی…' }}</div>
                            <div class="fs12 muted" id="stDrStatus">{{ $anprPanel['demo']['status'] ?? 'دوربین در حال پایش ورودی' }}</div>
                        </div>
                        @if(!empty($anprPanel['demo']['pill']))
                            <span class="pill {{ $anprPanel['demo']['pill'][1] ?? '' }}">{{ $anprPanel['demo']['pill'][0] ?? '—' }}</span>
                        @endif
                    </div>
                    <div class="anpr-note mt-12"><span data-ic="shield"></span><span>موتور {{ $anprPanel['engine'] ?? 'PLPR Lite' }} روی Edge · سینک رویداد به ابر</span></div>
                </div>
            </div>

            <div class="section-title mt-16">دوربین‌های ایستگاه</div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>شناسه</th><th>نام</th><th>RTSP / منبع</th><th>وضعیت</th><th>آخرین تشخیص</th></tr></thead>
                    <tbody>
                        @foreach($anprPanel['cameras'] ?? [] as $cam)
                        <tr>
                            <td class="tnum cell-strong">{{ $cam['id'] }}</td>
                            <td>{{ $cam['name'] }}</td>
                            <td class="cell-muted tnum" dir="ltr">{{ $cam['rtsp_url'] ?: 'وب‌کم / آپلود' }}</td>
                            <td>
                                @php $st = $cam['status'] ?? 'offline'; @endphp
                                <span class="pill {{ $st === 'online' ? 'pill-green' : ($st === 'idle' ? 'pill-accent' : 'pill-amber') }}">
                                    @if($st === 'online')<span class="pdot"></span>@endif
                                    {{ $st === 'online' ? 'آنلاین' : ($st === 'idle' ? 'بیکار' : ($st === 'disabled' ? 'غیرفعال' : 'آفلاین')) }}
                                </span>
                            </td>
                            <td class="cell-muted tnum">{{ $cam['last_detection'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(!empty($anprPanel['events']))
            <div class="section-title mt-16">آخرین رویدادهای تشخیص</div>
            <div class="table-wrap">
                <table class="tbl">
                    <thead><tr><th>زمان</th><th>پلاک</th><th>کاربر</th><th>دوربین</th><th>وضعیت</th></tr></thead>
                    <tbody id="stAnprEvents">
                        @foreach(array_slice($anprPanel['events'], 0, 5) as $ev)
                        <tr>
                            <td class="tnum cell-muted">{{ $ev['t'] }}</td>
                            <td><span class="plate-sm" data-plate='@json($ev['p'])'></span></td>
                            <td class="cell-strong">{{ $ev['u'] }}</td>
                            <td class="cell-muted">{{ explode(' · ', $ev['st'])[1] ?? '—' }}</td>
                            <td><span class="pill {{ $ev['s'][1] }}">{{ $ev['s'][0] }}</span></td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        @endif
    </div>
</div>

@once
@push('styles')
<style>
.anpr-note{display:flex;align-items:flex-start;gap:9px;background:var(--accent-softer);border-radius:11px;padding:12px;font-size:12px;color:var(--text-2);line-height:1.6}
.anpr-note svg{width:18px;height:18px;color:var(--accent);flex:none;margin-top:1px}
.cam{border-radius:14px;overflow:hidden;border:1px solid var(--border)}
.cam-scene{position:relative;background:linear-gradient(180deg,#16293c,#0d1b28);overflow:hidden}
.cam-grid{position:absolute;inset:0;background-image:linear-gradient(rgba(255,255,255,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.05) 1px,transparent 1px);background-size:28px 28px;opacity:.45}
.cam-car{position:absolute;bottom:36px;left:50%;transform:translateX(-50%)}
.cam-box{position:absolute;bottom:48px;left:50%;transform:translateX(-50%);width:96px;height:32px}
.cb-corner{position:absolute;width:12px;height:12px;border:2px solid #4ade80}
.cb-corner.tl{top:0;left:0;border-right:none;border-bottom:none}.cb-corner.tr{top:0;right:0;border-left:none;border-bottom:none}
.cb-corner.bl{bottom:0;left:0;border-right:none;border-top:none}.cb-corner.br{bottom:0;right:0;border-left:none;border-top:none}
.cam-tag{position:absolute;bottom:82px;left:50%;transform:translateX(-50%);background:#4ade80;color:#06250f;font-size:11px;font-weight:800;padding:3px 10px;border-radius:20px;white-space:nowrap}
.cam-meta{position:absolute;top:10px;left:12px;right:12px;display:flex;justify-content:space-between;color:#9fb4c8;font-size:11px;font-weight:600}
.detect-result{display:flex;align-items:center;gap:14px;padding:14px;border-radius:14px;background:var(--surface-2);border:1px solid var(--border-2)}
.mini-stat{background:var(--surface);border:1px solid var(--border);border-radius:var(--r-lg);padding:14px;display:flex;align-items:center;gap:12px}
.ms-ic{width:40px;height:40px;border-radius:12px;display:grid;place-items:center;flex:none}.ms-ic svg{width:20px;height:20px}
.ms-v{font-size:20px;font-weight:800}.ms-l{font-size:11px;color:var(--muted)}
</style>
@endpush
@endonce
