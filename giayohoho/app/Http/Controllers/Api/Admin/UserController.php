<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->string('q');
        $users = User::with('roles')
            ->when($q, function ($b) use ($q) {
                $b->where(function ($s) use ($q) {
                    $s->where('email', 'like', "%{$q}%")
                      ->orWhere('username', 'like', "%{$q}%")
                      ->orWhere('phone_number', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20);
        return response()->json($users);
    }

    public function setRoles(Request $request, int $id)
    {
        $data = $request->validate([
            'roles' => 'array',
            'roles.*' => 'string',
        ]);
        $user = User::findOrFail($id);
        $roleIds = Role::whereIn('name', $data['roles'] ?? [])->pluck('id');
        $user->roles()->sync($roleIds);
        return response()->json($user->load('roles'));
    }
}

