<?php

namespace App\Http\Requests\Account;

use App\Models\CustomerPaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/** Make a saved card the default, or remove it (5.3): the guest's own card only — anyone else's is a 404. */
class CardActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $card = $this->route('card');
        Gate::authorize('manage', $card instanceof CustomerPaymentMethod ? $card : abort(404));

        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [];
    }
}
