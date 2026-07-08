<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\RefreshTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AccountSessionController extends Controller
{
    public function index(int $accountId, RefreshTokenService $refreshTokens)
    {
        User::withTrashed()->findOrFail($accountId);

        return $this->ok($refreshTokens->adminSessions($accountId), 'Đã tải danh sách phiên đăng nhập');
    }

    public function destroy(
        Request $request,
        int $accountId,
        string $sessionId,
        RefreshTokenService $refreshTokens
    ) {
        $target = User::withTrashed()->findOrFail($accountId);
        $refreshTokens->revokeAccountSession($accountId, $sessionId);
        $this->recordAudit($request, $target, 'SESSION_REVOKED_BY_ADMIN', $sessionId);

        return response()->noContent();
    }

    public function destroyAll(Request $request, int $accountId, RefreshTokenService $refreshTokens)
    {
        $target = User::withTrashed()->findOrFail($accountId);
        $refreshTokens->revokeAll($accountId);
        $this->recordAudit($request, $target, 'ALL_SESSIONS_REVOKED_BY_ADMIN');

        return response()->noContent();
    }

    private function recordAudit(Request $request, User $target, string $eventType, ?string $sessionId = null): void
    {
        DB::table('account_login_events')->insert([
            'account_id' => $target->id,
            'actor_account_id' => $request->user()?->id,
            'session_id' => $sessionId && preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $sessionId) ? $sessionId : null,
            'email' => $target->email,
            'event_type' => $eventType,
            'success' => true,
            'failure_reason' => null,
            'provider' => 'ADMIN',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 512, ''),
            'created_at' => now(),
        ]);
    }
}
