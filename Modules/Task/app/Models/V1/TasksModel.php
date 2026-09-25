<?php

namespace Modules\Task\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TasksModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tasks';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'title',
        'chat_id',
        'type',
        'comments',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'deleted_at' => 'datetime',
        ];
    }

    public function reminders(): HasMany
    {
        return $this->hasMany(ReminderModel::class, 'task_id', 'id');
    }
}
