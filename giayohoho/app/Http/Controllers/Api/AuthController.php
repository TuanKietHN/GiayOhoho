<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    // POST /api/auth/register
    public function register(Request $request)
    {
        $data = $request->validate([
            'first_name' => ['required','string','max:255','regex:/^[\p{L}\s\'.-]+$/u'],
            'last_name'  => ['nullable','string','max:255','regex:/^[\p{L}\s\'.-]+$/u'],
            'username'   => ['required','string','max:255','regex:/^[A-Za-z0-9_.-]+$/','unique:users,username'],
            'email'      => 'required|email|max:255|unique:users,email',
            'password'   => 'required|string|min:6|confirmed',
            'phone_number' => ['nullable','string','max:20','regex:/^[0-9+()\s-]{6,20}$/'],
            'birth_of_date' => 'nullable|date',
        ]);

        $user = User::create([
            'name'          => trim(strip_tags(($data['first_name'].' '.($data['last_name'] ?? '')))),
            'first_name'    => trim(strip_tags($data['first_name'])),
            'last_name'     => isset($data['last_name']) ? trim(strip_tags($data['last_name'])) : null,
            'username'      => trim($data['username']),
            'email'         => strtolower(trim($data['email'])),
            'password'      => Hash::make($data['password']),
            'phone_number'  => $data['phone_number'] ?? null,
            'birth_of_date' => $data['birth_of_date'] ?? null,
        ]);

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    // POST /api/auth/login
    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thông tin đăng nhập không chính xác.'],
            ]);
        }

        // Xóa token cũ nếu muốn "1 thiết bị 1 token"
        $user->tokens()->delete();

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'user'  => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ]);
    }

    // POST /api/auth/logout
    public function logout(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Xóa token hiện tại
        $user->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Đăng xuất thành công.',
        ]);
    }

    // GET /api/auth/me
    public function me(Request $request)
    {
        return response()->json($request->user()->load('roles'));
    }
}
