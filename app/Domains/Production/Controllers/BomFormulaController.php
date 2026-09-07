<?php

namespace App\Domains\Production\Controllers;

use App\Http\Controllers\Controller;
use App\Domains\Production\Services\BomFormulaEvaluatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BomFormulaController extends Controller
{
    public function __construct(
        private readonly BomFormulaEvaluatorService $evaluatorService
    ) {
    }

    /**
     * AJAX endpoint to preview formula evaluation.
     */
    public function preview(Request $request): JsonResponse
    {
        $formula = (string) $request->input('formula', '');
        $parameters = $request->input('parameters', $request->input('sample_parameters', []));

        if (!is_array($parameters)) {
            $parameters = [];
        }

        $result = $this->evaluatorService->previewFormula($formula, $parameters);

        $status = $result['success'] ? 200 : 422;
        return response()->json($result, $status);
    }
}
