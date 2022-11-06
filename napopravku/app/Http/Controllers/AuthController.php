<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct() {
        $this->middleware('auth:api', ['except' => ['login', 'register']]);
    }

    /**
     * Функция для регистрации
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|between:2,100',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|confirmed|min:6',
        ]);
        if($validator->fails()){
            return response()->json($validator->errors()->toJson(), 400);
        }
        $user = User::create(array_merge(
            $validator->validated(),
            ['password' => bcrypt($request->password)]
        ));
        Storage::makeDirectory($user->id);
        return response()->json([
            'message' => 'User successfully registered',
            'user' => $user
        ], 201);
    }

    /**
     * Функция для логина(получения jwt токена)
     *
     * @param Request $request
     * @return JsonResponse
     * @throws ValidationException
     */
    function login(Request $request): JsonResponse
    {
        $request->input("login");
        $request->input("password");
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }
        if (! $token = auth()->attempt($validator->validated())) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        return $this->createNewToken($token);
    }

    /**
     * Функция для разлогина
     *
     * @return JsonResponse
     */
    function logout(): JsonResponse
    {
        auth()->logout();
        return response()->json(['message' => 'User successfully signed out']);
    }

    /**
     * Функция для обновления токена
     *
     * @return JsonResponse
     */
    public function refresh(): JsonResponse {
        return $this->createNewToken(auth()->refresh());
    }

    /**
     * Функция для создания токена
     *
     * @param $token
     * @return JsonResponse
     */
    protected function createNewToken($token): JsonResponse{
        return response()->json([
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => auth()->factory()->getTTL() * 60,
            'user' => auth()->user()
        ]);
    }

    /**
     * Функция для возврата данных о профиле
     *
     * @return JsonResponse
     */
    public function userProfile(): JsonResponse {
        return response()->json(auth()->user());
    }
}
