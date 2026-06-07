<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShippingEvent;
use App\Models\ShippingOrder;
use Illuminate\Http\Request;

class GhnWebhookController extends Controller
{
    public function orderStatus(Request $request)
    {
        $payload = $request->getContent();
        $decoded = json_decode($payload, true) ?: [];
        $orderCode = $decoded['OrderCode'] ?? $decoded['order_code'] ?? $decoded['providerOrderCode'] ?? null;
        $status = $decoded['Status'] ?? $decoded['status'] ?? null;
        $eventKey = (string) ($decoded['id'] ?? $decoded['event_id'] ?? hash('sha256', $payload));

        ShippingEvent::updateOrCreate(
            ['idempotency_key' => $eventKey],
            [
                'provider' => 'GHN',
                'provider_order_code' => $orderCode,
                'event_type' => 'ORDER_STATUS',
                'status_raw' => $status,
                'payload' => $payload,
                'processed_at' => now(),
            ]
        );

        if ($orderCode) {
            ShippingOrder::where('provider_order_code', $orderCode)->update([
                'status_raw' => $status,
                'status_mapped' => $this->mapStatus($status),
                'raw_latest_payload' => $payload,
                'updated_at' => now(),
            ]);
        }

        return response()->noContent();
    }

    public function ticket(Request $request)
    {
        ShippingEvent::updateOrCreate(
            ['idempotency_key' => hash('sha256', $request->getContent())],
            [
                'provider' => 'GHN',
                'event_type' => 'TICKET',
                'payload' => $request->getContent(),
                'processed_at' => now(),
            ]
        );

        return response()->noContent();
    }

    private function mapStatus(?string $status): ?string
    {
        return match (strtolower((string) $status)) {
            'ready_to_pick', 'picking', 'picked', 'storing', 'transporting' => 'SHIPPING',
            'delivered' => 'DONE',
            'cancel', 'cancelled' => 'CANCELLED',
            'return', 'returned' => 'RETURNING',
            default => $status ? strtoupper($status) : null,
        };
    }
}
