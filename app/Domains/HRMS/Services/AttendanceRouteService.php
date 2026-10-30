<?php

namespace App\Domains\HRMS\Services;

use App\Domains\HRMS\Models\Attendance;
use App\Domains\HRMS\Models\AttendanceLocationLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class AttendanceRouteService
{
    /**
     * Consolidate 1-minute location logs into an optimized polyline and snapshot,
     * calculate total distance, and purge raw logs from database.
     *
     * @param Attendance|int $attendance
     * @return array
     */
    public function consolidateShiftRoute(Attendance|int $attendance): array
    {
        if (is_numeric($attendance)) {
            $attendance = Attendance::findOrFail($attendance);
        }

        // 1. Guard against overwriting already consolidated route if raw logs have already been purged
        $rawLogs = AttendanceLocationLog::where('attendance_id', $attendance->id)
            ->orderBy('created_at', 'asc')
            ->get();

        if ($rawLogs->isEmpty() && !empty($attendance->route_snapshot_url) && !empty($attendance->route_polyline)) {
            return [
                'success'              => true,
                'already_consolidated' => true,
                'attendance_id'        => $attendance->id,
                'total_distance_km'    => (float) $attendance->total_distance_km,
                'route_polyline'       => $attendance->route_polyline,
                'route_snapshot_url'   => $attendance->route_snapshot_url,
                'purged_rows'          => 0,
                'points_count'         => $attendance->route_summary['retained_points_count'] ?? 0,
                'message'              => 'Route has already been consolidated and raw logs were safely purged.',
            ];
        }

        $points = [];

        // Prepend check-in coordinates if available
        if (!is_null($attendance->check_in_latitude) && !is_null($attendance->check_in_longitude)) {
            $points[] = [
                'lat'       => (float) $attendance->check_in_latitude,
                'lng'       => (float) $attendance->check_in_longitude,
                'time'      => $attendance->check_in ? $attendance->check_in->format('h:i A') : '',
                'timestamp' => $attendance->check_in ? $attendance->check_in->timestamp : null,
            ];
        }

        // Add periodic breadcrumb logs
        foreach ($rawLogs as $log) {
            $lat = (float) $log->latitude;
            $lng = (float) $log->longitude;
            $timeStr = $log->created_at ? $log->created_at->format('h:i A') : '';

            // Avoid adding identical consecutive coordinates
            $last = end($points);
            if ($last && abs($last['lat'] - $lat) < 0.00005 && abs($last['lng'] - $lng) < 0.00005) {
                continue;
            }

            $points[] = [
                'lat'       => $lat,
                'lng'       => $lng,
                'time'      => $timeStr,
                'timestamp' => $log->created_at ? $log->created_at->timestamp : null,
            ];
        }

        // Append check-out coordinates if available
        if (!is_null($attendance->check_out_latitude) && !is_null($attendance->check_out_longitude)) {
            $outLat = (float) $attendance->check_out_latitude;
            $outLng = (float) $attendance->check_out_longitude;
            $last = end($points);
            if (!$last || abs($last['lat'] - $outLat) >= 0.00005 || abs($last['lng'] - $outLng) >= 0.00005) {
                $points[] = [
                    'lat'       => $outLat,
                    'lng'       => $outLng,
                    'time'      => $attendance->check_out ? $attendance->check_out->format('h:i A') : '',
                    'timestamp' => $attendance->check_out ? $attendance->check_out->timestamp : null,
                ];
            }
        }

        // If no coordinates were recorded at all
        if (empty($points)) {
            return [
                'success'           => false,
                'message'           => 'No location points recorded for this attendance session.',
                'total_distance_km' => 0.00,
                'purged_rows'       => 0,
            ];
        }

        // 2. Calculate total distance traveled (Haversine formula with stationary jitter suppression)
        $totalDistanceKm = 0.0;
        $prevPoint = null;
        $minMovementKm = 0.01; // 10 meters threshold to filter stationary GPS jitter

        foreach ($points as $p) {
            if ($prevPoint !== null) {
                $segmentDistance = $this->calculateDistance($prevPoint['lat'], $prevPoint['lng'], $p['lat'], $p['lng']);
                // Filter GPS drift: dynamically ignore jumps under configured threshold (default 10 meters)
                if ($segmentDistance >= $minMovementKm) {
                    $totalDistanceKm += $segmentDistance;
                }
            }
            $prevPoint = $p;
        }

        // Detect tracking gaps (Pillar 3: Audit trail for GPS turned off mid-shift)
        $trackingConfig = $attendance->tracking_config ?? [];
        $expectedIntervalMinutes = (int) ($trackingConfig['interval_minutes'] ?? 15);
        $gapThresholdSeconds = max(20 * 60, (int) round(($expectedIntervalMinutes * 60) * 1.5));

        $trackingGaps = [];
        $totalUntrackedSeconds = 0;
        $pointCount = count($points);
        $approvedBreaks = $attendance->breaks ?? collect();

        for ($i = 0; $i < $pointCount - 1; $i++) {
            $t1 = $points[$i]['timestamp'] ?? null;
            $t2 = $points[$i + 1]['timestamp'] ?? null;

            if ($t1 && $t2 && ($t2 - $t1) > $gapThresholdSeconds) {
                $gapSec = $t2 - $t1;
                $gapMin = (int) round($gapSec / 60);

                // Check if this gap coincides with an authorized break (Enhancement 1)
                $isApprovedBreak = false;
                foreach ($approvedBreaks as $b) {
                    $bIn = $b->break_in ? $b->break_in->timestamp : null;
                    $bOut = $b->break_out ? $b->break_out->timestamp : null;
                    if ($bIn && $bOut) {
                        $overlapStart = max($t1, $bIn);
                        $overlapEnd = min($t2, $bOut);
                        if ($overlapEnd > $overlapStart && ($overlapEnd - $overlapStart) >= ($gapSec * 0.6)) {
                            $isApprovedBreak = true;
                            break;
                        }
                    }
                }

                if (!$isApprovedBreak) {
                    $totalUntrackedSeconds += $gapSec;
                }

                $trackingGaps[] = [
                    'from_time'         => $points[$i]['time'] ?? '',
                    'to_time'           => $points[$i + 1]['time'] ?? '',
                    'duration_min'      => $gapMin,
                    'is_approved_break' => $isApprovedBreak,
                    'from_index'        => $i,
                    'to_index'          => $i + 1,
                    'from_lat'          => $points[$i]['lat'],
                    'from_lng'          => $points[$i]['lng'],
                    'to_lat'            => $points[$i + 1]['lat'],
                    'to_lng'            => $points[$i + 1]['lng'],
                ];
            }
        }

        $unapprovedGaps = array_filter($trackingGaps, fn($g) => empty($g['is_approved_break']));

        // 3. Compress points into Google Polyline String (~1 KB text)
        $polylineString = $this->encodePolyline($points);

        // 4. Update attendances record with consolidated summary
        $totalPointsCount = $rawLogs->count();
        $startPoint = $points[0] ?? null;
        $endPoint = end($points) ?: null;

        $updateFields = [
            'total_distance_km'  => round($totalDistanceKm, 2),
            'route_polyline'     => $polylineString,
            'route_summary'      => [
                'total_raw_points'        => $totalPointsCount,
                'retained_points_count'   => count($points),
                'start_point'             => $startPoint,
                'end_point'               => $endPoint,
                'waypoint_times'          => array_column($points, 'time'),
                'waypoint_timestamps'     => array_column($points, 'timestamp'),
                'has_tracking_gaps'       => count($unapprovedGaps) > 0,
                'tracking_gaps'           => $trackingGaps,
                'total_untracked_minutes' => (int) round($totalUntrackedSeconds / 60),
                'consolidated_at'         => Carbon::now()->toIso8601String(),
            ],
        ];

        $attendance->update($updateFields);

        // 5. PURGE raw periodic logs once polyline has been consolidated
        $purgedCount = 0;
        if (!empty($polylineString)) {
            $purgedCount = AttendanceLocationLog::where('attendance_id', $attendance->id)->delete();
        }

        return [
            'success'            => true,
            'snapshot_saved'     => !empty($attendance->route_snapshot_url),
            'attendance_id'      => $attendance->id,
            'total_distance_km'  => round($totalDistanceKm, 2),
            'route_polyline'     => $polylineString,
            'route_snapshot_url' => $attendance->route_snapshot_url,
            'purged_rows'        => $purgedCount,
            'points_count'       => count($points),
        ];
    }

    /**
     * Regenerate route snapshot for an existing attendance record.
     */
    public function regenerateRouteSnapshot(Attendance $attendance): ?string
    {
        if (empty($attendance->route_polyline)) {
            return null;
        }

        $summary = is_array($attendance->route_summary) ? $attendance->route_summary : (json_decode($attendance->route_summary, true) ?? []);
        $timestamps = $summary['waypoint_times'] ?? [];
        $points = $this->decodePolyline($attendance->route_polyline, $timestamps);

        if (empty($points)) {
            return null;
        }

        $trackingGaps = $summary['tracking_gaps'] ?? [];
        $distanceKm = (float) ($attendance->total_distance_km ?? 0);

        $newSnapshot = $this->generateAndSaveRouteSnapshot($attendance->id, $attendance->route_polyline, $points, $distanceKm, $trackingGaps);

        if ($newSnapshot) {
            $attendance->update(['route_snapshot_url' => $newSnapshot]);
        }

        return $newSnapshot;
    }

    /**
     * Generate route snapshot image and store into configured disk.
     * Tries OpenStreetMap / CARTO with OPENSTREETMAP_API_KEY first; otherwise falls back to Google Static Maps or SVG.
     */
    public function generateAndSaveRouteSnapshot(int $attendanceId, string $polyline, array $points, float $distanceKm, array $trackingGaps = []): ?string
    {
        $storageDisk = 'public';
        $storageDir = 'attendance/routes';

        // Check/create directory on configured disk
        if (!Storage::disk($storageDisk)->exists($storageDir)) {
            Storage::disk($storageDisk)->makeDirectory($storageDir);
        }

        $osmApiKey = config('services.openstreetmap.key') ?? env('OPENSTREETMAP_API_KEY');

        // Option A (User's Preference): Generate real OpenStreetMap PNG snapshot using stitched map tiles & API key
        try {
            $pngContent = $this->generateOsmMapSnapshot($points, $distanceKm, $osmApiKey, $trackingGaps);
            if (!empty($pngContent)) {
                $filename = "{$storageDir}/route_{$attendanceId}_" . time() . ".png";
                Storage::disk($storageDisk)->put($filename, $pngContent);
                return Storage::disk($storageDisk)->url($filename);
            }
        } catch (\Throwable $e) {
            Log::warning("OpenStreetMap snapshot generation failed for Attendance #{$attendanceId}: " . $e->getMessage() . ". Trying fallback.");
        }

        // Option B: Try Google Static Maps API if key is configured
        $googleKey = config('services.google_maps.key');
        if (!empty($googleKey)) {
            try {
                $start = $points[0];
                $end = end($points);

                $queryParams = [
                    'size'     => '640x360',
                    'scale'    => '2',
                    'maptype'  => 'roadmap',
                    'path'     => "color:0x3b82f6ff|weight:4|enc:{$polyline}",
                    'key'      => $googleKey,
                ];

                $url = "https://maps.googleapis.com/maps/api/staticmap?" . http_build_query($queryParams)
                    . "&markers=" . urlencode("color:green|label:S|{$start['lat']},{$start['lng']}")
                    . "&markers=" . urlencode("color:red|label:E|{$end['lat']},{$end['lng']}");

                $response = Http::timeout(6)->get($url);
                if ($response->successful() && str_contains($response->header('Content-Type') ?? '', 'image')) {
                    $filename = "{$storageDir}/route_{$attendanceId}_" . time() . ".png";
                    Storage::disk($storageDisk)->put($filename, $response->body());
                    return Storage::disk($storageDisk)->url($filename);
                }
            } catch (\Throwable $e) {
                Log::warning("Google Static Map failed for Attendance #{$attendanceId}: " . $e->getMessage() . ". Using SVG fallback.");
            }
        }

        // Option C: Generate an SVG vector route snapshot (100% offline, free, zero external latency)
        try {
            $svgContent = $this->generateSvgRouteSnapshot($attendanceId, $points, $distanceKm);
            $filename = "{$storageDir}/route_{$attendanceId}_" . time() . ".svg";
            Storage::disk($storageDisk)->put($filename, $svgContent);
            return Storage::disk($storageDisk)->url($filename);
        } catch (\Throwable $e) {
            Log::error("SVG snapshot generation failed for Attendance #{$attendanceId}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate a real map route snapshot using OpenStreetMap raster tiles stitched with PHP GD.
     */
    public function generateOsmMapSnapshot(array $points, float $distanceKm, ?string $apiKey = null, array $trackingGaps = []): ?string
    {
        if (empty($points) || !extension_loaded('gd') || !function_exists('imagecreatetruecolor')) {
            return null;
        }

        $width = 640;
        $height = 360;

        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');
        $minLat = (float) min($lats);
        $maxLat = (float) max($lats);
        $minLng = (float) min($lngs);
        $maxLng = (float) max($lngs);

        $centerLat = ($minLat + $maxLat) / 2.0;
        $centerLng = ($minLng + $maxLng) / 2.0;

        $zoom = $this->calculateZoom($minLat, $maxLat, $minLng, $maxLng, $width, $height);

        [$centerWorldX, $centerWorldY] = $this->latLngToWorldPixel($centerLat, $centerLng, $zoom);

        $originWorldX = $centerWorldX - ($width / 2.0);
        $originWorldY = $centerWorldY - ($height / 2.0);

        $minTileX = (int) floor($originWorldX / 256.0);
        $maxTileX = (int) floor(($originWorldX + $width) / 256.0);
        $minTileY = (int) floor($originWorldY / 256.0);
        $maxTileY = (int) floor(($originWorldY + $height) / 256.0);

        $canvas = imagecreatetruecolor($width, $height);
        $bgColor = imagecolorallocate($canvas, 241, 245, 249);
        imagefill($canvas, 0, 0, $bgColor);

        // Fetch tiles and stitch onto canvas (Official OpenStreetMap - 100% watermark-free, real streets)
        $tilesLoaded = 0;
        for ($tx = $minTileX; $tx <= $maxTileX; $tx++) {
            for ($ty = $minTileY; $ty <= $maxTileY; $ty++) {
                $tileData = null;

                $tileEndpoints = [
                    "https://tile.openstreetmap.org/{$zoom}/{$tx}/{$ty}.png",
                    "https://a.tile.openstreetmap.fr/osmfr/{$zoom}/{$tx}/{$ty}.png",
                ];

                foreach ($tileEndpoints as $endpoint) {
                    try {
                        $res = Http::timeout(3)
                            ->withHeaders([
                                'User-Agent' => 'WM-Product-SaaS/1.0 (Location Route Tracker; support@saas-erp.com)',
                                'Referer'    => url('/'),
                                'Accept'     => 'image/png,image/*',
                            ])
                            ->get($endpoint);

                        if ($res->successful() && str_contains($res->header('Content-Type') ?? '', 'image')) {
                            $tileData = $res->body();
                            break;
                        }
                    } catch (\Throwable $e) {
                        continue;
                    }
                }

                if (!empty($tileData)) {
                    $tileImg = @imagecreatefromstring($tileData);
                    if ($tileImg) {
                        $destX = (int) round($tx * 256.0 - $originWorldX);
                        $destY = (int) round($ty * 256.0 - $originWorldY);
                        imagecopy($canvas, $tileImg, $destX, $destY, 0, 0, 256, 256);
                        imagedestroy($tileImg);
                        $tilesLoaded++;
                    }
                }
            }
        }

        // If no tiles could be loaded, return null to fallback to SVG
        if ($tilesLoaded === 0) {
            imagedestroy($canvas);
            return null;
        }

        // Convert points to canvas pixels
        $pixelPoints = [];
        foreach ($points as $p) {
            [$wx, $wy] = $this->latLngToWorldPixel((float)$p['lat'], (float)$p['lng'], $zoom);
            $pixelPoints[] = [
                'x' => (int) round($wx - $originWorldX),
                'y' => (int) round($wy - $originWorldY),
            ];
        }

        // Gap indices lookup (Pillar 1 & 3: Telemetry gaps)
        $gapIndices = [];
        $totalGapMin = 0;
        foreach ($trackingGaps as $gap) {
            if (isset($gap['from_index'])) {
                $gapIndices[(int)$gap['from_index']] = $gap;
            }
            $totalGapMin += (int)($gap['duration_min'] ?? 0);
        }
        $hasGaps = !empty($gapIndices);

        // Colors
        $routeColor = imagecolorallocate($canvas, 79, 70, 229);         // Indigo #4f46e5
        $routeShadow = imagecolorallocatealpha($canvas, 99, 102, 241, 75); // Path glow
        $gapColor = imagecolorallocate($canvas, 239, 68, 68);           // Red #ef4444 for GPS disabled
        $gapGlow = imagecolorallocatealpha($canvas, 239, 68, 68, 80);
        $startColor = imagecolorallocate($canvas, 16, 185, 129);        // Green #10b981
        $endColor = imagecolorallocate($canvas, 239, 68, 68);           // Red #ef4444
        $waypointColor = imagecolorallocate($canvas, 59, 130, 246);     // Blue #3b82f6
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $textDark = imagecolorallocate($canvas, 15, 23, 42);

        // Draw polyline route connecting all points
        $count = count($pixelPoints);
        if ($count >= 2) {
            for ($i = 0; $i < $count - 1; $i++) {
                $p1 = $pixelPoints[$i];
                $p2 = $pixelPoints[$i + 1];

                if (isset($gapIndices[$i])) {
                    // Pillar 1: Draw Telemetry Gap as Red Dashed Line on snapshot
                    $this->drawDashedLine($canvas, $p1['x'], $p1['y'], $p2['x'], $p2['y'], $gapGlow, 8, 8, 6);
                    $this->drawDashedLine($canvas, $p1['x'], $p1['y'], $p2['x'], $p2['y'], $gapColor, 4, 8, 6);
                } else {
                    // Route glow
                    $this->drawThickLine($canvas, $p1['x'], $p1['y'], $p2['x'], $p2['y'], $routeShadow, 8);
                    // Main route line
                    $this->drawThickLine($canvas, $p1['x'], $p1['y'], $p2['x'], $p2['y'], $routeColor, 4);
                }
            }
        }

        // Draw intermediate waypoint dots if > 2 points
        if ($count > 2) {
            for ($i = 1; $i < $count - 1; $i++) {
                $pt = $pixelPoints[$i];
                imagefilledellipse($canvas, $pt['x'], $pt['y'], 8, 8, $white);
                imagefilledellipse($canvas, $pt['x'], $pt['y'], 6, 6, $waypointColor);
            }
        }

        // Check if start and end are in virtually identical spot (< 18px apart)
        $start = $pixelPoints[0];
        $end = end($pixelPoints);
        $isSameSpot = (abs($start['x'] - $end['x']) < 18 && abs($start['y'] - $end['y']) < 18);

        $startX = $isSameSpot ? $start['x'] - 11 : $start['x'];
        $startY = $start['y'];
        $endX = $isSameSpot ? $end['x'] + 11 : $end['x'];
        $endY = $end['y'];

        // If same spot, draw subtle connector between IN and OUT
        if ($isSameSpot && $count <= 2) {
            $this->drawThickLine($canvas, $startX, $startY, $endX, $endY, $routeColor, 3);
        }

        // Draw Start Marker (Green circle with white border & inner dot)
        imagefilledellipse($canvas, $startX, $startY, 22, 22, $white);
        imagefilledellipse($canvas, $startX, $startY, 18, 18, $startColor);
        imagefilledellipse($canvas, $startX, $startY, 6, 6, $white);

        // Draw End Marker (Red circle with white border & inner dot)
        imagefilledellipse($canvas, $endX, $endY, 22, 22, $white);
        imagefilledellipse($canvas, $endX, $endY, 18, 18, $endColor);
        imagefilledellipse($canvas, $endX, $endY, 6, 6, $white);

        // Draw badges
        $distLabel = number_format($distanceKm, 2) . ' KM';
        $ptsLabel = $count . ' Waypoints';

        $this->drawPillBadge($canvas, 16, 14, 150, 28, $distLabel, 'dist', $white, $textDark);
        $this->drawPillBadge($canvas, $width - 130, 14, 114, 28, $ptsLabel, 'pts', $white, $textDark);

        if ($hasGaps) {
            $gapBg = imagecolorallocate($canvas, 254, 242, 242);
            $gapFg = imagecolorallocate($canvas, 185, 28, 28);
            $gapLabel = "GPS Gap: " . $totalGapMin . "m";
            $this->drawPillBadge($canvas, 174, 14, 130, 28, $gapLabel, 'gap', $gapBg, $gapFg);
        }

        // Attribution: © OpenStreetMap contributors
        $attrColor = imagecolorallocatealpha($canvas, 71, 85, 105, 40);
        $attrBg = imagecolorallocatealpha($canvas, 255, 255, 255, 30);
        imagefilledrectangle($canvas, $width - 170, $height - 18, $width - 4, $height - 4, $attrBg);
        imagestring($canvas, 1, $width - 165, $height - 16, 'OpenStreetMap contributors', $attrColor);

        ob_start();
        imagepng($canvas, null, 7);
        $pngData = ob_get_clean();
        imagedestroy($canvas);

        return $pngData;
    }

    protected function drawDashedLine($canvas, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness, int $dashLength = 8, int $spaceLength = 6): void
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $dist = sqrt($dx * $dx + $dy * $dy);
        if ($dist <= 0) return;

        $step = ($dashLength + $spaceLength);
        $steps = (int) ceil($dist / $step);

        for ($i = 0; $i < $steps; $i++) {
            $startRatio = ($i * $step) / $dist;
            $endRatio = min(1.0, ($i * $step + $dashLength) / $dist);

            if ($startRatio >= 1.0) break;

            $sx = (int) round($x1 + $dx * $startRatio);
            $sy = (int) round($y1 + $dy * $startRatio);
            $ex = (int) round($x1 + $dx * $endRatio);
            $ey = (int) round($y1 + $dy * $endRatio);

            $this->drawThickLine($canvas, $sx, $sy, $ex, $ey, $color, $thickness);
        }
    }

    protected function drawThickLine($canvas, int $x1, int $y1, int $x2, int $y2, int $color, int $thickness): void
    {
        if ($thickness <= 1) {
            imageline($canvas, $x1, $y1, $x2, $y2, $color);
            return;
        }

        $half = (int) floor($thickness / 2);
        for ($dx = -$half; $dx <= $half; $dx++) {
            for ($dy = -$half; $dy <= $half; $dy++) {
                if ($dx * $dx + $dy * $dy <= $half * $half + 1) {
                    imageline($canvas, $x1 + $dx, $y1 + $dy, $x2 + $dx, $y2 + $dy, $color);
                }
            }
        }
    }

    protected function drawPillBadge($canvas, int $x, int $y, int $w, int $h, string $text, string $type, int $bg, int $fg): void
    {
        $shadow = imagecolorallocatealpha($canvas, 0, 0, 0, 110);
        imagefilledellipse($canvas, $x + (int)($h / 2), $y + (int)($h / 2) + 1, $h, $h, $shadow);
        imagefilledellipse($canvas, $x + $w - (int)($h / 2), $y + (int)($h / 2) + 1, $h, $h, $shadow);
        imagefilledrectangle($canvas, $x + (int)($h / 2), $y + 1, $x + $w - (int)($h / 2), $y + $h + 1, $shadow);

        imagefilledellipse($canvas, $x + (int)($h / 2), $y + (int)($h / 2), $h, $h, $bg);
        imagefilledellipse($canvas, $x + $w - (int)($h / 2), $y + (int)($h / 2), $h, $h, $bg);
        imagefilledrectangle($canvas, $x + (int)($h / 2), $y, $x + $w - (int)($h / 2), $y + $h, $bg);

        if ($type === 'dist') {
            $blue = imagecolorallocate($canvas, 59, 130, 246);
            imagefilledellipse($canvas, $x + 14, $y + (int)($h / 2), 8, 8, $blue);
            imagestring($canvas, 2, $x + 24, $y + 7, "Dist: " . $text, $fg);
        } else {
            imagestring($canvas, 2, $x + 16, $y + 7, $text, $fg);
        }
    }

    protected function calculateZoom(float $minLat, float $maxLat, float $minLng, float $maxLng, int $width, int $height): int
    {
        $latDiff = abs($maxLat - $minLat);
        $lngDiff = abs($maxLng - $minLng);

        // Minimal distance / stationary
        if ($latDiff < 0.0005 && $lngDiff < 0.0005) {
            return 16;
        }

        $latFraction = ($latDiff * 1.35) / 180.0;
        $lngFraction = ($lngDiff * 1.35) / 360.0;

        $zoomY = $latFraction > 0 ? floor(log($height / 256.0 / $latFraction) / log(2)) : 16;
        $zoomX = $lngFraction > 0 ? floor(log($width / 256.0 / $lngFraction) / log(2)) : 16;

        $zoom = (int) min($zoomX, $zoomY);
        return max(2, min(17, $zoom));
    }

    protected function latLngToWorldPixel(float $lat, float $lng, int $zoom): array
    {
        $n = pow(2, $zoom);
        $x = ($lng + 180.0) / 360.0 * 256.0 * $n;
        $latRad = deg2rad($lat);
        $latRad = max(-1.4844, min(1.4844, $latRad));
        $y = (1.0 - log(tan($latRad) + (1.0 / cos($latRad))) / M_PI) / 2.0 * 256.0 * $n;

        return [$x, $y];
    }


    /**
     * Generate a responsive, modern SVG route graphic as an offline fallback.
     */
    public function generateSvgRouteSnapshot(int $attendanceId, array $points, float $distanceKm): string
    {
        $width = 640;
        $height = 360;
        $padding = 45;

        if (count($points) < 1) {
            return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 {$width} {$height}'><rect width='100%' height='100%' fill='#f8fafc'/><text x='50%' y='50%' fill='#64748b' text-anchor='middle'>No Route Points</text></svg>";
        }

        // Determine bounding box
        $lats = array_column($points, 'lat');
        $lngs = array_column($points, 'lng');

        $minLat = min($lats);
        $maxLat = max($lats);
        $minLng = min($lngs);
        $maxLng = max($lngs);

        $latSpan = max($maxLat - $minLat, 0.0001);
        $lngSpan = max($maxLng - $minLng, 0.0001);

        $usableW = $width - ($padding * 2);
        $usableH = $height - ($padding * 2);

        // Map coords to SVG canvas (Mercator inverted Y)
        $svgPoints = [];
        foreach ($points as $p) {
            $x = $padding + (($p['lng'] - $minLng) / $lngSpan) * $usableW;
            $y = ($height - $padding) - (($p['lat'] - $minLat) / $latSpan) * $usableH;
            $svgPoints[] = round($x, 1) . ',' . round($y, 1);
        }

        $pathD = 'M ' . implode(' L ', $svgPoints);
        $startCoord = explode(',', $svgPoints[0]);
        $endCoord = explode(',', end($svgPoints));

        $distanceLabel = number_format($distanceKm, 2) . ' KM';
        $pointsCount = count($points);

        return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$width} {$height}" width="100%" height="100%">
    <defs>
        <linearGradient id="routeGradient" x1="0%" y1="0%" x2="100%" y2="100%">
            <stop offset="0%" stop-color="#10b981" />
            <stop offset="50%" stop-color="#4f46e5" />
            <stop offset="100%" stop-color="#ef4444" />
        </linearGradient>
        <pattern id="mapGrid" width="40" height="40" patternUnits="userSpaceOnUse">
            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#e2e8f0" stroke-width="0.8"/>
        </pattern>
        <filter id="shadow" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="2" stdDeviation="3" flood-color="#0f172a" flood-opacity="0.15"/>
        </filter>
    </defs>

    <!-- Map Background -->
    <rect width="{$width}" height="{$height}" fill="#f1f5f9" rx="12"/>
    <rect width="{$width}" height="{$height}" fill="url(#mapGrid)" rx="12"/>

    <!-- Map Geography Simulation lines -->
    <path d="M 0 180 Q 200 120 400 200 T {$width} 160" fill="none" stroke="#cbd5e1" stroke-width="6" opacity="0.6"/>
    <path d="M 120 0 Q 180 180 240 {$height}" fill="none" stroke="#e2e8f0" stroke-width="4" opacity="0.6"/>
    <path d="M 450 0 Q 420 180 500 {$height}" fill="none" stroke="#e2e8f0" stroke-width="4" opacity="0.6"/>

    <!-- Route Line Glow -->
    <path d="{$pathD}" fill="none" stroke="#6366f1" stroke-width="8" stroke-linecap="round" stroke-linejoin="round" opacity="0.25"/>

    <!-- Main Route Path -->
    <path d="{$pathD}" fill="none" stroke="url(#routeGradient)" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>

    <!-- Start Marker (Green) -->
    <circle cx="{$startCoord[0]}" cy="{$startCoord[1]}" r="9" fill="#10b981" stroke="#ffffff" stroke-width="2.5" filter="url(#shadow)"/>
    <circle cx="{$startCoord[0]}" cy="{$startCoord[1]}" r="3.5" fill="#ffffff"/>

    <!-- End Marker (Red) -->
    <circle cx="{$endCoord[0]}" cy="{$endCoord[1]}" r="9" fill="#ef4444" stroke="#ffffff" stroke-width="2.5" filter="url(#shadow)"/>
    <circle cx="{$endCoord[0]}" cy="{$endCoord[1]}" r="3.5" fill="#ffffff"/>

    <!-- Top Badge: Distance & Points -->
    <g transform="translate(18, 20)">
        <rect width="180" height="34" rx="17" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" filter="url(#shadow)"/>
        <circle cx="16" cy="17" r="5" fill="#4f46e5"/>
        <text x="30" y="21" fill="#0f172a" font-family="system-ui, sans-serif" font-size="12" font-weight="700">Distance: {$distanceLabel}</text>
    </g>

    <g transform="translate({$width} - 150, 20)">
        <rect width="132" height="34" rx="17" fill="#ffffff" stroke="#e2e8f0" stroke-width="1" filter="url(#shadow)"/>
        <text x="66" y="21" fill="#475569" font-family="system-ui, sans-serif" font-size="11" font-weight="600" text-anchor="middle">{$pointsCount} Waypoints</text>
    </g>

    <!-- Legend (Bottom) -->
    <g transform="translate(18, {$height} - 28)">
        <circle cx="8" cy="8" r="4.5" fill="#10b981"/>
        <text x="18" y="11" fill="#475569" font-family="system-ui, sans-serif" font-size="10" font-weight="600">Start Point</text>

        <circle cx="95" cy="8" r="4.5" fill="#ef4444"/>
        <text x="105" y="11" fill="#475569" font-family="system-ui, sans-serif" font-size="10" font-weight="600">End Point</text>
    </g>
</svg>
SVG;
    }

    /**
     * Encode an array of [['lat' => ..., 'lng' => ...]] to Google Encoded Polyline algorithm.
     */
    public function encodePolyline(array $points): string
    {
        $encoded = '';
        $prevLat = 0;
        $prevLng = 0;

        foreach ($points as $p) {
            $lat = (int) round(((float)$p['lat']) * 1e5);
            $lng = (int) round(((float)$p['lng']) * 1e5);

            $dLat = $lat - $prevLat;
            $dLng = $lng - $prevLng;

            $prevLat = $lat;
            $prevLng = $lng;

            $encoded .= $this->encodeChunk($dLat) . $this->encodeChunk($dLng);
        }

        return $encoded;
    }

    /**
     * Decode a Google Encoded Polyline string back into an array of [['lat' => ..., 'lng' => ..., 'time' => ...]].
     */
    public function decodePolyline(string $encoded, array $timestamps = []): array
    {
        $points = [];
        $index = 0;
        $len = strlen($encoded);
        $lat = 0;
        $lng = 0;
        $pointIndex = 0;

        while ($index < $len) {
            // Latitude
            $b = 0;
            $shift = 0;
            $result = 0;
            do {
                if ($index >= $len) break;
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlat = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lat += $dlat;

            // Longitude
            $shift = 0;
            $result = 0;
            do {
                if ($index >= $len) break;
                $b = ord($encoded[$index++]) - 63;
                $result |= ($b & 0x1f) << $shift;
                $shift += 5;
            } while ($b >= 0x20);

            $dlng = (($result & 1) ? ~($result >> 1) : ($result >> 1));
            $lng += $dlng;

            $points[] = [
                'lat'  => round($lat * 1e-5, 6),
                'lng'  => round($lng * 1e-5, 6),
                'time' => $timestamps[$pointIndex] ?? '',
            ];
            $pointIndex++;
        }

        return $points;
    }

    /**
     * Purge old raw location logs for shifts that have already checked out.
     * Ensures any missing route snapshot/polyline is generated before purging!
     */
    public function pruneOldLogs(?int $olderThanHours = null): int
    {
        $hours = $olderThanHours ?: 24;
        $cutoff = Carbon::now()->subHours($hours);

        // 1. Recover and consolidate any completed attendances that still have raw logs but no snapshot/polyline
        $unconsolidatedAttendances = Attendance::whereNotNull('check_out')
            ->where('check_out', '<', $cutoff)
            ->where(function ($q) {
                $q->whereNull('route_snapshot_url')
                  ->orWhereNull('route_polyline');
            })
            ->whereHas('locationLogs')
            ->get();

        foreach ($unconsolidatedAttendances as $att) {
            try {
                $this->consolidateShiftRoute($att);
            } catch (\Throwable $e) {
                Log::warning("Auto-consolidation during prune failed for Attendance #{$att->id}: " . $e->getMessage());
            }
        }

        // 2. Only delete raw logs for completed shifts that have CONFIRMED saved route snapshot and polyline!
        return AttendanceLocationLog::whereHas('attendance', function ($q) use ($cutoff) {
                $q->whereNotNull('check_out')
                  ->where('check_out', '<', $cutoff)
                  ->whereNotNull('route_snapshot_url')
                  ->whereNotNull('route_polyline');
            })
            ->delete();
    }

    protected function encodeChunk(int $num): string
    {
        $num = $num < 0 ? ~($num << 1) : ($num << 1);
        $chunk = '';
        while ($num >= 0x20) {
            $chunk .= chr((0x20 | ($num & 0x1f)) + 63);
            $num >>= 5;
        }
        $chunk .= chr($num + 63);
        return $chunk;
    }

    protected function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
