<?php

namespace Modules\Auth\Services\V1;

use Modules\Auth\Models\V1\UsersModel;

class AuthService
{
    static public function createUser(array $userInfo){
        try{

            $inserted = UsersModel::create($userInfo);

            if(!$inserted){
                return [
                    "msg" => "not inserted",
                    "statuscode" => 400
                ];
            }

            return [
                "msg" => "inserted",
                "statuscode" => 201
            ];

        }catch(\Exception $e){
            return [
                "msg" => $e->getMessage() . " at line " . $e->getLine(),
                "statuscode" => 500
            ];
        }
    }

    static public function updateUser(string $chat_id, array $user_update){
        try{

            $updated = UsersModel::where("chat_id", $chat_id)->update($user_update);

            if(!$updated){
                return [
                    "msg" => "not updated",
                    "statuscode" => 400
                ];
            }

            return [
                "msg" => "updated",
                "statuscode" => 200
            ];

        }catch(\Exception $e){
            return [
                "msg" => $e->getMessage() . " at line " . $e->getLine(),
                "statuscode" => 500
            ];
        }
    }

    static public function deleteUser(string $chat_id){
        try{
            $deleted = UsersModel::where("chat_id", $chat_id)->update(
                [
                    "deleted_at" => now(),
                ]
            );

            if(!$deleted){
                return [
                    "msg" => "not deleted",
                    "statuscode" => 400
                ];
            }

            return [
                "msg" => "deleted",
                "statuscode" => 200
            ];

        }catch(\Exception $e){
            return [
                "msg" => $e->getMessage() . " at line " . $e->getLine(),
                "statuscode" => 500
            ];
        }
    }
}
