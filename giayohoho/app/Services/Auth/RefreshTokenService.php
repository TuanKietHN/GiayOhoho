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
    public function issue(User $user, Request $request, ?string $deviceFingerprint = null, string $authProvider = 'PASSWORD'): array
    {
        return DB::transaction(fn () => $this->createTokenRecord($user, $request, $deviceFingerprint, $authProvider));
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
            $newRefresh = $this->createTokenRecord(
                $user,
                $request,
                $record->device_fingerprint,
                $record->auth_provider ?: 'PASSWORD',
                $record
            );

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

    public function revokeBySessionIdentifier(User $user, string $sessionIdentifier): int
    {
        return $this->revokeAccountSession($user->id, $sessionIdentifier);
    }

    public function revokeAccountSession(int $accountId, string $sessionIdentifier): int
    {
        $isUuid = $this->looksLikeUuid($sessionIdentifier);

        return DB::table('refresh_tokens')
            ->where('account_id', $accountId)
            ->whereNull('revoked_at')
            ->where(function ($query) use ($sessionIdentifier, $isUuid) {
                if ($isUuid) {
                    $query->where('session_id', $sessionIdentifier);
                } else {
                    $query->whereRaw('1 = 0');
                }
                $query->orWhere('device_fingerprint', $sessionIdentifier);
                if (ctype_digit($sessionIdentifier)) {
                    $query->orWhere('id', (int) $sessionIdentifier);
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

    public function revokeAllExceptCurrentAccessToken(User $user, ?int $currentAccessTokenId = null): void
    {
        $user->tokens()
            ->when($currentAccessTokenId, fn ($query) => $query->where('id', '<>', $currentAccessTokenId))
            ->delete();
    }

    public function revokeAll(User|int $user): int
    {
        $accountId = $user instanceof User ? $user->id : $user;

        return DB::table('refresh_tokens')
            ->where('account_id', $accountId)
            ->whereNull('revoked_at')
            ->update([
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function sessions(User $user, ?string $currentPlainToken = null): array
    {
        return $this->sessionRows($user->id, true, $currentPlainToken);
    }

    public function adminSessions(User|int $user): array
    {
        $accountId = $user instanceof User ? $user->id : $user;
        return $this->sessionRows($accountId, false);
    }

    private function sessionRows(int $accountId, bool $maskIp, ?string $currentPlainToken = null): array
    {
        $currentHash = $currentPlainToken ? $this->hash($currentPlainToken) : null;

        return DB::table('refresh_tokens')
            ->where('account_id', $accountId)
            ->whereNull('revoked_at')
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc(DB::raw('COALESCE(last_seen_at, created_at)'))
            ->limit(50)
            ->get()
            ->map(function ($session) use ($maskIp, $currentHash) {
                $display = $this->parseUserAgent((string) $session->user_agent);
                $sessionId = (string) ($session->session_id ?: $session->id);

                return [
                    'id' => (string) $session->id,
                    'sessionId' => $sessionId,
                    'deviceFingerprint' => $session->device_fingerprint,
                    'deviceName' => $display['deviceName'],
                    'deviceType' => $display['deviceType'],
                    'browser' => $display['browser'],
                    'operatingSystem' => $display['operatingSystem'],
                    'userAgent' => $session->user_agent,
                    'ipAddress' => $maskIp ? $this->maskIp((string) $session->ip_address) : $session->ip_address,
                    'authProvider' => $session->auth_provider ?: 'PASSWORD',
                    'createdAt' => $session->session_created_at ?: $session->created_at,
                    'lastSeenAt' => $session->last_seen_at ?: $session->created_at,
                    'expiresAt' => $session->expires_at,
                    'revokedAt' => $session->revoked_at,
                    'current' => $currentHash !== null && hash_equals($currentHash, (string) $session->token),
                ];
            })
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

    private function createTokenRecord(
        User $user,
        Request $request,
        ?string $deviceFingerprint = null,
        string $authProvider = 'PASSWORD',
        ?object $sourceSession = null
    ): array
    {
        $plainToken = Str::random(96);
        $hash = $this->hash($plainToken);
        $csrfToken = Str::random(64);
        $expiresInSeconds = (int) config('services.auth.refresh.lifetime_seconds', 60 * 60 * 24 * 30);
        $expiresAt = now()->addSeconds($expiresInSeconds);
        $fingerprint = $deviceFingerprint ?: $this->deviceFingerprint($request);
        $sessionId = $sourceSession?->session_id ?: (string) Str::uuid();
        $sessionCreatedAt = $sourceSession?->session_created_at ?: now();
        $deviceId = $sourceSession?->device_id
            ?: Str::limit((string) ($request->header('X-Device-Id') ?: $fingerprint), 128, '');
        $provider = $sourceSession?->auth_provider ?: strtoupper($authProvider);

        DB::table('refresh_tokens')->insert([
            'token' => $hash,
            'account_id' => $user->id,
            'device_fingerprint' => $fingerprint,
            'session_id' => $sessionId,
            'device_id' => $deviceId,
            'auth_provider' => $provider,
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'ip_address' => $request->ip(),
            'session_created_at' => $sessionCreatedAt,
            'last_seen_at' => now(),
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
            'sessionId' => $sessionId,
        ];
    }

    private function parseUserAgent(string $userAgent): array
    {
        $browser = 'Unknown';
        foreach (['Edg' => 'Edge', 'Chrome' => 'Chrome', 'Firefox' => 'Firefox', 'Safari' => 'Safari'] as $needle => $name) {
            if (stripos($userAgent, $needle) !== false) {
                $browser = $name;
                break;
            }
        }

        $os = 'Unknown';
        foreach (['Windows' => 'Windows', 'Mac OS' => 'macOS', 'Android' => 'Android', 'iPhone' => 'iOS', 'Linux' => 'Linux'] as $needle => $name) {
            if (stripos($userAgent, $needle) !== false) {
                $os = $name;
                break;
            }
        }

        $deviceType = preg_match('/Mobile|Android|iPhone/i', $userAgent) ? 'mobile' : 'desktop';

        return [
            'deviceName' => trim($browser.' on '.$os),
            'deviceType' => $deviceType,
            'browser' => $browser,
            'operatingSystem' => $os,
        ];
    }

    private function maskIp(string $ipAddress): ?string
    {
        if ($ipAddress === '') {
            return null;
        }
        if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return preg_replace('/\.\d+$/', '.***', $ipAddress);
        }
        return preg_replace('/:[0-9a-f]{1,4}$/i', ':****', $ipAddress);
    }

    private function looksLikeUuid(string $value): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $value);
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
