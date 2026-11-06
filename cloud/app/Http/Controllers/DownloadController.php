<?php

namespace App\Http\Controllers;

use App\Support\Persian;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * صفحهٔ دانلود اپ موبایل و تحویل فایل APK اندروید.
 * فایل‌های APK در git نیستند و جداگانه در public/downloads بارگذاری می‌شوند.
 */
class DownloadController extends Controller
{
    public const VERSION = '1.1.0';

    /** معماری => [فایل، برچسب] */
    public const BUILDS = [
        'arm64' => ['quad-ev.apk', 'گوشی‌های جدید (arm64)'],
        'armv7' => ['quad-ev-armv7.apk', 'گوشی‌های قدیمی‌تر (armv7)'],
    ];

    public function page(Request $request)
    {
        $builds = [];
        foreach (self::BUILDS as $arch => [$file, $label]) {
            $path = public_path('downloads/'.$file);
            if (is_file($path)) {
                $builds[$arch] = [
                    'label' => $label,
                    'url' => route('download.android', $arch === 'arm64' ? [] : ['arch' => $arch]),
                    'size' => Persian::digits(number_format(filesize($path) / 1048576, 1)),
                    'date' => Persian::dateTime(\Carbon\Carbon::createFromTimestamp(filemtime($path)), 'yyyy/MM/dd'),
                ];
            }
        }

        $ua = strtolower((string) $request->userAgent());

        return view('download', [
            'version' => self::VERSION,
            'builds' => $builds,
            'platform' => str_contains($ua, 'android') ? 'android' : (preg_match('/iphone|ipad|ipod/', $ua) ? 'ios' : 'desktop'),
        ]);
    }

    public function android(Request $request): BinaryFileResponse
    {
        $build = self::BUILDS[$request->query('arch', 'arm64')] ?? self::BUILDS['arm64'];
        $path = public_path('downloads/'.$build[0]);
        abort_unless(is_file($path), 404);

        $name = 'QUAD-EV-'.self::VERSION.($build[0] === 'quad-ev.apk' ? '' : '-armv7').'.apk';

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
