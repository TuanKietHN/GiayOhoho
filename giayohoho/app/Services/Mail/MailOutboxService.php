<?php

namespace App\Services\Mail;

use App\Models\MailOutbox;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class MailOutboxService
{
    public function enqueue(
        UserMailContext $context,
        string $mailType,
        string $subject,
        array $payload
    ): MailOutbox {
        $outbox = MailOutbox::create([
            'aggregate_type' => 'ACCOUNT',
            'aggregate_id' => (string) $context->accountId,
            'mail_type' => $mailType,
            'dedupe_key' => 'account:'.$context->accountId.':'.strtolower($mailType).':'.Str::uuid(),
            'recipient' => $context->email,
            'subject' => $subject,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'status' => 'PENDING',
            'attempts' => 0,
            'next_attempt_at' => now(),
        ]);

        try {
            Mail::raw($this->body($context, $payload), function ($message) use ($context, $subject) {
                $message->to($context->email)->subject($subject);
            });
            $outbox->forceFill([
                'status' => 'SENT',
                'attempts' => 1,
                'sent_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $outbox->forceFill([
                'status' => 'PENDING',
                'attempts' => 1,
                'next_attempt_at' => now()->addMinutes(5),
                'last_error' => Str::limit($exception->getMessage(), 500, ''),
            ])->save();
        }

        return $outbox;
    }

    private function body(UserMailContext $context, array $payload): string
    {
        $lines = [
            'Xin chao '.$context->displayName.',',
            '',
            (string) ($payload['message'] ?? 'Vui long mo lien ket ben duoi de tiep tuc.'),
        ];

        if (! empty($payload['link'])) {
            $lines[] = '';
            $lines[] = (string) $payload['link'];
        }

        $lines[] = '';
        $lines[] = 'GiayOhoho';

        return implode(PHP_EOL, $lines);
    }
}
