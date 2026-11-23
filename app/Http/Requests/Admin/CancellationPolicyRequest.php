<?php

namespace App\Http\Requests\Admin;

use App\Rules\PlainText;
use Illuminate\Validation\Validator;

/**
 * Edit a cancellation policy (Admin → Cancellation policies, PLAN §7.4, 3.4.5): the sentences guests read and
 * the refund rules the refund engine uses — up to 6 rows of "at least N hours before the start → X% back".
 * Rules must include a 0-hour row, and the refund can't grow as the trip gets closer. Money rules, so saving
 * needs a reason and the password; bookings already made keep their own snapshot.
 */
class CancellationPolicyRequest extends SensitiveActionRequest
{
    use Concerns\PreparesSlug;

    protected $errorBag = 'default';

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('policy'));
    }

    protected function prepareForValidation(): void
    {
        $rows = collect((array) $this->input('rules', []))
            ->filter(fn ($row) => is_array($row) && (filled($row['hours'] ?? null) || filled($row['pct'] ?? null)))
            ->values()
            ->all();

        $this->merge(['points' => $this->lines('points_text'), 'rules' => $rows]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return parent::rules() + [
            'name' => ['required', 'string', 'max:80', new PlainText],
            'badge' => ['nullable', 'string', 'max:60', new PlainText],
            'points_text' => ['nullable', 'string', 'max:2000'],
            'points' => ['required', 'array', 'min:1', 'max:6'],
            'points.*' => ['string', 'max:255', new PlainText],
            'rules' => ['required', 'array', 'min:1', 'max:6'],
            'rules.*' => ['array:hours,pct'],
            'rules.*.hours' => ['required', 'integer', 'between:0,8760', 'distinct'],
            'rules.*.pct' => ['required', 'integer', 'between:0,100'],
            'is_active' => ['required', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $rules = $this->sortedRules();
            if (end($rules)['min_hours_before'] !== 0) {
                $validator->errors()->add('rules', 'Add a row for 0 hours (cancellations right before the trip).');
            }
            for ($i = 1; $i < count($rules); $i++) {
                if ($rules[$i]['refund_pct'] > $rules[$i - 1]['refund_pct']) {
                    $validator->errors()->add('rules', 'The refund can\'t be bigger closer to the trip than further away.');
                    break;
                }
            }
        }];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return parent::messages() + [
            'points.required' => 'Write at least one sentence guests will read.',
            'rules.required' => 'Add at least one refund rule.',
            'rules.*.hours.distinct' => 'Each number of hours can be used once.',
        ];
    }

    /**
     * @return list<array{min_hours_before: int, refund_pct: int}> most hours first
     */
    public function sortedRules(): array
    {
        return collect((array) $this->input('rules', []))
            ->map(fn (array $row) => ['min_hours_before' => (int) $row['hours'], 'refund_pct' => (int) $row['pct']])
            ->sortByDesc('min_hours_before')
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function policyData(): array
    {
        return [
            'name' => $this->validated('name'),
            'badge' => $this->validated('badge'),
            'points' => $this->validated('points'),
            'rules' => $this->sortedRules(),
            'is_active' => $this->boolean('is_active'),
        ];
    }
}
