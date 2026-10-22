<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailCodeController extends Controller
{
    /**
     * Envia o código de confirmação. A resposta é a mesma exista ou não conta
     * com esse e-mail — o endpoint não serve para descobrir quem é cliente.
     */
    public function send(Request $request, EmailVerificationCodeService $codes): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $wait = $codes->send($data['email']);

        if ($wait !== null) {
            $minutes = max(1, (int) ceil($wait / 60));

            return response()->json([
                'message' => "Já enviamos alguns códigos para esse e-mail. Confira a caixa de entrada e o spam, ou tente de novo em {$minutes} min.",
            ], 429);
        }

        return response()->json([
            'message' => 'Enviamos um código de 6 dígitos para '.EmailVerificationCodeService::normalize($data['email']).'.',
        ]);
    }
}
