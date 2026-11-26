<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/**
 * After sign-up: back to the page that asked for an account (checkout, 4.5 — the guest books right away and
 * verifies the email later), otherwise straight to "Check your inbox" (the verification email was just sent).
 */
class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        /** @var Request $request */
        return $request->wantsJson()
            ? new JsonResponse(['message' => 'Account created. Please verify your email.'], 201)
            : redirect()->intended(route('verification.notice'));
    }
}
