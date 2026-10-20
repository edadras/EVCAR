<?php

/**
 * تبدیل فایل‌های HTML قالب به Blade پنل ادمین
 * اجرا: php scripts/convert-template.php
 */
$templateDir = dirname(__DIR__).'/../Template';
$viewsDir = dirname(__DIR__).'/resources/views/admin';

if (! is_dir($templateDir)) {
    fwrite(STDERR, "Template dir not found: {$templateDir}\n");
    exit(1);
}

$linkMap = [
    'dashboard.html' => '/admin',
    'monitoring.html' => '/admin/monitoring',
    'stations.html' => '/admin/stations',
    'station-new.html' => '/admin/stations/create',
    'sessions.html' => '/admin/sessions',
    'reservations.html' => '/admin/reservations',
    'anpr.html' => '/admin/anpr',
    'wallet.html' => '/admin/wallet',
    'finance.html' => '/admin/finance',
    'reports.html' => '/admin/reports',
    'tariffs.html' => '/admin/tariffs',
    'tariff-new.html' => '/admin/tariffs/create',
    'rfid-new.html' => '/admin/rfid/create',
    'users.html' => '/admin/users',
    'user-new.html' => '/admin/users/create',
    'partners.html' => '/admin/partners',
    'partner-new.html' => '/admin/partners/create',
    'support.html' => '/admin/support',
    'inventory.html' => '/admin/inventory',
    'repairs.html' => '/admin/repairs',
    'sync.html' => '/admin/sync',
    'sync-settings.html' => '/admin/sync/settings',
    'notifications.html' => '/admin/notifications',
    'notification-new.html' => '/admin/notifications/create',
    'campaigns.html' => '/admin/campaigns',
    'team.html' => '/admin/team',
    'settings.html' => '/admin/settings',
    'settlement.html' => '/admin/settlement',
    'settlement-new.html' => '/admin/settlement/create',
    'remote.html' => '/admin/remote',
    'login.html' => '/admin/login',
    'partner/login.html' => '#',
];

$outputMap = [
    'dashboard.html' => 'dashboard.blade.php',
    'monitoring.html' => 'monitoring.blade.php',
    'stations.html' => 'stations/index.blade.php',
    'station-new.html' => 'stations/create.blade.php',
    'sessions.html' => 'sessions/index.blade.php',
    'reservations.html' => 'reservations.blade.php',
    'anpr.html' => 'anpr.blade.php',
    'wallet.html' => 'wallet.blade.php',
    'finance.html' => 'finance.blade.php',
    'reports.html' => 'reports.blade.php',
    'tariffs.html' => 'tariffs/index.blade.php',
    'tariff-new.html' => 'tariffs/create.blade.php',
    'rfid-new.html' => 'rfid/create.blade.php',
    'users.html' => 'users/index.blade.php',
    'user-new.html' => 'users/create.blade.php',
    'partners.html' => 'partners/index.blade.php',
    'partner-new.html' => 'partners/create.blade.php',
    'support.html' => 'support.blade.php',
    'inventory.html' => 'inventory.blade.php',
    'repairs.html' => 'repairs.blade.php',
    'sync.html' => 'sync/index.blade.php',
    'sync-settings.html' => 'sync/settings.blade.php',
    'notifications.html' => 'notifications/index.blade.php',
    'notification-new.html' => 'notifications/create.blade.php',
    'campaigns.html' => 'campaigns.blade.php',
    'team.html' => 'team.blade.php',
    'settings.html' => 'settings.blade.php',
    'settlement.html' => 'settlement/show.blade.php',
    'settlement-new.html' => 'settlement/create.blade.php',
    'remote.html' => 'remote.blade.php',
];

function extractBodyMeta(string $html): array
{
    if (! preg_match('/<body([^>]*)>/i', $html, $m)) {
        return ['page' => '', 'title' => '', 'crumb' => ''];
    }
    $attrs = $m[1];
    $get = fn (string $name) => preg_match('/data-'.$name.'="([^"]*)"/', $attrs, $x) ? $x[1] : '';

    return [
        'page' => $get('page'),
        'title' => $get('title'),
        'crumb' => $get('crumb'),
    ];
}

/**
 * @return array{content: string, tail: string}
 */
function extractMainParts(string $html): array
{
    $marker = '<div class="content">';
    $start = stripos($html, $marker);
    if ($start === false) {
        throw new RuntimeException('content div not found');
    }
    $innerStart = $start + strlen($marker);
    $depth = 1;
    $i = $innerStart;
    $len = strlen($html);
    $contentEnd = null;

    while ($i < $len && $depth > 0) {
        if (preg_match('/<\/?div\b/i', $html, $m, PREG_OFFSET_CAPTURE, $i)) {
            $tag = $m[0][0];
            $i = $m[0][1];
            if (str_starts_with(strtolower($tag), '</div')) {
                $depth--;
                if ($depth === 0) {
                    $contentEnd = $i;
                    break;
                }
                $i += strlen($tag);
            } else {
                $depth++;
                $i += strlen($tag);
            }
        } else {
            break;
        }
    }

    if ($contentEnd === null) {
        throw new RuntimeException('could not close content div');
    }

    $content = trim(substr($html, $innerStart, $contentEnd - $innerStart));
    $tail = trim(substr($html, $contentEnd + strlen('</div>')));
    $tail = preg_replace('/^<\/div>\s*/i', '', $tail);
    $tail = preg_replace('/<script[^>]*src=["\']assets\/app\.js["\'][^>]*><\/script>\s*/i', '', $tail);
    $tail = preg_replace('/<\/body>\s*<\/html>\s*$/i', '', $tail);

    return ['content' => $content, 'tail' => trim($tail)];
}

function replaceLinks(string $html, array $linkMap): string
{
    foreach ($linkMap as $file => $route) {
        $html = str_replace('href="'.$file.'"', 'href="'.$route.'"', $html);
        $html = str_replace("href='".$file."'", "href='".$route."'", $html);
        $html = str_replace('location.href=&quot;'.$file.'&quot;', 'location.href=&quot;'.$route.'&quot;', $html);
        $html = str_replace("location.href='".$file."'", "location.href='".$route."'", $html);
    }

    return $html;
}

function bladeEscape(string $s): string
{
    // @verbatim برای اسکریپت‌های JS که @ دارند
    if (str_contains($s, '@') && str_contains($s, '<script')) {
        return "@verbatim\n{$s}\n@endverbatim";
    }

    return $s;
}

$converted = 0;
foreach ($outputMap as $file => $outPath) {
    $src = $templateDir.'/'.$file;
    if (! is_file($src)) {
        echo "SKIP (missing): {$file}\n";

        continue;
    }

    $html = file_get_contents($src);
    $meta = extractBodyMeta($html);
    ['content' => $content, 'tail' => $tail] = extractMainParts($html);

    $content = replaceLinks($content, $linkMap);
    $tail = replaceLinks($tail, $linkMap);

    $blade = "@extends('admin.layouts.app', ['page' => '{$meta['page']}', 'title' => '{$meta['title']}', 'crumb' => '{$meta['crumb']}'])\n\n";
    $blade .= "@section('content')\n{$content}\n@endsection\n";

    if ($tail !== '') {
        $blade .= "\n@push('styles')\n";
        if (preg_match('/<style[\s\S]*?<\/style>/i', $tail, $styleMatch)) {
            $blade .= $styleMatch[0]."\n";
            $tail = trim(str_replace($styleMatch[0], '', $tail));
        }
        $blade .= "@endpush\n";

        if ($tail !== '') {
            $blade .= "\n@push('scripts')\n".bladeEscape($tail)."\n@endpush\n";
        }
    }

    $dest = $viewsDir.'/'.$outPath;
    $dir = dirname($dest);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dest, $blade);
    echo "OK: {$file} -> {$outPath}\n";
    $converted++;
}

echo "\nConverted {$converted} files.\n";
echo "Run: php scripts/patch-admin-dynamic.php\n";
