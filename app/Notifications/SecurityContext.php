<?php

namespace App\Notifications;

use App\Support\UserAgent;
use Carbon\CarbonImmutable;

/**
 * Where and when a security event happened, for the security emails. Captured during the request,
 * because the emails are sent later from the queue.
 */
final readonly class SecurityContext
{
    public function __construct(
        public string $device,
        public ?string $ip,
        public CarbonImmutable $at,
    ) {}

    public static function fromRequest(): self
    {
        $console = app()->runningInConsole() && ! app()->runningUnitTests();
        $request = request();

        return new self(
            $console ? 'Our support team' : UserAgent::describe($request->userAgent()),
            $console ? null : $request->ip(),
            CarbonImmutable::now(),
        );
    }

    /** "Chrome on Windows · IP 203.0.113.9 · Nov 22, 2026 at 1:05 AM CST" */
    public function summary(): string
    {
        $when = $this->at->setTimezone(config('app.display_timezone'))->format('M j, Y \a\t g:i A T');

        return implode(' · ', array_filter([$this->device, $this->ip ? 'IP '.$this->ip : null, $when]));
    }
}
