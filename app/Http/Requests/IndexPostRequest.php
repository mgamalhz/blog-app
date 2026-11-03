<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => [
                'nullable',
                'integer',
                'exists:categories,id',
            ],
            'author_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],
            'sort' => [
                'nullable',
                Rule::in(['created_at', 'title']),
            ],
            'direction' => [
                'nullable',
                'in:asc,desc',
            ],
        ];
    }
}
