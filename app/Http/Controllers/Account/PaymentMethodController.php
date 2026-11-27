<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Account\CardActionRequest;
use App\Http\Requests\Account\CardSetupRequest;
use App\Http\Requests\Account\StoreCardRequest;
use App\Http\Requests\Account\TestCardRequest;
use App\Http\Resources\Account\CardSetupResource;
use App\Http\Resources\Account\SavedCardResource;
use App\Models\CustomerPaymentMethod;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\SavedCards;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/**
 * Saved cards on Profile & security (PLAN 5.3). The provider keeps the cards (PCI SAQ-A); we keep the brand, last
 * 4 digits and expiry. "Add a card" saves one with a Stripe SetupIntent confirmed in the browser — the server asks
 * Stripe whether it succeeded, never the browser — or with the fake gateway's test card locally. Cards are also
 * saved by paying with "Save this card" at checkout. The guest's own cards only (CustomerPaymentMethodPolicy).
 */
class PaymentMethodController extends Controller
{
    private const BACK = 'account.profile';

    /** Start adding a card. */
    public function setup(CardSetupRequest $request, PaymentGateway $gateway, SavedCards $cards): CardSetupResource
    {
        if ($gateway->name() !== 'stripe') {
            return new CardSetupResource(['provider' => $gateway->name()]);
        }
        $setup = $gateway->createSetup($cards->customerId($request->user()));

        return new CardSetupResource(['provider' => 'stripe', 'client_secret' => $setup->clientSecret]);
    }

    /** The browser confirmed the setup with Stripe: keep the card Stripe says was saved. */
    public function store(StoreCardRequest $request, PaymentGateway $gateway, SavedCards $cards): SavedCardResource
    {
        abort_if($gateway->name() !== 'stripe', 404);
        $card = $gateway->retrieveSetup($request->setupId(), $cards->customerId($request->user()));
        if ($card === null) {
            throw ValidationException::withMessages(['card' => 'We could not save that card. Please try again.']);
        }
        $request->session()->flash('status', 'card-added');

        return new SavedCardResource($cards->remember($request->user(), $card));
    }

    /** Back from a bank check (Stripe's return URL). */
    public function added(StoreCardRequest $request, PaymentGateway $gateway, SavedCards $cards): RedirectResponse
    {
        abort_if($gateway->name() !== 'stripe', 404);
        $card = $gateway->retrieveSetup($request->setupId(), $cards->customerId($request->user()));
        if ($card === null) {
            return redirect()->to(route(self::BACK).'#cards')->with('status', 'card-failed');
        }
        $cards->remember($request->user(), $card);

        return redirect()->to(route(self::BACK).'#cards')->with('status', 'card-added');
    }

    /** The fake gateway's test card (local development and tests only: 404 with Stripe or in production). */
    public function testCard(TestCardRequest $request, PaymentGateway $gateway, SavedCards $cards): SavedCardResource
    {
        abort_if($gateway->name() !== 'fake' || app()->isProduction(), 404);
        $request->session()->flash('status', 'card-added');

        return new SavedCardResource($cards->remember($request->user(), $request->card('fake_pm_'.FakeGateway::id())));
    }

    public function makeDefault(CardActionRequest $request, CustomerPaymentMethod $card, SavedCards $cards): RedirectResponse
    {
        $cards->makeDefault($card);

        return redirect()->to(route(self::BACK).'#cards')->with('status', 'card-default');
    }

    public function destroy(CardActionRequest $request, CustomerPaymentMethod $card, SavedCards $cards): RedirectResponse
    {
        $cards->remove($card);

        return redirect()->to(route(self::BACK).'#cards')->with('status', 'card-removed');
    }
}
