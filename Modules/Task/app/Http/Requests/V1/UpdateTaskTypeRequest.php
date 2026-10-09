<?php

namespace Modules\Task\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string'],
        ];
    }
}
