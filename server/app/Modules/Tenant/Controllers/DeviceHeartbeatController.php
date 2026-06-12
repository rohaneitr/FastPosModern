<?php

namespace App\Modules\Tenant\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Tenant\Services\DeviceRegistrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * DeviceHeartbeatController — Phase 3 Refactored
 *
 * BEFORE: 308 lines — license validation, fingerprint check, FIFO eviction,
 *         grace period, status queries, and POS activation all inline.
 * AFTER:  ~80 lines — pure HTTP orchestration (validate → delegate → respond)
 *
 * CRITICAL BUG FIXED:
 *   DeviceActivation model was missing entirely — every heartbeat endpoint
 *   would crash with "Class not found". Model created in Task 3.5.
 *
 * Delegated to:
 *   DeviceRegistrationService — all device lifecycle business logic
 *
 * @author  Antigravity AI Agent — Phase 3, Task 3.5
 * @version 2026-06-12
 */
class DeviceHeartbeatController extends Controller
{
    public function __construct(
        private readonly DeviceRegistrationService $deviceService,
    ) {}

    // ── License-key heartbeat (System A: device_activations) ──────────────────

    /**
     * POST /api/v1/devices/heartbeat (public — license key in body)
     * Records a device heartbeat, validates license + fingerprint.
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'   => 'required|string',
            'hardware_hash' => 'required|string|min:8|max:512',
        ]);

        $result = $this->deviceService->heartbeat(
            $request->license_key,
            $request->hardware_hash,
        );

        $httpStatus = $result['httpStatus'];
        unset($result['httpStatus']);

        return response()->json($result, $httpStatus);
    }

    /**
     * POST /api/v1/devices/status
     * Read-only device status check.
     */
    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'license_key'   => 'required|string',
            'hardware_hash' => 'required|string',
        ]);

        $result = $this->deviceService->getDeviceStatus(
            $request->license_key,
            $request->hardware_hash,
        );

        $httpStatus = $result['httpStatus'];
        unset($result['httpStatus']);

        return response()->json($result, $httpStatus);
    }

    // ── Authenticated POS device management (System A) ────────────────────────

    /**
     * POST /api/v1/devices/activate
     * Activate a POS device for the authenticated business (requires auth).
     */
    public function activatePosDevice(Request $request): JsonResponse
    {
        $request->validate([
            'hardware_hash' => 'required|string|min:8|max:512',
            'device_name'   => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if (!$user?->business_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = $this->deviceService->activatePosDevice(
            $user->business_id,
            $request->hardware_hash,
            $request->input('license_key'),
            $request->input('device_name'),
        );

        $httpStatus = $result['httpStatus'];
        unset($result['httpStatus']);

        return response()->json($result, $httpStatus);
    }

    /**
     * GET /api/v1/devices
     * List all registered devices for the authenticated business.
     */
    public function getDevices(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user?->business_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return response()->json($this->deviceService->listDevices($user->business_id));
    }

    /**
     * DELETE /api/v1/devices/{id}
     * Revoke a device. BusinessAdmin only (enforced by route middleware).
     */
    public function revokeDevice(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user?->business_id) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $result = $this->deviceService->revokeDevice($user->business_id, $id);

        $httpStatus = $result['httpStatus'];
        unset($result['httpStatus']);

        return response()->json($result, $httpStatus);
    }
}
