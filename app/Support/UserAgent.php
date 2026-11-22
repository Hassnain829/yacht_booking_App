<?php

namespace App\Support;

/**
 * A short, human label for a browser's User-Agent ("Chrome on Windows"), for the Devices list and the
 * security emails. Deliberately simple (no package): it only has to be recognisable to the account owner.
 */
final class UserAgent
{
    /** Checked in order: Edge and Opera also say "Chrome", Chrome also says "Safari". */
    private const BROWSERS = [
        'Edg/' => 'Edge',
        'OPR/' => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'CriOS/' => 'Chrome',
        'FxiOS/' => 'Firefox',
        'Chrome/' => 'Chrome',
        'Firefox/' => 'Firefox',
        'Safari/' => 'Safari',
    ];

    private const PLATFORMS = [
        'iPhone' => 'iPhone',
        'iPad' => 'iPad',
        'Android' => 'Android',
        'Windows' => 'Windows',
        'CrOS' => 'ChromeOS',
        'Macintosh' => 'Mac',
        'Linux' => 'Linux',
    ];

    public static function describe(?string $userAgent): string
    {
        $browser = self::firstMatch(self::BROWSERS, (string) $userAgent);
        $platform = self::firstMatch(self::PLATFORMS, (string) $userAgent);

        return match (true) {
            $browser !== null && $platform !== null => "{$browser} on {$platform}",
            $browser !== null => $browser,
            $platform !== null => $platform,
            default => 'Unknown device',
        };
    }

    /** Lucide icon name: a phone or tablet, otherwise a computer. */
    public static function icon(?string $userAgent): string
    {
        return preg_match('/iPhone|iPad|Android|Mobile/', (string) $userAgent) === 1 ? 'smartphone' : 'monitor';
    }

    /** @param array<string, string> $needles */
    private static function firstMatch(array $needles, string $userAgent): ?string
    {
        foreach ($needles as $needle => $label) {
            if (str_contains($userAgent, $needle)) {
                return $label;
            }
        }

        return null;
    }
}
