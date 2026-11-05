<?php

namespace App\Domains\Accounting\Controllers\Api\Integration;

use App\Domains\Accounting\Models\IntegrationConnection;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class IntegrationApiController extends Controller
{
    /**
     * Global tenant scope means another tenant's connection is simply not found.
     * A connector token additionally works for its own connection only.
     */
    protected function connection(Request $request, int $id): IntegrationConnection
    {
        $connection = IntegrationConnection::query()->findOrFail($id);
        $user = $request->user();

        abort_unless(
            $user->tokenCan($connection->tokenAbility()),
            403,
            'This token is for a different connection.'
        );

        return $connection;
    }

    protected function ok(mixed $data = null, string $message = 'OK', int $status = 200, array $headers = []): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status, $headers);
    }

    protected function refused(string $message, int $status = 422): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], $status);
    }

    protected function paginated(LengthAwarePaginator $page): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => 'OK',
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'last_page' => $page->lastPage(),
            ],
        ]);
    }

    protected function perPage(Request $request): int
    {
        return max(1, min((int) $request->query('per_page', 25), 100));
    }
}
