<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrderDetail;
use App\Models\Product;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function productsCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products.csv"',
        ];
        $callback = function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id','name','brand','gender','base_price','category_id']);
            Product::chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    fputcsv($out, [$p->id, $p->name, $p->brand, $p->gender, $p->base_price, $p->category_id]);
                }
            });
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }

    public function ordersCsv(): StreamedResponse
    {
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="orders.csv"',
        ];
        $callback = function () {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id','user_id','total','sub_total','discount_amount','status','created_at']);
            OrderDetail::chunk(500, function ($rows) use ($out) {
                foreach ($rows as $o) {
                    fputcsv($out, [$o->id, $o->user_id, $o->total, $o->sub_total, $o->discount_amount, $o->status, $o->created_at]);
                }
            });
            fclose($out);
        };
        return response()->stream($callback, 200, $headers);
    }
}

