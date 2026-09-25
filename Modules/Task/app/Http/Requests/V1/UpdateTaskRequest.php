<?php

namespace Modules\Task\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string'],
            'chat_id' => ['sometimes', 'string'],
            'type' => ['sometimes', 'string'],
            'comments' => ['sometimes', 'string'],
            'status' => ['sometimes', 'string'],
            'remind_at' => ['sometimes', 'date'],
        ];
    }
}
