<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class MapApiController extends Controller
{
    /**
     * GET /api/config
     * Returns maps initialization parameters.
     */
    public function config(): JsonResponse
    {
        $key = config('services.google_maps.key');
        
        return response()->json([
            'google_maps_api_key' => $key,
            'maps_configured'     => !empty($key),
            'maps_libraries'      => ['places', 'marker', 'visualization'],
            'risk_pin_colors'     => [
                'High'   => '#ef4444',
                'Medium' => '#f59e0b',
                'Low'    => '#22c55e'
            ],
            'websocket_path'      => '/ws/deals/' . (auth()->id() ?? '')
        ]);
    }

    /**
     * GET /api/maps/geocode
     * Proxies geocoding requests to Google Geocoding API.
     */
    public function geocode(Request $request): JsonResponse
    {
        $request->validate([
            'address' => 'required|string',
        ]);

        $key = config('services.google_maps.key');
        if (empty($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Google Maps API key is not configured.'
            ], 500);
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
            'address' => $request->input('address'),
            'key'     => $key
        ]);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to Google Geocoding API.'
            ], 502);
        }

        $data = $response->json();

        if (($data['status'] ?? '') !== 'OK' || empty($data['results'])) {
            return response()->json([
                'success' => false,
                'message' => $data['error_message'] ?? 'No geocoding results found.'
            ], 422);
        }

        $result = $data['results'][0];
        $location = $result['geometry']['location'];

        return response()->json([
            'latitude'          => $location['lat'],
            'longitude'         => $location['lng'],
            'formatted_address' => $result['formatted_address'],
            'place_id'          => $result['place_id']
        ]);
    }

    /**
     * GET /api/maps/autocomplete
     * Proxies autocomplete requests to Google Places Autocomplete API.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate([
            'query'   => 'required|string',
            'country' => 'nullable|string|max:10',
        ]);

        $key = config('services.google_maps.key');
        if (empty($key)) {
            return response()->json([
                'success' => false,
                'message' => 'Google Maps API key is not configured.'
            ], 500);
        }

        $params = [
            'input' => $request->input('query'),
            'key'   => $key
        ];

        if ($request->filled('country')) {
            $params['components'] = 'country:' . $request->input('country');
        }

        $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', $params);

        if ($response->failed()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to connect to Google Places API.'
            ], 502);
        }

        $data = $response->json();

        if (($data['status'] ?? '') !== 'OK') {
            return response()->json([
                'success' => false,
                'message' => $data['error_message'] ?? 'Places autocomplete failed.'
            ], 422);
        }

        $predictions = collect($data['predictions'] ?? [])->map(function ($p) {
            return [
                'description' => $p['description'],
                'place_id'    => $p['place_id']
            ];
        });

        return response()->json([
            'predictions' => $predictions
        ]);
    }
}
