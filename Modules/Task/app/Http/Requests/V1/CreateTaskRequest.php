<?php

namespace Modules\Task\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class CreateTaskRequest extends FormRequest
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
            'type' => ['required', 'string'],
            'comments' => ['required', 'string'],
            'status' => ['required', 'string'],
            'task_id' => ['required', 'integer'],
            'remind_at' => ['required', 'date'],
        ];
    }
}
