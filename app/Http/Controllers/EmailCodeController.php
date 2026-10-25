<?php

namespace App\Http\Controllers;

use App\Services\EmailVerificationCodeService;
use App\Support\AccountNotice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailCodeController extends Controller
{
    /**
     * Envia o código de confirmação (criar conta ou comprar como convidado).
     * Decisão de produto: se o e-mail já entra com Google/senha, avisa e manda
     * para o login em vez de enviar código — mais claro para os pais, ao custo
     * de revelar que o e-mail tem conta (o limite por pessoa/IP segura varredura).
     */
    public function send(Request $request, EmailVerificationCodeService $codes): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        // E-mail que já entra com Google ou senha não recebe código: o código é
        // para criar conta. O front mostra o aviso com o botão de entrar certo.
        if (($notice = AccountNotice::forEmail($data['email'])) !== null) {
            return response()->json(['message' => $notice['message'], 'account' => $notice], 409);
        }

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
