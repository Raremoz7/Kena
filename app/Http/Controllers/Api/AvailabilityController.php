<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EventSession;
use App\Services\AvailabilityService;
use Illuminate\Http\JsonResponse;

class AvailabilityController extends Controller
{
    public function __construct(private readonly AvailabilityService $availability) {}

    public function show(EventSession $session): JsonResponse
    {
        // Só os ocupados (o front trata o resto como livre). A chave continua
        // `seats` para a aba aberta com o JS antigo seguir funcionando.
        return response()->json([
            'seats' => (object) $this->availability->unavailable($session),
        ]);
    }
}
