<?php

namespace App\Http\Requests\Admin;

use App\Rules\SafeUpload;
use Illuminate\Foundation\Http\FormRequest;

/** Upload up to 10 boat photos at once (25 MB each, like the owner wizard — config/listing.php). */
class BoatPhotosRequest extends FormRequest
{
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
            'photos' => ['required', 'array', 'min:1', 'max:10'],
            'photos.*' => ['required', SafeUpload::image(maxKb: (int) config('listing.rules_engine.max_photo_mb') * 1024)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['photos.required' => 'Choose at least one photo.', 'photos.max' => 'Upload at most 10 photos at a time.'];
    }
}
