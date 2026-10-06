<?php

namespace Modules\Task\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReminderModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'reminder';

    protected $fillable = [
        'chat_id',
        'task_id',
        'remind_at',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'task_id' => 'integer',
            'remind_at' => 'datetime',
            'sent_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(TasksModel::class, 'task_id', 'id');
    }
}
