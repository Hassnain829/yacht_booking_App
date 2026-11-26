<?php

namespace App\Http\Requests\Checkout;

use App\Models\Addon;
use App\Models\Booking;
use App\Services\SettingsService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * The checkout page's live price (GET /checkout/{booking}/price, read-only): the add-ons and gratuity the guest is
 * trying out. Only the guest's own booking (else 404). Prices are QuoteService's.
 */
class CheckoutPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $booking = $this->route('booking');
        Gate::authorize('pay', $booking instanceof Booking ? $booking : abort(404));

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $options = array_map('intval', app(SettingsService::class)->all()['gratuity_options']);

        return [
            'addons' => ['nullable', 'array', 'max:10'],
            'addons.*' => ['string', 'max:80', 'distinct', Rule::exists('addons', 'slug')->where('is_active', true)->whereNull('deleted_at')],
            'gratuity' => ['required', 'integer', Rule::in($options)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'addons.*.exists' => 'One of the add-ons is no longer offered. Reload the page.',
            'gratuity.in' => 'Choose one of the gratuity options.',
        ];
    }

    /** @return list<Addon> in the order chosen */
    public function addons(): array
    {
        $slugs = array_values($this->validated('addons') ?? []);

        return Addon::query()->whereIn('slug', $slugs)->get()->sortBy(fn (Addon $addon) => array_search($addon->slug, $slugs, true))->values()->all();
    }

    public function gratuity(): int
    {
        return (int) $this->validated('gratuity');
    }
}
