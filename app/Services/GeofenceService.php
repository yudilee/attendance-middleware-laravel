<?php

namespace App\Services;

class GeofenceService
{
    /**
     * Calculate distance between two coordinates in meters using the Haversine formula.
     */
    public function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000; // in meters

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Check if a point (lat, lon) is inside a polygon using the Ray-Casting algorithm.
     * Expects array of [[lat, lon], [lat, lon], ...] or json string.
     */
    public function isPointInPolygon(float $lat, float $lon, $polygon): bool
    {
        if (is_string($polygon)) {
            $polygon = json_decode($polygon, true);
        }

        if (!is_array($polygon) || count($polygon) < 3) {
            return false;
        }

        $inside = false;
        $numPoints = count($polygon);
        $j = $numPoints - 1;

        for ($i = 0; $i < $numPoints; $i++) {
            $xi = (float)($polygon[$i][0] ?? $polygon[$i]['lat'] ?? 0);
            $yi = (float)($polygon[$i][1] ?? $polygon[$i]['lng'] ?? $polygon[$i]['lon'] ?? 0);
            $xj = (float)($polygon[$j][0] ?? $polygon[$j]['lat'] ?? 0);
            $yj = (float)($polygon[$j][1] ?? $polygon[$j]['lng'] ?? $polygon[$j]['lon'] ?? 0);

            $intersect = (($yi > $lon) !== ($yj > $lon)) &&
                         ($lat < ($xj - $xi) * ($lon - $yi) / ($yj - $yi + 1e-12) + $xi);

            if ($intersect) {
                $inside = !$inside;
            }

            $j = $i;
        }

        return $inside;
    }

    /**
     * Validate if coordinates fall within any authorized branch or checkpoint.
     *
     * @param float $lat
     * @param float $lon
     * @param iterable $branches
     * @return array ['valid' => bool, 'matched_branch' => ?Branch, 'matched_checkpoint' => ?BranchCheckpoint, 'distance' => float]
     */
    public function validateLocation(float $lat, float $lon, $branches): array
    {
        $minDistance = PHP_FLOAT_MAX;
        $matchedBranch = null;
        $matchedCheckpoint = null;

        foreach ($branches as $branch) {
            if (!$branch->is_active) {
                continue;
            }

            // 1. Check branch polygon geofence
            if ($branch->geofence_type === 'polygon' && !empty($branch->polygon_coordinates)) {
                if ($this->isPointInPolygon($lat, $lon, $branch->polygon_coordinates)) {
                    return [
                        'valid' => true,
                        'matched_branch' => $branch,
                        'matched_checkpoint' => null,
                        'distance' => 0.0,
                    ];
                }
            } else {
                // 2. Check branch circular geofence
                $dist = $this->calculateDistance($lat, $lon, $branch->latitude, $branch->longitude);
                if ($dist < $minDistance) {
                    $minDistance = $dist;
                }
                if ($dist <= $branch->radius_meters) {
                    return [
                        'valid' => true,
                        'matched_branch' => $branch,
                        'matched_checkpoint' => null,
                        'distance' => round($dist, 2),
                    ];
                }
            }

            // 3. Check branch checkpoints
            if ($branch->relationLoaded('checkpoints') || method_exists($branch, 'checkpoints')) {
                foreach ($branch->checkpoints as $cp) {
                    if (!$cp->is_active) {
                        continue;
                    }

                    if ($cp->geofence_type === 'polygon' && !empty($cp->polygon_coordinates)) {
                        if ($this->isPointInPolygon($lat, $lon, $cp->polygon_coordinates)) {
                            return [
                                'valid' => true,
                                'matched_branch' => $branch,
                                'matched_checkpoint' => $cp,
                                'distance' => 0.0,
                            ];
                        }
                    } else {
                        $cpDist = $this->calculateDistance($lat, $lon, $cp->latitude, $cp->longitude);
                        if ($cpDist <= $cp->radius_meters) {
                            return [
                                'valid' => true,
                                'matched_branch' => $branch,
                                'matched_checkpoint' => $cp,
                                'distance' => round($cpDist, 2),
                            ];
                        }
                    }
                }
            }
        }

        return [
            'valid' => false,
            'matched_branch' => null,
            'matched_checkpoint' => null,
            'distance' => $minDistance === PHP_FLOAT_MAX ? 0.0 : round($minDistance, 2),
        ];
    }
}
