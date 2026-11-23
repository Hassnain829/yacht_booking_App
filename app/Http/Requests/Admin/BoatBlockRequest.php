<?php

namespace App\Http\Requests\Admin;

use App\Enums\BlockType;
use App\Rules\PlainText;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Block a time on a boat (maintenance, blackout, weather). The admin types Chicago time; it is stored in UTC.
 * "Owner block" is for owners' own calendar (Phase 6).
 */
class BoatBlockRequest extends FormRequest
{
    protected $errorBag = 'block';

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('boat'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'starts_at' => ['required', 'date_format:Y-m-d\TH:i'],
            'ends_at' => ['required', 'date_format:Y-m-d\TH:i', 'after:starts_at'],
            'type' => ['required', Rule::enum(BlockType::class)->only([BlockType::Maintenance, BlockType::Blackout, BlockType::Weather])],
            'reason' => ['nullable', 'string', 'max:255', new PlainText],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['ends_at.after' => 'The block must end after it starts.'];
    }

    public function startsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->validated('starts_at'), config('app.display_timezone'));
    }

    public function endsAt(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d\TH:i', $this->validated('ends_at'), config('app.display_timezone'));
    }
}
