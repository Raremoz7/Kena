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
        return response()->json([
            'seats' => $this->availability->snapshot($session),
        ]);
    }
}
