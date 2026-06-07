<?php

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

class RefreshTokenService
{
    public function issue(User $user, Request $request, ?string $deviceFingerprint = null): array
    {
        return DB::transaction(fn () => $this->createTokenRecord($user, $request, $deviceFingerprint));
    }

    public function rotate(string $plainToken, Request $request): array
    {
        return DB::transaction(function () use ($plainToken, $request) {
            $hash = $this->hash($plainToken);
            $record = DB::table('refresh_tokens')->where('token', $hash)->lockForUpdate()->first();

            if (! $record) {
                throw new AuthenticationException('Refresh token is invalid.');
            }

            if ($record->revoked_at !== null) {
                $this->revokeAllForAccount((int) $record->account_id);
                throw new AuthenticationException('Refresh token was already used.');
            }

            if (Carbon::parse($record->expires_at)->isPast()) {
                DB::table('refresh_tokens')->where('id', $record->id)->update([
                    'revoked_at' => now(),
                    'updated_at' => now(),
                ]);
                throw new AuthenticationException('Refresh token has expired.');
            }

            $user = User::with('roles')->findOrFail($record->account_id);
            $newRefresh = $this->createTokenRecord($user, $request, $record->device_fingerprint);

            DB::table('refresh_tokens')->where('id', $record->id)->update([
                'revoked_at' => now(),
                'replaced_by_token' => $newRefresh['tokenHash'],
                'updated_at' => now(),
            ]);

            return [
                'user' => $user,
                'refresh' => $newRefresh,
            ];
        });
    }

    public function tokenFromRequest(Request $request): ?string
    {
        return $request->input('refreshToken')
            ?: $request->input('refresh_token')
            ?: $request->header('X-Refresh-Token')
            ?: $request->cookie($this->refreshCookieName());
    }

    public function validateRefreshCsrf(Request $request): void
    {
        $cookieToken = $request->cookie($this->csrfCookieName());
        if (! $cookieToken) {
            return;
        }

        $headerToken = $request->header('X-CSRF-Refresh-Token') ?: $request->input('csrfToken');
        if (! hash_equals((string) $cookieToken, (string) $headerToken)) {
            throw new AuthenticationException('Refresh CSRF token is invalid.');
        }
    }

    public function revokePlainToken(?string $plainToken): void
    {
        if (! $plainToken) {
            return;
        }

        DB::table('refresh_tokens')->where('token', $this->hash($plainToken))->update([
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function revokeByFingerprint(User $user, string $deviceFingerprint): int
    {
        return DB::table('refresh_tokens')
            ->where('account_id', $user->id)
            ->whereNull('revoked_at')
            ->where(function ($query) use ($deviceFingerprint) {
                $query->where('device_fingerprint', $deviceFingerprint);
                if (ctype_digit($deviceFingerprint)) {
                    $query->orWhere('id', (int) $deviceFingerprint);
                }
            })
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function revokeOtherSessions(User $user, ?string $currentPlainToken = null): int
    {
        $currentHash = $currentPlainToken ? $this->hash($currentPlainToken) : null;

        return DB::table('refresh_tokens')
            ->where('account_id', $user->id)
            ->whereNull('revoked_at')
            ->when($currentHash, fn ($query) => $query->where('token', '<>', $currentHash))
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function sessions(User $user): array
    {
        return DB::table('refresh_tokens')
            ->where('account_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(fn ($session) => [
                'id' => (string) $session->id,
                'deviceFingerprint' => $session->device_fingerprint,
                'userAgent' => $session->user_agent,
                'ipAddress' => $session->ip_address,
                'expiresAt' => $session->expires_at,
                'revokedAt' => $session->revoked_at,
                'createdAt' => $session->created_at,
            ])
            ->values()
            ->all();
    }

    public function cookiesFor(array $refresh): array
    {
        $minutes = (int) ceil($refresh['expiresInSeconds'] / 60);

        return [
            $this->makeCookie($this->refreshCookieName(), $refresh['refreshToken'], $minutes, true),
            $this->makeCookie($this->csrfCookieName(), $refresh['csrfToken'], $minutes, false),
        ];
    }

    public function forgetCookies(): array
    {
        return [
            cookie()->forget($this->refreshCookieName(), $this->cookiePath(), $this->cookieDomain()),
            cookie()->forget($this->csrfCookieName(), $this->cookiePath(), $this->cookieDomain()),
        ];
    }

    private function createTokenRecord(User $user, Request $request, ?string $deviceFingerprint = null): array
    {
        $plainToken = Str::random(96);
        $hash = $this->hash($plainToken);
        $csrfToken = Str::random(64);
        $expiresInSeconds = (int) config('services.auth.refresh.lifetime_seconds', 60 * 60 * 24 * 30);
        $expiresAt = now()->addSeconds($expiresInSeconds);
        $fingerprint = $deviceFingerprint ?: $this->deviceFingerprint($request);

        DB::table('refresh_tokens')->insert([
            'token' => $hash,
            'account_id' => $user->id,
            'device_fingerprint' => $fingerprint,
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'ip_address' => $request->ip(),
            'expires_at' => $expiresAt,
            'expiration_in_seconds' => $expiresInSeconds,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'refreshToken' => $plainToken,
            'tokenHash' => $hash,
            'csrfToken' => $csrfToken,
            'expiresAt' => $expiresAt,
            'expiresInSeconds' => $expiresInSeconds,
            'deviceFingerprint' => $fingerprint,
        ];
    }

    private function revokeAllForAccount(int $accountId): void
    {
        DB::table('refresh_tokens')->where('account_id', $accountId)->whereNull('revoked_at')->update([
            'revoked_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function deviceFingerprint(Request $request): string
    {
        return hash('sha256', implode('|', [
            $request->header('X-Device-Fingerprint', ''),
            $request->userAgent() ?: '',
            $request->ip() ?: '',
        ]));
    }

    private function makeCookie(string $name, string $value, int $minutes, bool $httpOnly): Cookie
    {
        return cookie(
            $name,
            $value,
            $minutes,
            $this->cookiePath(),
            $this->cookieDomain(),
            (bool) config('services.auth.refresh.cookie_secure', false),
            $httpOnly,
            false,
            (string) config('services.auth.refresh.cookie_same_site', 'lax')
        );
    }

    private function refreshCookieName(): string
    {
        return (string) config('services.auth.refresh.cookie_name', 'refresh_token');
    }

    private function csrfCookieName(): string
    {
        return (string) config('services.auth.refresh.csrf_cookie_name', 'csrf_refresh_token');
    }

    private function cookiePath(): string
    {
        return (string) config('services.auth.refresh.cookie_path', '/');
    }

    private function cookieDomain(): ?string
    {
        return config('services.auth.refresh.cookie_domain');
    }
}
