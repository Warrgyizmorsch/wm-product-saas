<?php

namespace App\Http\Controllers;

use App\Services\Search\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GlobalSearchController extends Controller
{
    public function __construct(
        private readonly GlobalSearchService $searchService,
    ) {
    }

    public function search(Request $request): JsonResponse
    {
        $query = (string) $request->query('q', '');
        $module = (string) $request->query('module', 'all');

        $results = $this->searchService->search(
            $request->user(),
            $query,
            $module
        );

        return response()->json($results);
    }
}
