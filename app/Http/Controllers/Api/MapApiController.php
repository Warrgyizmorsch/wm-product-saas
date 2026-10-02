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
        return response()->json([
            'map_engine'          => 'leaflet_osm',
            'maps_configured'     => true,
            'tile_layer_url'      => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
            'tile_attribution'    => '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
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
     * Proxies geocoding requests to OpenStreetMap Nominatim.
     */
    public function geocode(Request $request): JsonResponse
    {
        $request->validate([
            'address' => 'required|string',
        ]);

        $address = $request->input('address');

        try {
            $nomResponse = Http::withHeaders([
                'User-Agent' => 'WM-Product-SaaS/1.0 (ERP HRMS)'
            ])->timeout(8)->get('https://nominatim.openstreetmap.org/search', [
                'q'              => $address,
                'format'         => 'json',
                'addressdetails' => 1,
                'limit'          => 1
            ]);

            if ($nomResponse->successful() && !empty($nomResponse->json())) {
                $first = $nomResponse->json()[0];
                return response()->json([
                    'success'           => true,
                    'latitude'          => (float)$first['lat'],
                    'longitude'         => (float)$first['lon'],
                    'formatted_address' => $first['display_name'],
                    'place_id'          => (string)($first['place_id'] ?? '')
                ]);
            }
        } catch (\Exception $e) {
            // Graceful fallback
        }

        return response()->json([
            'success' => false,
            'message' => 'Geocoding failed.'
        ], 422);
    }

    /**
     * GET /api/maps/autocomplete
     * Proxies autocomplete requests to OpenStreetMap Nominatim.
     */
    public function autocomplete(Request $request): JsonResponse
    {
        $request->validate([
            'query'   => 'required|string',
            'country' => 'nullable|string|max:10',
        ]);

        $query = $request->input('query');
        $params = [
            'q'              => $query,
            'format'         => 'json',
            'addressdetails' => 1,
            'limit'          => 6
        ];

        if ($request->filled('country')) {
            $params['countrycodes'] = strtolower($request->input('country'));
        }

        try {
            $nomResponse = Http::withHeaders([
                'User-Agent' => 'WM-Product-SaaS/1.0 (ERP HRMS)'
            ])->timeout(6)->get('https://nominatim.openstreetmap.org/search', $params);

            if ($nomResponse->successful()) {
                $predictions = collect($nomResponse->json())->map(function ($item) {
                    return [
                        'description'  => $item['display_name'],
                        'display_name' => $item['display_name'],
                        'place_id'     => (string)($item['place_id'] ?? ''),
                        'lat'          => (float)$item['lat'],
                        'lon'          => (float)$item['lon'],
                        'lng'          => (float)$item['lon']
                    ];
                });

                return response()->json([
                    'success'     => true,
                    'predictions' => $predictions
                ]);
            }
        } catch (\Exception $e) {
            // Graceful fallback
        }

        return response()->json([
            'success'     => false,
            'predictions' => []
        ]);
    }
}
