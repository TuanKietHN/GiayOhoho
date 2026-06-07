<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        return $this->ok($this->profilePayload($request->user()), 'Đã tải hồ sơ');
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'firstName' => 'nullable|string|max:255',
            'lastName' => 'nullable|string|max:255',
            'phoneNumber' => 'nullable|string|max:30',
            'birthOfDate' => 'nullable|date',
        ]);

        $user = $request->user();
        $user->forceFill([
            'first_name' => $data['firstName'] ?? $user->first_name,
            'last_name' => $data['lastName'] ?? $user->last_name,
            'phone_number' => $data['phoneNumber'] ?? $user->phone_number,
            'birth_of_date' => $data['birthOfDate'] ?? $user->birth_of_date,
        ])->save();

        DB::table('profiles')->updateOrInsert(
            ['account_id' => $user->id],
            [
                'dtype' => $this->profileType($user),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'phone_number' => $user->phone_number,
                'birth_of_date' => $user->birth_of_date,
            ]
        );

        return $this->ok($this->profilePayload($user->refresh()), 'Đã cập nhật hồ sơ');
    }

    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'file' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $path = $request->file('file')->store('avatars', 'public');
        $avatar = Storage::url($path);
        $user = $request->user();
        $user->forceFill(['avatar' => $avatar])->save();

        DB::table('profiles')->updateOrInsert(
            ['account_id' => $user->id],
            [
                'dtype' => $this->profileType($user),
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'avatar' => $avatar,
                'phone_number' => $user->phone_number,
                'birth_of_date' => $user->birth_of_date,
            ]
        );

        return $this->ok($this->profilePayload($user->refresh()), 'Đã cập nhật ảnh đại diện');
    }

    public function deleteAvatar(Request $request)
    {
        $user = $request->user();
        $user->forceFill(['avatar' => null])->save();
        DB::table('profiles')->where('account_id', $user->id)->update(['avatar' => null]);

        return $this->ok($this->profilePayload($user->refresh()), 'Đã xóa ảnh đại diện');
    }

    private function profilePayload($user): array
    {
        $profile = DB::table('profiles')->where('account_id', $user->id)->first();

        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'firstName' => $profile->first_name ?? $user->first_name,
            'lastName' => $profile->last_name ?? $user->last_name,
            'avatar' => $profile->avatar ?? $user->avatar,
            'phoneNumber' => $profile->phone_number ?? $user->phone_number,
            'birthOfDate' => $profile->birth_of_date ?? $user->birth_of_date,
            'roles' => $user->roles()->pluck('name')->map(fn($role) => strtoupper($role))->values(),
        ];
    }

    private function profileType($user): string
    {
        $roles = $user->roles()->pluck('name')->map(fn($role) => strtoupper($role))->all();
        if (in_array('ADMIN', $roles, true)) {
            return 'AdminProfile';
        }
        if (in_array('STAFF', $roles, true)) {
            return 'StaffProfile';
        }

        return 'CustomerProfile';
    }
}
