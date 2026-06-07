<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleOAuthService
{
    public function authenticate(string $idToken, Request $request): User
    {
        $clientId = config('services.google.client_id');
        if (! $clientId) {
            throw ValidationException::withMessages([
                'idToken' => ['Google OAuth client id is not configured.'],
            ]);
        }

        $payload = $this->verifyIdToken($idToken, (string) $clientId);
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $googleId = (string) ($payload['sub'] ?? '');

        if ($email === '' || $googleId === '') {
            throw ValidationException::withMessages([
                'idToken' => ['Google token payload is missing email or subject.'],
            ]);
        }

        if (! $this->emailVerified($payload['email_verified'] ?? false)) {
            throw ValidationException::withMessages([
                'idToken' => ['Google account email is not verified.'],
            ]);
        }

        return DB::transaction(function () use ($payload, $email, $googleId, $request) {
            $user = User::where('google_id', $googleId)->orWhere('email', $email)->lockForUpdate()->first();
            $firstName = trim((string) ($payload['given_name'] ?? $payload['name'] ?? Str::before($email, '@')));
            $lastName = trim((string) ($payload['family_name'] ?? ''));

            if (! $user) {
                $user = User::create([
                    'name' => trim($firstName.' '.$lastName),
                    'avatar' => $payload['picture'] ?? null,
                    'first_name' => $firstName,
                    'last_name' => $lastName ?: null,
                    'username' => $this->uniqueUsername(Str::before($email, '@')),
                    'email' => $email,
                    'password' => Hash::make(Str::random(48)),
                    'google_id' => $googleId,
                    'status' => 'ACTIVE',
                    'locked' => false,
                    'email_verified' => true,
                    'email_verified_at' => now(),
                ]);
            } else {
                $user->forceFill([
                    'google_id' => $user->google_id ?: $googleId,
                    'avatar' => $user->avatar ?: ($payload['picture'] ?? null),
                    'email_verified' => true,
                    'email_verified_at' => $user->email_verified_at ?: now(),
                ])->save();
            }

            if ($user->locked || $user->status !== 'ACTIVE') {
                throw ValidationException::withMessages([
                    'idToken' => ['Tai khoan dang bi khoa hoac khong con hoat dong.'],
                ]);
            }

            $roleId = Role::where('name', 'CUSTOMER')->value('id');
            if ($roleId) {
                $user->roles()->syncWithoutDetaching([$roleId]);
            }

            DB::table('profiles')->updateOrInsert(
                ['account_id' => $user->id],
                [
                    'dtype' => 'CustomerProfile',
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'avatar' => $user->avatar,
                    'phone_number' => $user->phone_number,
                    'birth_of_date' => $user->birth_of_date,
                    'created_at' => now(),
                    'deleted_at' => null,
                ]
            );

            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
                'login_count' => ((int) $user->login_count) + 1,
            ])->save();

            return $user->load('roles');
        });
    }

    private function verifyIdToken(string $idToken, string $clientId): array
    {
        $response = Http::timeout(10)->get((string) config('services.google.tokeninfo_url'), [
            'id_token' => $idToken,
        ]);

        if (! $response->ok()) {
            throw ValidationException::withMessages([
                'idToken' => ['Google ID token verification failed.'],
            ]);
        }

        $payload = $response->json();
        if (($payload['aud'] ?? null) !== $clientId) {
            throw ValidationException::withMessages([
                'idToken' => ['Google ID token audience does not match this app.'],
            ]);
        }

        return $payload;
    }

    private function emailVerified(mixed $value): bool
    {
        return $value === true || $value === 'true' || $value === 1 || $value === '1';
    }

    private function uniqueUsername(string $base): string
    {
        $base = Str::of($base)->slug('_')->limit(40, '')->toString() ?: 'google_user';
        $candidate = $base;
        $suffix = 1;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.'_'.$suffix++;
        }

        return $candidate;
    }
}
