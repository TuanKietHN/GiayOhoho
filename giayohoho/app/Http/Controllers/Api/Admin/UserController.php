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
        $size = (int) $request->input('size', 20);
        $page = $request->has('page') ? ((int) $request->input('page')) + 1 : null;
        $users = User::with('roles')
            ->when($q, function ($b) use ($q) {
                $b->where(function ($s) use ($q) {
                    $s->where('email', 'like', "%{$q}%")
                      ->orWhere('username', 'like', "%{$q}%")
                      ->orWhere('phone_number', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->paginate($size, ['*'], 'page', $page);

        if ($request->is('api/admin/accounts')) {
            $items = $users->getCollection()->map(fn(User $user) => $this->accountPayload($user))->values();
            return response()->json([
                'content' => $items,
                'page' => max(0, $users->currentPage() - 1),
                'size' => $users->perPage(),
                'totalElements' => $users->total(),
                'totalPages' => $users->lastPage(),
                'last' => $users->currentPage() >= $users->lastPage(),
                'first' => $users->currentPage() === 1,
            ]);
        }

        return response()->json($users);
    }

    public function setRoles(Request $request, int $id)
    {
        $data = $request->validate([
            'roles' => 'array',
            'roles.*' => 'string',
        ]);
        $user = User::findOrFail($id);
        $roleIds = Role::whereIn('name', collect($data['roles'] ?? [])->map(fn ($role) => strtoupper($role))->all())->pluck('id');
        $user->roles()->sync($roleIds);
        return response()->json($user->load('roles'));
    }

    public function status(Request $request, int $id)
    {
        $data = $request->validate([
            'locked' => 'nullable|boolean',
            'status' => 'nullable|string|max:32',
        ]);

        $user = User::with('roles')->findOrFail($id);
        if (array_key_exists('locked', $data)) {
            $user->locked = (bool) $data['locked'];
            $user->status = $user->locked ? 'LOCKED' : 'ACTIVE';
        }
        if (! empty($data['status'])) {
            $user->status = strtoupper($data['status']);
            $user->locked = $user->status !== 'ACTIVE';
        }
        $user->save();

        return response()->json($this->accountPayload($user->refresh()->load('roles')));
    }

    private function accountPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'email' => $user->email,
            'displayName' => trim(($user->first_name ?? '').' '.($user->last_name ?? '')) ?: $user->name,
            'roles' => $user->roles->pluck('name')->map(fn($role) => strtoupper($role))->values(),
            'locked' => (bool) $user->locked || $user->status !== 'ACTIVE',
            'createdAt' => $user->created_at?->toIso8601String(),
        ];
    }
}
