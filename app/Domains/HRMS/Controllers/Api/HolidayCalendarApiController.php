<?php

namespace App\Domains\HRMS\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Domains\HRMS\Models\HolidayCalendar;
use App\Domains\HRMS\Repositories\HolidayCalendarRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HolidayCalendarApiController extends Controller
{
    public function __construct(
        private readonly HolidayCalendarRepositoryInterface $holidayCalendarRepository
    ) {}

    /**
     * Helper for standardized success JSON response.
     */
    private function sendSuccess(mixed $data = null, string $message = 'Operation successful', int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    /**
     * Helper for standardized error JSON response.
     */
    private function sendError(string $message = 'An error occurred', int $statusCode = 400, mixed $errors = null): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    /**
     * Null-safe authorization check.
     */
    private function authorizeUser(): ?JsonResponse
    {
        if (!auth()->check()) {
            $authUser = request()->getUser();
            $authPass = request()->getPassword();

            if ($authUser && $authPass) {
                if (!auth()->attempt(['email' => $authUser, 'password' => $authPass])) {
                    return $this->sendError('Invalid HTTP Basic Auth username or password.', 401);
                }
            } else {
                return $this->sendError('Unauthenticated access. Please log in or provide HTTP Basic Auth credentials.', 401);
            }
        }

        return null;
    }

    /**
     * GET /api/hrms/holidays
     */
    public function index(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $data = $this->holidayCalendarRepository->getIndexData($request->all());
        return $this->sendSuccess($data);
    }

    /**
     * POST /api/hrms/holidays
     */
    public function store(Request $request): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'company_id' => 'nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
        ]);

        $validated['status'] = $request->has('status') ? (bool) $request->status : true;

        $holiday = $this->holidayCalendarRepository->storeHoliday($validated);
        return $this->sendSuccess($holiday, 'Holiday created successfully.', 201);
    }

    /**
     * GET /api/hrms/holidays/{holiday}
     */
    public function show(HolidayCalendar $holiday): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        return $this->sendSuccess($holiday->load(['company', 'businessUnit', 'branch']));
    }

    /**
     * PUT /api/hrms/holidays/{holiday}
     */
    public function update(Request $request, HolidayCalendar $holiday): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'holiday_date' => 'required|date',
            'description' => 'nullable|string|max:1000',
            'company_id' => 'nullable|exists:companies,id',
            'business_unit_id' => 'nullable|exists:business_units,id',
            'branch_id' => 'nullable|exists:branches,id',
            'status' => 'nullable|boolean',
        ]);

        if ($request->has('status')) {
            $validated['status'] = (bool) $request->status;
        }

        $this->holidayCalendarRepository->updateHoliday($holiday, $validated);
        return $this->sendSuccess($holiday->fresh(), 'Holiday updated successfully.');
    }

    /**
     * DELETE /api/hrms/holidays/{holiday}
     */
    public function destroy(HolidayCalendar $holiday): JsonResponse
    {
        if ($authError = $this->authorizeUser()) {
            return $authError;
        }

        $this->holidayCalendarRepository->deleteHoliday($holiday);
        return $this->sendSuccess(null, 'Holiday deleted successfully.');
    }
}
