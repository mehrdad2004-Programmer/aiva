<?php

namespace Modules\Task\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaskStatusModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'task_status';

    protected $fillable = [
        'title',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }
}
