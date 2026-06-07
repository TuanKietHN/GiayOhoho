<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Role;
use App\Models\User;
use App\Services\Auth\GoogleOAuthService;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request, RefreshTokenService $refreshTokens)
    {
        $this->normalizeRegisterPayload($request);

        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:255', 'regex:/^[\p{L}\s\'.-]+$/u'],
            'last_name' => ['nullable', 'string', 'max:255', 'regex:/^[\p{L}\s\'.-]+$/u'],
            'username' => ['required', 'string', 'max:255', 'regex:/^[A-Za-z0-9_.-]+$/', 'unique:accounts,username'],
            'email' => 'required|email|max:255|unique:accounts,email',
            'password' => 'required|string|min:6',
            'phone_number' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+()\s-]{6,20}$/'],
            'birth_of_date' => 'nullable|date',
            'address_line' => 'nullable|string|max:255',
            'ward' => 'nullable|string|max:255',
            'district' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'ghn_province_id' => 'nullable|integer',
            'ghn_district_id' => 'nullable|integer',
            'ghn_ward_code' => 'nullable|string|max:50',
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => trim(strip_tags($data['first_name'].' '.($data['last_name'] ?? ''))),
                'first_name' => trim(strip_tags($data['first_name'])),
                'last_name' => isset($data['last_name']) ? trim(strip_tags($data['last_name'])) : null,
                'username' => trim($data['username']),
                'email' => strtolower(trim($data['email'])),
                'password' => Hash::make($data['password']),
                'phone_number' => $data['phone_number'] ?? null,
                'birth_of_date' => $data['birth_of_date'] ?? null,
                'status' => 'ACTIVE',
                'locked' => false,
                'email_verified' => true,
                'email_verified_at' => now(),
            ]);

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
                    'phone_number' => $user->phone_number,
                    'birth_of_date' => $user->birth_of_date,
                    'created_at' => now(),
                    'deleted_at' => null,
                ]
            );

            if (! empty($data['address_line']) || ! empty($data['city'])) {
                Address::create([
                    'account_id' => $user->id,
                    'address_line' => $data['address_line'] ?? null,
                    'ward' => $data['ward'] ?? null,
                    'district' => $data['district'] ?? null,
                    'city' => $data['city'] ?? null,
                    'country' => 'VN',
                    'ghn_province_id' => $data['ghn_province_id'] ?? null,
                    'ghn_district_id' => $data['ghn_district_id'] ?? null,
                    'ghn_ward_code' => $data['ghn_ward_code'] ?? null,
                ]);
            }

            return $user->load('roles');
        });

        $token = $user->createToken('api_token')->plainTextToken;
        $refresh = $refreshTokens->issue($user, $request);

        return $this->withRefreshCookies(
            $this->created($this->authPayload($user, $token, $refresh)),
            $refreshTokens,
            $refresh
        );
    }

    public function login(Request $request, RefreshTokenService $refreshTokens)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', strtolower(trim($data['email'])))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Thong tin dang nhap khong chinh xac.'],
            ]);
        }

        if ($user->locked || $user->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'email' => ['Tai khoan dang bi khoa hoac khong con hoat dong.'],
            ]);
        }

        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
            'login_count' => ((int) $user->login_count) + 1,
        ])->save();

        $token = $user->createToken('api_token')->plainTextToken;
        $refresh = $refreshTokens->issue($user, $request);

        return $this->withRefreshCookies(
            $this->ok($this->authPayload($user->load('roles'), $token, $refresh)),
            $refreshTokens,
            $refresh
        );
    }

    public function logout(Request $request, RefreshTokenService $refreshTokens)
    {
        $refreshTokens->revokePlainToken($refreshTokens->tokenFromRequest($request));
        $request->user()?->currentAccessToken()?->delete();

        $response = $this->ok(null, 'Logged out');
        foreach ($refreshTokens->forgetCookies() as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    public function me(Request $request)
    {
        return $this->ok($this->accountDto($request->user()->load('roles')));
    }

    public function refresh(Request $request, RefreshTokenService $refreshTokens)
    {
        $plainToken = $refreshTokens->tokenFromRequest($request);
        if (! $plainToken) {
            return response()->json([
                'success' => false,
                'message' => 'Refresh token is required.',
                'data' => null,
                'timestamp' => now()->getTimestampMs(),
            ], 401);
        }

        try {
            $refreshTokens->validateRefreshCsrf($request);
            $rotated = $refreshTokens->rotate($plainToken, $request);
        } catch (AuthenticationException $exception) {
            return response()->json([
                'success' => false,
                'message' => $exception->getMessage(),
                'data' => null,
                'timestamp' => now()->getTimestampMs(),
            ], 401);
        }

        $user = $rotated['user']->load('roles');
        $token = $user->createToken('api_token')->plainTextToken;

        return $this->withRefreshCookies(
            $this->ok($this->authPayload($user, $token, $rotated['refresh'])),
            $refreshTokens,
            $rotated['refresh']
        );
    }

    public function google(Request $request, GoogleOAuthService $googleOAuth, RefreshTokenService $refreshTokens)
    {
        $request->validate(['idToken' => 'required|string']);

        $user = $googleOAuth->authenticate((string) $request->input('idToken'), $request);
        $token = $user->createToken('api_token')->plainTextToken;
        $refresh = $refreshTokens->issue($user, $request);

        return $this->withRefreshCookies(
            $this->ok($this->authPayload($user, $token, $refresh), 'Google login completed'),
            $refreshTokens,
            $refresh
        );
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        return $this->ok(null, 'Nếu email tồn tại, hướng dẫn đặt lại mật khẩu sẽ được gửi.');
    }

    public function resendVerification(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        return $this->ok(null, 'Nếu email tồn tại, email xác thực sẽ được gửi lại.');
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'newPassword' => 'required|string|min:6',
        ]);

        return $this->ok(null, 'Reset password token accepted by migration stub.');
    }

    public function verifyEmail(Request $request)
    {
        $request->validate(['token' => 'required|string']);

        return $this->ok(null, 'Email verification token accepted by migration stub.');
    }

    public function changePassword(Request $request, RefreshTokenService $refreshTokens)
    {
        $data = $request->validate([
            'currentPassword' => 'required|string',
            'newPassword' => 'required|string|min:6',
        ]);

        $user = $request->user();
        if (! Hash::check($data['currentPassword'], $user->password)) {
            throw ValidationException::withMessages([
                'currentPassword' => ['Mật khẩu hiện tại không chính xác.'],
            ]);
        }

        $user->forceFill(['password' => Hash::make($data['newPassword'])])->save();
        $refreshTokens->revokeOtherSessions($user, $refreshTokens->tokenFromRequest($request));
        $currentTokenId = $user->currentAccessToken()?->id;
        $user->tokens()
            ->when($currentTokenId, fn($query) => $query->where('id', '<>', $currentTokenId))
            ->delete();

        return $this->ok(null, 'Password changed');
    }

    public function setupPassword(Request $request)
    {
        $data = $request->validate([
            'newPassword' => 'required|string|min:6',
        ]);

        $user = $request->user();
        $user->forceFill(['password' => Hash::make($data['newPassword'])])->save();

        return $this->ok($this->accountDto($user->load('roles')), 'Password setup completed');
    }

    public function sessions(Request $request, RefreshTokenService $refreshTokens)
    {
        return $this->ok($refreshTokens->sessions($request->user()));
    }

    public function revokeSession(Request $request, string $deviceFingerprint, RefreshTokenService $refreshTokens)
    {
        $refreshTokens->revokeByFingerprint($request->user(), $deviceFingerprint);
        if (ctype_digit($deviceFingerprint)) {
            $request->user()->tokens()->where('id', (int) $deviceFingerprint)->delete();
        }

        return $this->ok(null, 'Session revoked');
    }

    public function revokeSessions(Request $request, RefreshTokenService $refreshTokens)
    {
        $refreshTokens->revokeOtherSessions($request->user(), $refreshTokens->tokenFromRequest($request));
        $currentTokenId = $request->user()?->currentAccessToken()?->id;
        $request->user()->tokens()
            ->when($currentTokenId, fn($query) => $query->where('id', '<>', $currentTokenId))
            ->delete();

        return $this->ok(null, 'All sessions revoked');
    }

    private function normalizeRegisterPayload(Request $request): void
    {
        $firstName = $request->input('first_name', $request->input('firstName'));
        $lastName = $request->input('last_name', $request->input('lastName'));
        $phoneNumber = $request->input('phone_number', $request->input('phoneNumber'));
        $birthOfDate = $request->input('birth_of_date', $request->input('birthOfDate'));
        $addressLine = $request->input('address_line', $request->input('addressLine'));
        $ghnProvinceId = $request->input('ghn_province_id', $request->input('ghnProvinceId'));
        $ghnDistrictId = $request->input('ghn_district_id', $request->input('ghnDistrictId'));
        $ghnWardCode = $request->input('ghn_ward_code', $request->input('ghnWardCode'));
        $username = $request->input('username') ?: str($request->input('email'))->before('@')->slug('_')->toString();

        $request->merge([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => $username,
            'phone_number' => $phoneNumber,
            'birth_of_date' => $birthOfDate,
            'address_line' => $addressLine,
            'ghn_province_id' => $ghnProvinceId,
            'ghn_district_id' => $ghnDistrictId,
            'ghn_ward_code' => $ghnWardCode,
        ]);
    }

    private function authPayload(User $user, string $token, ?array $refresh = null): array
    {
        return [
            'token' => $token,
            'accessToken' => $token,
            'refreshToken' => $refresh['refreshToken'] ?? null,
            'csrfToken' => $refresh['csrfToken'] ?? null,
            'refreshTokenExpiresAt' => isset($refresh['expiresAt']) ? $refresh['expiresAt']->toIso8601String() : null,
            'refreshTokenExpiresIn' => $refresh['expiresInSeconds'] ?? null,
            'account' => $this->accountDto($user),
            'requiresPasswordSetup' => false,
        ];
    }

    private function withRefreshCookies($response, RefreshTokenService $refreshTokens, array $refresh)
    {
        foreach ($refreshTokens->cookiesFor($refresh) as $cookie) {
            $response->withCookie($cookie);
        }

        return $response;
    }

    private function accountDto(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'username' => $user->username,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'phoneNumber' => $user->phone_number,
            'roles' => $user->roles->pluck('name')->map(fn (string $name) => strtoupper($name))->values()->all(),
            'requiresPasswordSetup' => false,
        ];
    }
}
