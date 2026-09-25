<?php

namespace Modules\Auth\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Auth\Services\V1\AuthService;

class AuthController extends Controller
{
    public function createUser(Request $request){
        try{

            $result = AuthService::createUser($request->all());

            return response()->json(
                $result,
                $result['statuscode']
            );

        }catch(\Exception $e){
            return response()->json([
                "msg" => $e->getMessage() . " at line " . $e->getMessage(),
                "statuscode" => 500
            ], 500);
        }
    }

    public function updateUser(Request $request){
        try{

            $user_update = [
                "fullname" => $request->fullname,
            ];
            
            $result = AuthService::updateUser(
                $request->chat_id,
                $user_update
            );

            return response()->json(
                $result,
                $result['statuscode']
            );

        }catch(\Exception $e){
            return response()->json([
                "msg" => $e->getMessage() . " at line " . $e->getMessage(),
                "statuscode" => 500
            ], 500);
        }
    }

    public function deleteUser(Request $request){
        try{

            $result = AuthService::deleteUser($request->chat_id);
            
            return response()->json(
                $result,
                $result['statuscode']
            );

        }catch(\Exception $e){
            return response()->json([
                "msg" => $e->getMessage() . " at line " . $e->getMessage(),
                "statuscode" => 500
            ], 500);
        }
    }

}
