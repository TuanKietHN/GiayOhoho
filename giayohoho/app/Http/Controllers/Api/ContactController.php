<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ContactController extends Controller
{
    public function submit(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'email' => 'required|email|max:255',
            'topic' => 'required|string|max:80',
            'message' => 'required|string|max:5000',
            'companyWebsite' => 'nullable|string|max:255',
            'startedAt' => 'nullable|integer',
        ]);

        if (! empty($data['companyWebsite'])) {
            return $this->ok(null, 'Đã ghi nhận nội dung. Bộ phận hỗ trợ sẽ liên hệ lại sớm.');
        }

        if (Schema::hasTable('mail_outbox')) {
            DB::table('mail_outbox')->updateOrInsert(
                ['dedupe_key' => 'contact:'.hash('sha256', $data['email'].'|'.$data['topic'].'|'.$data['message'])],
                [
                    'aggregate_type' => 'CONTACT',
                    'aggregate_id' => $data['email'],
                    'mail_type' => 'CONTACT_REQUEST',
                    'recipient' => config('mail.from.address', 'support@example.test'),
                    'subject' => 'Contact request: '.$data['topic'],
                    'payload_json' => json_encode($data, JSON_UNESCAPED_UNICODE),
                    'status' => 'PENDING',
                    'next_attempt_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        return $this->ok(null, 'Đã ghi nhận nội dung. Bộ phận hỗ trợ sẽ liên hệ lại sớm.');
    }
}
