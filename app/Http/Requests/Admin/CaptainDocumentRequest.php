<?php

namespace App\Http\Requests\Admin;

use App\Rules\SafeUpload;
use Illuminate\Foundation\Http\FormRequest;

/** Upload a captain's license document (PDF or image) to the private disk (PLAN §9, 3.4.6). */
class CaptainDocumentRequest extends FormRequest
{
    protected $errorBag = 'document';

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('captain'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['document' => ['required', SafeUpload::document()]];
    }
}
