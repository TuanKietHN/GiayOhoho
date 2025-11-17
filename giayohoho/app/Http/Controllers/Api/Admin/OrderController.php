<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->string('status')->toString();
        $orders = OrderDetail::with(['items.variant.product', 'user'])
            ->when($status, fn($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(50);
        return response()->json($orders);
    }

    public function show(int $id)
    {
        $order = OrderDetail::with(['items.variant.product', 'user'])->findOrFail($id);
        return response()->json($order);
    }

    public function updateStatus(Request $request, int $id)
    {
        $data = $request->validate([
            'status' => 'required|string|in:pending,paid,shipping,done,cancel',
        ]);
        $order = OrderDetail::findOrFail($id);
        if ($order->status === 'done') {
            return response()->json(['message' => 'immutable_done'], 422);
        }
        $order->status = $data['status'];
        $order->save();
        return response()->json($order);
    }
}

