<?php

namespace App\Http\Responses\Auth;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;

/** After sign-up: straight to "Check your inbox" (the verification email was just sent). */
class RegisterResponse implements RegisterResponseContract
{
    public function toResponse($request): JsonResponse|RedirectResponse
    {
        /** @var Request $request */
        return $request->wantsJson()
            ? new JsonResponse(['message' => 'Account created. Please verify your email.'], 201)
            : redirect()->route('verification.notice');
    }
}
