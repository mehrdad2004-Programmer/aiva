<?php

namespace Modules\Task\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskTypesModel extends Model
{
    use HasFactory;

    protected $table = 'task_types';

    protected $fillable = [
        'title',
        'chat_id',
    ];
}
