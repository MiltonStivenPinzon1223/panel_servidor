<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAlertRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'threshold' => ['sometimes', 'nullable', 'numeric', 'between:-1000000,1000000'],
            'sustain_minutes' => ['sometimes', 'integer', 'between:0,1440'],
            'hysteresis' => ['sometimes', 'numeric', 'between:0,1000000'],
            'severity' => ['sometimes', Rule::in(['warning', 'critical'])],
            'renotify_minutes' => ['sometimes', 'integer', 'between:1,10080'],
            'is_enabled' => ['sometimes', 'boolean'],
        ];
    }
}
