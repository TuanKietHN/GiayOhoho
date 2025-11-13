<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaymentController extends Controller
{
    // Placeholder callback: cập nhật trạng thái đơn khi provider báo thành công
    public function callback(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|integer|exists:order_details,id',
            'provider' => 'required|string',
            'status' => 'required|string|in:success,failed',
            'amount' => 'nullable|numeric',
            'transaction_id' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data) {
            $order = OrderDetail::findOrFail($data['order_id']);
            if ($data['status'] === 'success') {
                $order->status = 'paid';
            }
            $order->save();
            // Lưu payment_details nếu bạn mở rộng model/logic sau
            return response()->json(['message' => 'updated', 'order' => $order]);
        });
    }
}

