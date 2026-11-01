<?php

namespace App\Http\Controllers;

use App\Services\PaymentGatewayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PaymentWebhookController extends Controller
{
    public function midtrans(Request $request, PaymentGatewayService $gateway): JsonResponse
    {
        try {
            $result = $gateway->processWebhook($request->all());

            return response()->json([
                'status' => 'ok',
                'idempotent' => $result['idempotent'],
                'payment_status' => $result['payment']->status,
                'gateway_status' => $result['payment']->gateway_status,
            ], 200);
        } catch (AccessDeniedHttpException $e) {
            return response()->json([
                'status' => 'forbidden',
                'message' => $e->getMessage(),
            ], 403);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'invalid',
                'errors' => $e->errors(),
            ], 422);
        }
    }
}
