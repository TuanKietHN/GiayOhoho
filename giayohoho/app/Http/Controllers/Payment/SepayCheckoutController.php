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

        $merchantId = config('services.sepay.merchant_id', env('SEPAY_MERCHANT_ID'));
        $secretKey = config('services.sepay.secret_key', env('SEPAY_SECRET_KEY'));
        $env = env('SEPAY_ENV', 'sandbox') === 'production' ? SePayClient::ENVIRONMENT_PRODUCTION : SePayClient::ENVIRONMENT_SANDBOX;

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