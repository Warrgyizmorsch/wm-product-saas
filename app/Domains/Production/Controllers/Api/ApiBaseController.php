<?php

namespace App\Domains\Production\Controllers\Api;

use App\Core\Tenant\TenantContext;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * ApiBaseController
 *
 * Base controller for the versioned Production REST API (/api/v1/production/*).
 * Standardizes envelope shapes, pagination constraints, error mappings,
 * and tenant context resolution.
 */
abstract class ApiBaseController extends Controller
{
    /**
     * Standard JSON success response.
     */
    protected function successResponse(
        mixed $data = null,
        string $message = 'Operation successful.',
        int $statusCode = Response::HTTP_OK,
        array $meta = [],
        array $links = []
    ): JsonResponse {
        $payload = [
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        if (!empty($links)) {
            $payload['links'] = $links;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Standard JSON created (201) response.
     */
    protected function createdResponse(
        mixed $data = null,
        string $message = 'Resource created successfully.',
        array $meta = []
    ): JsonResponse {
        return $this->successResponse($data, $message, Response::HTTP_CREATED, $meta);
    }

    /**
     * Standard JSON 204 No Content response.
     */
    protected function noContentResponse(): Response
    {
        return response()->noContent();
    }

    /**
     * Standard JSON error response.
     */
    protected function errorResponse(
        string $message,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        ?array $errors = null
    ): JsonResponse {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Standard paginated response transformer.
     * Enforces the unified envelope structure with pagination metadata.
     */
    protected function paginatedResponse(
        LengthAwarePaginator $paginator,
        string $resourceClass,
        string $message = 'Records retrieved successfully.'
    ): JsonResponse {
        $collection = $resourceClass::collection($paginator->items());

        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $collection,
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links'   => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Safely resolve the active tenant ID from TenantContext or authenticated user.
     */
    protected function getTenantId(): int
    {
        $contextId = app(TenantContext::class)->id();
        if ($contextId) {
            return (int) $contextId;
        }

        $user = auth()->user();
        if ($user && $user->tenant_id) {
            return (int) $user->tenant_id;
        }

        if (function_exists('tenant_id') && tenant_id()) {
            return (int) tenant_id();
        }

        abort(Response::HTTP_FORBIDDEN, 'Tenant context could not be resolved.');
    }

    /**
     * Resolve bounded page size from query parameter with configurable cap.
     */
    protected function getPerPage(Request $request, int $default = 25, int $max = 100): int
    {
        $perPage = (int) $request->query('per_page', $default);

        if ($perPage <= 0) {
            return $default;
        }

        return min($perPage, $max);
    }

    /**
     * Map domain exceptions to appropriate HTTP error responses without leaking internals.
     */
    protected function handleDomainException(\Throwable $e): JsonResponse
    {
        if ($e instanceof ModelNotFoundException) {
            return $this->errorResponse('Resource not found.', Response::HTTP_NOT_FOUND);
        }

        if ($e instanceof ValidationException) {
            return $this->errorResponse(
                $e->getMessage() ?: 'The given data was invalid.',
                Response::HTTP_UNPROCESSABLE_ENTITY,
                $e->errors()
            );
        }

        // Domain rule violations and state transition conflicts
        if ($e instanceof \DomainException || $e instanceof \InvalidArgumentException) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT);
        }

        // Fallback: log internal error and return sanitized message
        report($e);

        return $this->errorResponse(
            'An unexpected error occurred while processing the request.',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }
}
