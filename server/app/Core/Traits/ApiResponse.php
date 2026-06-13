<?php

namespace App\Core\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ApiResponse Trait
 *
 * Provides a single, consistent JSON envelope for all API responses.
 * Every response from the FastPOS API MUST use one of these methods.
 *
 * Envelope Contract:
 *  - Success:    { "success": true,  "message": "...", "data": {...} }
 *  - Error:      { "success": false, "message": "...", "code": "...", "errors": {...} }
 *  - Paginated:  { "success": true,  "message": "...", "data": [...], "meta": {...}, "links": {...} }
 *
 * @version Phase 3 — API Architecture Refactor
 */
trait ApiResponse
{
    // ── Success ───────────────────────────────────────────────────────────────

    /**
     * Return a standard success response.
     *
     * @param  mixed       $data    Payload to include under the "data" key.
     * @param  string      $message Human-readable success message.
     * @param  int         $code    HTTP status code (default 200).
     * @return JsonResponse
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operation successful.',
        int $code = 200
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['data'] = $data;
        }

        return response()->json($payload, $code);
    }

    /**
     * Convenience wrapper for 201 Created responses.
     *
     * @param  mixed  $data
     * @param  string $message
     * @return JsonResponse
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Resource created successfully.'
    ): JsonResponse {
        return $this->successResponse($data, $message, 201);
    }

    /**
     * Convenience wrapper for 204 No Content responses.
     * Note: 204 must not include a body; we use 200 with a message instead
     * to keep the envelope consistent for the frontend.
     *
     * @param  string $message
     * @return JsonResponse
     */
    protected function noContentResponse(
        string $message = 'Resource deleted successfully.'
    ): JsonResponse {
        return $this->successResponse(null, $message, 200);
    }

    // ── Error ─────────────────────────────────────────────────────────────────

    /**
     * Return a standard error response.
     *
     * @param  string     $message Human-readable error description.
     * @param  int        $code    HTTP status code.
     * @param  array|null $errors  Field-level validation errors or extra context.
     * @param  string     $errorCode  Machine-readable error code string.
     * @return JsonResponse
     */
    protected function errorResponse(
        string $message = 'An error occurred.',
        int $code = 400,
        ?array $errors = null,
        string $errorCode = 'REQUEST_FAILED'
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
            'code'    => $errorCode,
        ];

        if (! empty($errors)) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $code);
    }

    /**
     * 401 Unauthenticated shortcut.
     */
    protected function unauthenticatedResponse(
        string $message = 'Unauthenticated. Please log in.'
    ): JsonResponse {
        return $this->errorResponse($message, 401, null, 'UNAUTHENTICATED');
    }

    /**
     * 403 Forbidden shortcut.
     */
    protected function forbiddenResponse(
        string $message = 'You do not have permission to perform this action.'
    ): JsonResponse {
        return $this->errorResponse($message, 403, null, 'FORBIDDEN');
    }

    /**
     * 404 Not Found shortcut.
     */
    protected function notFoundResponse(
        string $message = 'The requested resource was not found.'
    ): JsonResponse {
        return $this->errorResponse($message, 404, null, 'NOT_FOUND');
    }

    /**
     * 422 Validation error shortcut.
     *
     * @param  array  $errors  Keyed array of field => [error strings]
     */
    protected function validationErrorResponse(
        array $errors,
        string $message = 'The given data was invalid.'
    ): JsonResponse {
        return $this->errorResponse($message, 422, $errors, 'VALIDATION_FAILED');
    }

    /**
     * 500 Internal server error shortcut.
     */
    protected function serverErrorResponse(
        string $message = 'An unexpected server error occurred.'
    ): JsonResponse {
        return $this->errorResponse($message, 500, null, 'INTERNAL_SERVER_ERROR');
    }

    // ── Paginated ─────────────────────────────────────────────────────────────

    /**
     * Return a paginated collection response.
     * Extracts meta and links from the LengthAwarePaginator automatically.
     *
     * @param  LengthAwarePaginator $paginator
     * @param  string               $message
     * @return JsonResponse
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $message = 'Data retrieved successfully.'
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ], 200);
    }
}
