<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuthTokenService
{
    public const PASSWORD_RESET = 'PASSWORD_RESET';
    public const EMAIL_VERIFICATION = 'EMAIL_VERIFICATION';

    public function issue(User $user, string $type, int $ttlSeconds): string
    {
        $plainToken = Str::random(64);

        DB::transaction(function () use ($user, $type, $ttlSeconds, $plainToken) {
            DB::table('auth_tokens')
                ->where('account_id', $user->id)
                ->where('type', $type)
                ->whereNull('consumed_at')
                ->update([
                    'consumed_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('auth_tokens')->insert([
                'token' => $this->hash($plainToken),
                'account_id' => $user->id,
                'type' => $type,
                'expires_at' => now()->addSeconds($ttlSeconds),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        return $plainToken;
    }

    public function consume(string $plainToken, string $type): User
    {
        return DB::transaction(function () use ($plainToken, $type) {
            $record = DB::table('auth_tokens')
                ->where('token', $this->hash($plainToken))
                ->where('type', $type)
                ->lockForUpdate()
                ->first();

            if (! $record || $record->consumed_at || now()->greaterThan($record->expires_at)) {
                throw new AuthenticationException('Token is invalid or expired.');
            }

            DB::table('auth_tokens')->where('id', $record->id)->update([
                'consumed_at' => now(),
                'updated_at' => now(),
            ]);

            return User::withTrashed()->with('roles')->findOrFail($record->account_id);
        });
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }
}
