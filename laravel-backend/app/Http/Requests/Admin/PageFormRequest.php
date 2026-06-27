<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PageFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route is already gated by `permission:settings.manage`.
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $page = $this->route('page');
        $pageId = $page instanceof Page ? $page->id : null;

        return [
            'title' => ['required', 'string', 'max:160'],
            // Optional — generated from the title when left blank. Lowercase
            // url-safe slug, unique across pages (ignoring the one being edited).
            'slug' => [
                'nullable',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('pages', 'slug')->ignore($pageId),
            ],
            // Raw HTML from the rich editor. Sanitised with HTMLPurifier in the
            // controller before storage, so this only bounds the size here.
            'body' => ['nullable', 'string', 'max:200000'],
            'is_published' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'slug.regex' => 'Slug may use lowercase letters, numbers and hyphens only.',
        ];
    }
}
