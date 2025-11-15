<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SepayController extends Controller
{
    public function ipn(Request $request)
    {
        Log::info('SePay IPN', $request->all());
        return response()->json(['status' => 'ok']);
    }
}