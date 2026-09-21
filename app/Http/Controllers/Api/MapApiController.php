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
     * Proxies geocoding requests to Google Geocoding API with Nominatim fallback.
     */
    public function geocode(Request $request): JsonResponse
    {
        $request->validate([
            'address' => 'required|string',
        ]);

        $address = $request->input('address');
        $key = config('services.google_maps.key');
        
        if (!empty($key)) {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $address,
                'key'     => $key
            ]);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'OK' && !empty($data['results'])) {
                    $result = $data['results'][0];
                    $location = $result['geometry']['location'];

                    return response()->json([
                        'latitude'          => $location['lat'],
                        'longitude'         => $location['lng'],
                        'formatted_address' => $result['formatted_address'],
                        'place_id'          => $result['place_id']
                    ]);
                }
            }
        }

        // Nominatim Fallback
        $nomResponse = Http::withHeaders([
            'User-Agent' => 'WM-Product-SaaS/1.0'
        ])->get('https://nominatim.openstreetmap.org/search', [
            'q'      => $address,
            'format' => 'json',
            'limit'  => 1
        ]);

        if ($nomResponse->successful() && !empty($nomResponse->json())) {
            $first = $nomResponse->json()[0];
            return response()->json([
                'latitude'          => (float)$first['lat'],
                'longitude'         => (float)$first['lon'],
                'formatted_address' => $first['display_name'],
                'place_id'          => (string)($first['place_id'] ?? '')
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Geocoding failed.'
        ], 422);
    }

    /**
     * GET /api/maps/autocomplete
     * Proxies autocomplete requests to Google Places Autocomplete API with Nominatim fallback.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate([
            'query'   => 'required|string',
            'country' => 'nullable|string|max:10',
        ]);

        $query = $request->input('query');
        $key = config('services.google_maps.key');

        if (!empty($key)) {
            $params = [
                'input' => $query,
                'key'   => $key
            ];

            if ($request->filled('country')) {
                $params['components'] = 'country:' . $request->input('country');
            }

            $response = Http::get('https://maps.googleapis.com/maps/api/place/autocomplete/json', $params);

            if ($response->successful()) {
                $data = $response->json();
                if (($data['status'] ?? '') === 'OK' && !empty($data['predictions'])) {
                    $predictions = collect($data['predictions'])->map(function ($p) {
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
        }

        // Nominatim Fallback
        $nomResponse = Http::withHeaders([
            'User-Agent' => 'WM-Product-SaaS/1.0'
        ])->get('https://nominatim.openstreetmap.org/search', [
            'q'      => $query,
            'format' => 'json',
            'limit'  => 5
        ]);

        if ($nomResponse->successful()) {
            $predictions = collect($nomResponse->json())->map(function ($item) {
                return [
                    'description' => $item['display_name'],
                    'place_id'    => (string)($item['place_id'] ?? ''),
                    'lat'         => (float)$item['lat'],
                    'lon'         => (float)$item['lon']
                ];
            });

            return response()->json([
                'predictions' => $predictions
            ]);
        }

        return response()->json([
            'predictions' => []
        ]);
    }
}
