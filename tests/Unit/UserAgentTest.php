<?php

namespace Tests\Unit;

use App\Support\UserAgent;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Phase 2.4 — the short device labels on the Devices list and in the security emails. */
class UserAgentTest extends TestCase
{
    /** @return array<string, array{0: ?string, 1: string, 2: string}> */
    public static function agents(): array
    {
        return [
            'chrome windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36', 'Chrome on Windows', 'monitor'],
            'edge windows' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36 Edg/131.0.0.0', 'Edge on Windows', 'monitor'],
            'safari mac' => ['Mozilla/5.0 (Macintosh; Intel Mac OS X 14_6) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Safari/605.1.15', 'Safari on Mac', 'monitor'],
            'firefox linux' => ['Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0', 'Firefox on Linux', 'monitor'],
            'safari iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Safari on iPhone', 'smartphone'],
            'chrome iphone' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0.6778.73 Mobile/15E148 Safari/604.1', 'Chrome on iPhone', 'smartphone'],
            'samsung android' => ['Mozilla/5.0 (Linux; Android 14; SM-S921B) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/26.0 Chrome/122.0.0.0 Mobile Safari/537.36', 'Samsung Internet on Android', 'smartphone'],
            'unknown' => ['curl/8.5.0', 'Unknown device', 'monitor'],
            'missing' => [null, 'Unknown device', 'monitor'],
        ];
    }

    #[DataProvider('agents')]
    public function test_it_names_the_browser_and_platform(?string $userAgent, string $label, string $icon): void
    {
        $this->assertSame($label, UserAgent::describe($userAgent));
        $this->assertSame($icon, UserAgent::icon($userAgent));
    }
}
