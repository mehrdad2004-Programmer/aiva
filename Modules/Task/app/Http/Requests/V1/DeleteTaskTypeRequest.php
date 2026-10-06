<?php

namespace Modules\Task\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class DeleteTaskTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [];
    }
}
