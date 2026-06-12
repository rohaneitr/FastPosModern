<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Services\DeviceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LicenseActivationController — Phase 3 Refactored
 *
 * BEFORE: 167 lines — inline business validation, device limit check, Sanctum
 *         token generation, and heartbeat extension.
 * AFTER:  ~60 lines — pure HTTP orchestration (validate → delegate → respond)
 *
 * Delegated to:
 *   DeviceRegistrationService::activateDeviceByLicense() — System B activation
 *   DeviceRegistrationService::recordHeartbeatToken()    — System B heartbeat
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.5
 * @version 2026-06-12
 */
class LicenseActivationController extends Controller
{
    public function __construct(
        private readonly DeviceRegistrationService $deviceService,
    ) {}

    /**
     * POST /api/v1/licenses/activate-device
     * Activates a device via license_key, creates a 72-hour Sanctum token.
     * Public endpoint — no auth required (license_key is the credential).
     */
    public function activateDevice(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'          => 'required|string',
            'hardware_fingerprint' => 'required|string',
            'device_name'          => 'required|string|max:255',
        ]);

        $result = $this->deviceService->activateDeviceByLicense(
            $request->license_key,
            $request->hardware_fingerprint,
            $request->device_name,
            $request->ip(),
        );

        $httpStatus = $result['httpStatus'];
        unset($result['httpStatus']);

        return response()->json($result, $httpStatus);
    }

    /**
     * POST /api/v1/devices/heartbeat (auth required — Sanctum token flow)
     * Validates the token's bound device fingerprint and extends the token by 72h.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $result = $this->deviceService->recordHeartbeatToken($request->user());

        $httpStatus = $result['httpStatus'];
        unset($result['httpStatus']);

        return response()->json($result, $httpStatus);
    }
}
