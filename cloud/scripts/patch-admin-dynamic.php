<?php

/**
 * تزریق دادهٔ دیتابیس به Bladeهای تبدیل‌شده از قالب — بدون تغییر ساختار UI
 * اجرا پس از convert-template.php: php scripts/patch-admin-dynamic.php
 */
$viewsDir = dirname(__DIR__).'/resources/views/admin';

function patch(string $path, callable $fn): void
{
    $full = $GLOBALS['viewsDir'].'/'.$path;
    if (! is_file($full)) {
        echo "SKIP {$path}\n";

        return;
    }
    $content = file_get_contents($full);
    $new = $fn($content);
    if ($new !== $content) {
        file_put_contents($full, $new);
        echo "PATCHED {$path}\n";
    } else {
        echo "UNCHANGED {$path}\n";
    }
}

function prependPhp(string $c): string
{
    if (str_contains($c, 'use App\Support\Persian')) {
        return $c;
    }

    return preg_replace(
        "/@section\('content'\)\n/",
        "@section('content')\n@php use App\\Support\\Persian; @endphp\n\n",
        $c,
        1
    ) ?? $c;
}

function injectDataScript(string $c, string $varName, string $bladeExpr): string
{
    $needle = "@push('scripts')\n<script>";
    $inject = "@push('scripts')\n<script>\nwindow.{$varName} = {!! json_encode({$bladeExpr}, JSON_UNESCAPED_UNICODE) !!};\n";
    if (str_contains($c, "window.{$varName}")) {
        return $c;
    }

    return str_replace($needle, $inject, $c);
}

// ── dashboard ──
patch('dashboard.blade.php', function (string $c) {
    $c = prependPhp($c);
    $c = preg_replace(
        '/<div class="kpi-val tnum">۱٬۲۴۸ <small>\/ ۱٬۲۶۶<\/small><\/div>/',
        '<div class="kpi-val tnum">{{ Persian::digits($kpis[\'chargers_online\']) }} <small>/ {{ Persian::digits($kpis[\'chargers_total\']) }}</small></div>',
        $c
    );
    $c = preg_replace(
        '/<div class="kpi-val tnum">۳۸٬۴۲۰ <small>kWh<\/small><\/div>/',
        '<div class="kpi-val tnum">{{ Persian::digits(number_format($kpis[\'today_energy_kwh\'], 0)) }} <small>kWh</small></div>',
        $c
    );
    $c = preg_replace(
        '/<div class="kpi-val tnum">۲۸۴٫۷ <small>میلیون تومان<\/small><\/div>/',
        '<div class="kpi-val tnum">{{ Persian::digits($kpis[\'today_revenue_million\']) }} <small>میلیون تومان</small></div>',
        $c
    );
    $c = preg_replace(
        '/<div class="kpi-val tnum">۲۴۷<\/div>/',
        '<div class="kpi-val tnum">{{ Persian::digits($kpis[\'active_sessions\']) }}</div>',
        $c
    );
    $c = preg_replace(
        '/<b class="tnum" style="margin-inline-start:auto">۶۲۴<\/b>/',
        '<b class="tnum" style="margin-inline-start:auto">{{ Persian::digits($breakdown[\'charging\']) }}</b>',
        $c, 1
    );
    $c = preg_replace(
        '/<b class="tnum" style="margin-inline-start:auto">۴۹۸<\/b>/',
        '<b class="tnum" style="margin-inline-start:auto">{{ Persian::digits($breakdown[\'available\']) }}</b>',
        $c, 1
    );
    $c = preg_replace(
        '/<b class="tnum" style="margin-inline-start:auto">۱۲۶<\/b>/',
        '<b class="tnum" style="margin-inline-start:auto">{{ Persian::digits($breakdown[\'reserved\']) }}</b>',
        $c, 1
    );
    $c = preg_replace(
        '/<b class="tnum" style="margin-inline-start:auto">۱۸<\/b>/',
        '<b class="tnum" style="margin-inline-start:auto">{{ Persian::digits($breakdown[\'faulted\']) }}</b>',
        $c, 1
    );
    $c = preg_replace(
        '/<div class="ch-sub">۲۴۷ جلسه فعال در سراسر شبکه<\/div>/',
        '<div class="ch-sub">{{ Persian::digits($kpis[\'active_sessions\']) }} جلسه فعال در سراسر شبکه</div>',
        $c
    );

    // جدول جلسات زنده
    if (! str_contains($c, '@forelse($activeSessions')) {
        $c = preg_replace(
            '/<tbody>[\s\S]*?<\/tbody>\s*<\/table>\s*<\/div>\s*<\/div>\s*@endsection/s',
            '<tbody>
            @forelse($activeSessions as $s)
            @php
                $prog = min(99, max(5, (int)($s->soc_end ?? $s->soc_start ?? 50)));
                $mins = (int) floor(($s->duration_seconds ?? 0) / 60);
            @endphp
            <tr>
              <td><div class="cell-user"><span class="uavatar" style="background:var(--accent)">{{ mb_substr($s->user?->name ?? \'م\', 0, 2) }}</span><div><div class="cell-strong">{{ $s->user?->name ?? \'مهمان\' }}</div><div class="cell-muted mono">{{ $s->session_code ?? \'—\' }}</div></div></div></td>
              <td><div class="cell-strong">{{ $s->station?->name }}</div><div class="cell-muted mono">{{ $s->charger?->charge_point_id }}</div></td>
              <td><span class="tag">{{ $s->charger?->connector_type ?? \'DC\' }}</span></td>
              <td style="min-width:130px"><div class="bar"><i style="width:{{ $prog }}%"></i></div><div class="cell-muted tnum" style="margin-top:4px">{{ Persian::digits($prog) }}٪</div></td>
              <td class="tnum cell-strong">{{ Persian::kwh($s->energy_kwh) }} kWh</td>
              <td class="tnum">{{ Persian::digits($s->charger?->max_power_kw ?? 0) }} kW</td>
              <td class="tnum">{{ Persian::digits($mins) }} دق</td>
              <td class="tnum cell-strong">{{ Persian::money($s->cost_amount / 10) }}</td>
              <td><span class="pill pill-green"><span class="pdot"></span>در حال شارژ</span></td>
            </tr>
            @empty
            <tr><td colspan="9" class="muted" style="text-align:center;padding:24px">جلسه فعالی نیست</td></tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
@endsection',
            $c,
            1
        );
    }

    // نقشه leaflet
    $c = preg_replace(
        '/<div class="map-panel" id="map" style="height:320px"><\/div>/',
        "@include('admin.partials.leaflet-map', ['markers' => \$mapMarkers, 'mapId' => 'dashMap', 'height' => '320px'])",
        $c
    );
    // حذف JS نقشه استاتیک
    $c = preg_replace(
        '/\/\/ نقشه[\s\S]*?map\.innerHTML = bg \+ pinHTML;\s*\}\)\(\);/m',
        '// نقشه از partial leaflet',
        $c
    );

    // نمودار از DB
    $c = injectDataScript($c, '__CHART', '$chart');
    $c = str_replace(
        'var energy=[14,12,10,9,11,16,22,30,28,26,31,38,42,40,36,33,29,34,41,45,39,32,24,18];
    var revenue=[10,8,7,6,9,13,18,25,24,22,27,33,37,35,31,28,25,30,36,40,34,28,21,15];',
        'var energy=(window.__CHART||[]).map(function(r){return r.energy;});
    var revenue=(window.__CHART||[]).map(function(r){return r.revenue;});',
        $c
    );

    $c = injectDataScript($c, '__BREAKDOWN', '$breakdown');
    $c = str_replace(
        "var data=[{v:624,c:'var(--green)'},{v:498,c:'var(--accent)'},{v:126,c:'var(--amber)'},{v:18,c:'var(--red)'}];",
        "var bd=window.__BREAKDOWN||{}; var data=[{v:bd.charging||0,c:'var(--green)'},{v:bd.available||0,c:'var(--accent)'},{v:bd.reserved||0,c:'var(--amber)'},{v:bd.faulted||0,c:'var(--red)'}];",
        $c
    );

    return $c;
});

// ── monitoring ──
patch('monitoring.blade.php', function (string $c) {
    $c = prependPhp($c);
    $replacements = [
        ['/<div class="ms-v tnum">۱٬۲۴۸<\/div>/', '<div class="ms-v tnum">{{ Persian::digits($online_connectors) }}</div>'],
        ['/<div class="ms-v tnum">۶۲۴<\/div>/', '<div class="ms-v tnum">{{ Persian::digits($charging_connectors) }}</div>'],
        ['/<div class="ms-v tnum">۲۸٫۴ <small>MW<\/small><\/div>/', '<div class="ms-v tnum">{{ Persian::digits($load_mw) }} <small>MW</small></div>'],
        ['/<div class="ms-v tnum">۹۸٫۶٪<\/div>/', '<div class="ms-v tnum">{{ Persian::digits($sync_health) }}٪</div>'],
        ['/<div class="ms-v tnum">۱۸<\/div>/', '<div class="ms-v tnum">{{ Persian::digits($active_faults) }}</div>'],
    ];
    foreach ($replacements as [$pat, $rep]) {
        $c = preg_replace($pat, $rep, $c, 1);
    }
    $c = injectDataScript($c, '__MONITORING_STATIONS', 'app(App\\Services\\Admin\\AdminViewDataService::class)->monitoringStationsJs()');
    $c = str_replace(
        'var STATIONS=[',
        'var STATIONS=window.__MONITORING_STATIONS||[',
        $c
    );
    $c = str_replace("'<a href=\"remote.html\"", "'<a href=\"'+(st.remoteUrl||'/admin/remote')+'\"", $c);

    return $c;
});

// ── sessions ──
patch('sessions/index.blade.php', function (string $c) {
    $c = prependPhp($c);
    $c = preg_replace('/<span class="tcount">۲۴۷<\/span>/', '<span class="tcount">{{ Persian::digits($stats[\'active\']) }}</span>', $c);
    $c = preg_replace('/<div class="ms-v tnum">۲۴۷<\/div>/', '<div class="ms-v tnum">{{ Persian::digits($stats[\'active\']) }}</div>', $c, 1);
    $c = preg_replace('/<div class="ms-v tnum">۹٬۸۴۲<\/div>/', '<div class="ms-v tnum">{{ Persian::digits($stats[\'today\']) }}</div>', $c);
    $c = injectDataScript($c, '__SESSIONS', 'app(App\\Services\\Admin\\AdminViewDataService::class)->sessionsJs()');
    $c = str_replace("var names=['مهدی احمدی'", 'var DB=window.__SESSIONS||{}; var names=[\'مهدی احمدی\'', $c);
    $c = preg_replace(
        '/\/\/ زنده[\s\S]*?document\.getElementById\(\'liveBody\'\)\.innerHTML=live;/m',
        "// زنده از DB\n  if(DB.live&&DB.live.length){live=DB.live.map(function(r,i){var prog=r.prog,bar=prog>85?'bar-orange':(prog<20?'bar-accent':'');var stPill=prog>85?'<span class=\"pill pill-amber\">رو به اتمام</span>':(prog<15?'<span class=\"pill pill-accent\">شروع شد</span>':'<span class=\"pill pill-green\"><span class=\"pdot\"></span>در حال شارژ</span>');return '<tr><td class=\"mono\">'+r.id+'</td><td><div class=\"cell-user\"><span class=\"uavatar\" style=\"background:'+cols[i%cols.length]+'\">'+r.initials+'</span><span class=\"cell-strong\">'+r.user+'</span></div></td><td><div class=\"cell-strong\">'+r.station+'</div><div class=\"cell-muted mono\">'+r.charger+'</div></td><td><span class=\"tag\">'+r.connector+'</span></td><td style=\"min-width:120px\"><div class=\"bar '+bar+'\"><i style=\"width:'+prog+'%\"></i></div><div class=\"cell-muted tnum\" style=\"margin-top:4px\">'+fa(prog)+'٪</div></td><td class=\"tnum\">'+r.energy+' kWh</td><td class=\"tnum\">'+r.power+' kW</td><td class=\"tnum\">'+fa(r.duration)+' دق</td><td class=\"tnum cell-strong\">'+fa(r.cost)+'</td><td>'+stPill+'</td><td><button class=\"stop-btn\" title=\"توقف\">■</button></td></tr>';}).join('');}else{",
        $c,
        1
    );

    return $c;
});

// ── wallet ──
patch('wallet.blade.php', function (string $c) {
    $c = prependPhp($c);
    if (! str_contains($c, 'window.__WALLET')) {
        $c = injectDataScript($c, '__WALLET', '$walletJs');
    }
    $c = str_replace('var users=[', 'var DB=window.__WALLET||{}; var users=(DB.wallets&&DB.wallets.length)?DB.wallets:[/*', $c);

    return $c;
});

// ── anpr ──
patch('anpr.blade.php', function (string $c) {
    $c = prependPhp($c);
    if (! str_contains($c, 'window.__ANPR')) {
        $c = injectDataScript($c, '__ANPR', '$anprJs');
    }

    return $c;
});

// ── reservations ──
patch('reservations.blade.php', function (string $c) {
    $c = prependPhp($c);
    if (! str_contains($c, 'window.__RESERVATIONS')) {
        $c = injectDataScript($c, '__RESERVATIONS', '$reservationsJs');
    }

    return $c;
});

// ── users ──
patch('users/index.blade.php', function (string $c) {
    $c = prependPhp($c);
    if (! str_contains($c, 'window.__USERS')) {
        $c = injectDataScript($c, '__USERS', '$usersJs');
    }

    return $c;
});

// ── create forms: wrap submit in POST ──
$formPatches = [
    'users/create.blade.php' => ['route' => 'admin.users.store', 'btn' => 'ثبت کاربر'],
    'partners/create.blade.php' => ['route' => 'admin.partners.store', 'btn' => 'ثبت شریک'],
    'tariffs/create.blade.php' => ['route' => 'admin.tariffs.store', 'btn' => 'ثبت تعرفه'],
    'rfid/create.blade.php' => ['route' => 'admin.rfid.store', 'btn' => 'صدور کارت'],
    'settlement/create.blade.php' => ['route' => 'admin.settlement.store', 'btn' => 'ایجاد'],
    'notifications/create.blade.php' => ['route' => 'admin.notifications.store', 'btn' => 'ارسال'],
];

foreach ($formPatches as $file => $cfg) {
    patch($file, function (string $c) use ($cfg) {
        $c = prependPhp($c);
        if (str_contains($c, '@csrf')) {
            return $c;
        }
        $c = preg_replace(
            '/@section\(\'content\'\)\n/',
            "@section('content')\n<form method=\"POST\" action=\"{{ route('{$cfg['route']}') }}\">@csrf\n",
            $c,
            1
        );
        $c = preg_replace(
            '/@endsection\n/',
            "</form>\n@endsection\n",
            $c,
            1
        );

        return $c;
    });
}

// stations create - form to store
patch('stations/create.blade.php', function (string $c) {
    $c = prependPhp($c);
    if (! str_contains($c, 'route(\'admin.stations.store\')')) {
        $c = preg_replace(
            '/@section\(\'content\'\)\n/',
            "@section('content')\n<form method=\"POST\" action=\"{{ isset(\$station) ? route('admin.stations.update', \$station) : route('admin.stations.store') }}\">@csrf\n@if(isset(\$station))@method('PUT')@endif\n",
            $c,
            1
        );
        $c = preg_replace('/@endsection\n/', "</form>\n@endsection\n", $c, 1);
    }

    return $c;
});

// sync settings form
patch('sync/settings.blade.php', function (string $c) {
    if (str_contains($c, 'sync.settings.update')) {
        return $c;
    }
    $c = prependPhp($c);
    $c = preg_replace(
        '/<div class="card-pad">\s*<label class="toggle-row"/',
        '<div class="card-pad"><form method="POST" action="{{ route(\'admin.sync.settings.update\') }}">@csrf',
        $c,
        1
    );
    $c = preg_replace(
        '/<button class="btn btn-primary" style="height:48px[^<]*<\/button>/',
        '<button class="btn btn-primary" style="height:48px" type="submit"><span class="bic" data-ic="check"></span> ذخیره تنظیمات</button></form>',
        $c,
        1
    );

    return $c;
});

// settings save form
patch('settings.blade.php', function (string $c) {
    $c = prependPhp($c);
    if (! str_contains($c, 'settings.update')) {
        $c = preg_replace('/@section\(\'content\'\)\n/', "@section('content')\n<form method=\"POST\" action=\"{{ route('admin.settings.update') }}\">@csrf\n", $c, 1);
        $c = preg_replace('/@endsection\n/', "<button class=\"btn btn-primary mt-16\" type=\"submit\">ذخیره همه</button>\n</form>\n@endsection\n", $c, 1);
    }

    return $c;
});

echo "\nDone.\n";
