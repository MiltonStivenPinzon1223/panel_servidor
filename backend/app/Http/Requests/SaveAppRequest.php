<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $rules = [
            'name' => [$required, 'string', 'max:160'],
            'slug' => [$required, 'string', 'alpha_dash', 'max:160'],
            'kind' => [$required, Rule::in(['nextcloud', 'laravel', 'other'])],
            'description' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'repository_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'local_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'public_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'exposure' => [$required, Rule::in(['private', 'ngrok'])],
            'environment' => [$required, Rule::in(['personal', 'test'])],
            'lifecycle' => [$required, Rule::in(['active', 'disabled', 'archived'])],
            'port' => ['sometimes', 'nullable', 'integer', 'between:1,65535'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'technologies' => ['sometimes', 'array', 'max:20'],
            'technologies.*.id' => ['required', 'integer', 'exists:technologies,id'],
            'technologies.*.version' => ['nullable', 'string', 'max:80'],
        ];

        if ($this->isMethod('POST')) {
            $rules['slug'][] = Rule::unique('apps', 'slug');
        } else {
            $routeApp = $this->route('app');
            $id = $routeApp
                ? \DB::table('apps')->where('slug', $routeApp)->orWhere('id', $routeApp)->value('id')
                : null;
            $unique = Rule::unique('apps', 'slug');
            if ($id) {
                $unique->ignore($id);
            }
            $rules['slug'][] = $unique;
        }

        return $rules;
    }

    /** @return array<int, \Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $url = $this->input('local_url');
            if (! is_string($url) || $url === '') {
                return;
            }

            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $hasCredentials = parse_url($url, PHP_URL_USER) !== null || parse_url($url, PHP_URL_PASS) !== null;
            $allowedHosts = array_map('strtolower', config('panel.allowed_check_hosts'));
            if ($hasCredentials || ! in_array($host, $allowedHosts, true)) {
                $validator->errors()->add('local_url', 'La URL local debe usar un host de la lista permitida y no incluir credenciales.');
            }
        }];
    }
}
