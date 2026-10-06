<?php

namespace Modules\Task\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string'],
            'chat_id' => ['required', 'string'],
        ];
    }
}
