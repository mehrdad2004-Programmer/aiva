<?php

namespace Modules\Auth\Models\V1;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Modules\Auth\Database\Factories\V1/UsersModelFactory;

class UsersModel extends Model
{
    use HasFactory;

    protected $table = "users";
    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        "fullname",
        "chat_id",
        "deleted_at"
    ];

    // protected static function newFactory(): V1/UsersModelFactory
    // {
    //     // return V1/UsersModelFactory::new();
    // }
}
