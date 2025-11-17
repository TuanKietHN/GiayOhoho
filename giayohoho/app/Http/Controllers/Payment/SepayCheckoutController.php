<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use SePay\SePayClient;
use SePay\Builders\CheckoutBuilder;

class SepayCheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        $amount = (int) $request->query('amount', 0);
        $invoice = $request->query('invoice', 'INV_'.time());
        $desc = $request->query('desc', 'Thanh toán đơn hàng');

        $merchantId = env('SEPAY_MERCHANT_ID') ?: config('services.sepay.merchant_id');
        $secretKey = env('SEPAY_SECRET_KEY') ?: config('services.sepay.secret_key');
        $envName = env('SEPAY_ENV', 'sandbox');
        $env = $envName === 'production' ? SePayClient::ENVIRONMENT_PRODUCTION : SePayClient::ENVIRONMENT_SANDBOX;

        if (!is_string($merchantId) || !is_string($secretKey) || empty($merchantId) || empty($secretKey)) {
            return response('SePay cấu hình thiếu: vui lòng set SEPAY_MERCHANT_ID và SEPAY_SECRET_KEY trong .env', 500);
        }

        $client = new SePayClient($merchantId, $secretKey, $env);

        $appUrl = config('app.url');
        $checkoutData = CheckoutBuilder::make()
            ->currency('VND')
            ->orderAmount($amount)
            ->operation('PURCHASE')
            ->orderDescription($desc)
            ->orderInvoiceNumber($invoice)
            ->successUrl($appUrl.'/payments/success')
            ->errorUrl($appUrl.'/payments/error')
            ->cancelUrl($appUrl.'/payments/cancel')
            ->build();

        $formHtml = $client->checkout()->generateFormHtml($checkoutData);
        return response($formHtml)->header('Content-Type', 'text/html');
    }
}