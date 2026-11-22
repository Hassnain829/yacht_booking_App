<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureTwoFactorEnabled;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Laravel\Fortify\Fortify;

/**
 * Two-factor login (PLAN §8.1): turn on (QR code + setup key + a code from the app), recovery codes,
 * turn off. Behind `password.confirm`. The buttons post to Fortify's /user/two-factor-* routes,
 * which come back here with a `status`. Optional for everyone, the admin included (the admin's choice,
 * user decision 2026-11-22; linked from Admin → Settings → Security). Owner withdrawals will need it (Phase 7).
 */
class TwoFactorController extends Controller
{
    /** Fortify's statuses → what we tell the user. Only these are shown. */
    private const MESSAGES = [
        Fortify::TWO_FACTOR_AUTHENTICATION_ENABLED => 'Scan the QR code with your app, then enter the 6-digit code to finish.',
        Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED => 'Two-factor login is on. Save your recovery codes somewhere safe.',
        Fortify::TWO_FACTOR_AUTHENTICATION_DISABLED => 'Two-factor login is off.',
        Fortify::RECOVERY_CODES_GENERATED => 'New recovery codes are ready. The old ones no longer work.',
    ];

    /** Screen pixels per QR module: whole pixels keep the code crisp at 100% zoom (no blurred module edges). */
    public const QR_MODULE_PX = 5;

    public function show(Request $request): Response
    {
        $user = $request->user();
        $state = match (true) {
            $user->hasEnabledTwoFactorAuthentication() => 'on',
            $user->two_factor_secret !== null => 'pending', // turned on, waiting for the first code
            default => 'off',
        };
        $status = $request->session()->get('status');
        $sentByArea = (bool) $request->session()->pull(EnsureTwoFactorEnabled::SESSION_KEY, false);

        $message = is_string($status) ? (self::MESSAGES[$status] ?? null) : null;
        if ($message === null && $sentByArea && $state !== 'on') {
            $message = EnsureTwoFactorEnabled::MESSAGE;
        }

        return response()
            ->view('account.two-factor', [
                'state' => $state,
                'isAdmin' => $user->isAdmin(),
                'message' => $message,
                'qrCode' => $state === 'pending' ? $this->qrCode($user->twoFactorQrCodeUrl()) : null,
                'setupKey' => $state === 'pending' ? trim(chunk_split(Fortify::currentEncrypter()->decrypt($user->two_factor_secret), 4, ' ')) : null,
                'recoveryCodes' => $state === 'on' ? $user->recoveryCodes() : [],
                'confirmedAt' => $user->two_factor_confirmed_at,
                'showCodes' => in_array($status, [Fortify::TWO_FACTOR_AUTHENTICATION_CONFIRMED, Fortify::RECOVERY_CODES_GENERATED], true),
            ])
            ->header('Cache-Control', 'no-store, private'); // the setup key and recovery codes never go into a cache
    }

    /**
     * The setup QR code as an image (data URI — no inline SVG markup in the page), with its display size.
     *
     * Not Fortify's twoFactorQrCodeSvg(): that one has no quiet zone (margin 0), a grey colour and 176 px for
     * ~45 modules (blurred edges), and the Chrome "Authenticator" extension could not read it from a 100%-zoom
     * screen ("Unrecognized QR code"). This one is black on white with the standard 4-module quiet zone inside
     * its frame, and every module is exactly QR_MODULE_PX screen pixels (265–285 px in total).
     *
     * @return array{src: string, size: int}
     */
    private function qrCode(string $otpauthUrl): array
    {
        $modules = Encoder::encode($otpauthUrl, ErrorCorrectionLevel::L(), Encoder::DEFAULT_BYTE_MODE_ENCODING)->getMatrix()->getWidth();
        $size = ($modules + 2 * 4) * self::QR_MODULE_PX;
        $svg = (new Writer(new ImageRenderer(new RendererStyle($size, 4), new SvgImageBackEnd)))->writeString($otpauthUrl); // error correction L, as above

        return ['src' => 'data:image/svg+xml;base64,'.base64_encode($svg), 'size' => $size];
    }
}
