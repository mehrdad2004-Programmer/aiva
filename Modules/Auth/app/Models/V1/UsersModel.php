<?php

namespace Modules\Auth\Models\V1;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UsersModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'users';

    protected $primaryKey = 'chat_id';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'fullname',
        'chat_id',
    ];
}
