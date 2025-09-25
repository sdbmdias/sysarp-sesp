<?php

class GPXProcessor
{
    private $filesData = [];

    public function load(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return false;
        }

        $gpx = simplexml_load_file($filePath);
        if ($gpx === false) {
            return false;
        }

        $gpx->registerXPathNamespace('g', 'http://www.topografix.com/GPX/1/1');
        $trackPoints = [];
        foreach ($gpx->xpath('//g:trk/g:trkseg/g:trkpt') as $pt) {
            $trackPoints[] = [
                'lat' => (float)$pt['lat'],
                'lon' => (float)$pt['lon'],
                'ele' => (float)$pt->ele,
                'time' => new DateTime((string)$pt->time, new DateTimeZone('UTC'))
            ];
        }

        if (count($trackPoints) > 1) {
            $this->processIndividualFile($trackPoints);
        }

        return true;
    }

    private function processIndividualFile(array $trackPoints)
    {
        $fileDistance = 0;
        $fileMaxAltitude = -INF;
        $firstPoint = $trackPoints[0];
        $takeoffElevation = $firstPoint['ele'];

        for ($i = 0; $i < count($trackPoints) - 1; $i++) {
            $p1 = $trackPoints[$i];
            $p2 = $trackPoints[$i + 1];
            $fileDistance += $this->haversineGreatCircleDistance($p1['lat'], $p1['lon'], $p2['lat'], $p2['lon']);
            
            $relativeHeight = $p1['ele'] - $takeoffElevation;
            if ($relativeHeight > $fileMaxAltitude) {
                $fileMaxAltitude = $relativeHeight;
            }
        }
        $lastPoint = end($trackPoints);
        $lastRelativeHeight = $lastPoint['ele'] - $takeoffElevation;
        if ($lastRelativeHeight > $fileMaxAltitude) {
            $fileMaxAltitude = $lastRelativeHeight;
        }

        $fileDuration = $lastPoint['time']->getTimestamp() - $firstPoint['time']->getTimestamp();
        $brasiliaTimezone = new DateTimeZone('America/Sao_Paulo');
        
        $this->filesData[] = [
            'tempo_voo' => $fileDuration,
            'distancia_percorrida' => $fileDistance,
            'altura_maxima' => $fileMaxAltitude,
            'data_decolagem' => (clone $firstPoint['time'])->setTimezone($brasiliaTimezone),
            'data_pouso' => (clone $lastPoint['time'])->setTimezone($brasiliaTimezone),
            'trackPoints' => $trackPoints // Retorna todos os pontos do trajeto
        ];
    }

    public function getIndividualFileData(): array
    {
        return $this->filesData;
    }
    
    public function getAggregatedData(): ?array
    {
        if (empty($this->filesData)) {
            return null;
        }

        $totalDistance = 0;
        $totalDuration = 0;
        $overallMaxAltitude = -INF;
        $firstTakeoff = null;
        $lastLanding = null;

        foreach ($this->filesData as $file) {
            $totalDistance += $file['distancia_percorrida'];
            $totalDuration += $file['tempo_voo'];
            if ($file['altura_maxima'] > $overallMaxAltitude) {
                $overallMaxAltitude = $file['altura_maxima'];
            }
            if ($firstTakeoff === null || $file['data_decolagem'] < $firstTakeoff) {
                $firstTakeoff = $file['data_decolagem'];
            }
            if ($lastLanding === null || $file['data_pouso'] > $lastLanding) {
                $lastLanding = $file['data_pouso'];
            }
        }

        return [
            'altitude_maxima' => $overallMaxAltitude,
            'total_distancia_percorrida' => $totalDistance,
            'total_tempo_voo' => $totalDuration,
            'data_primeira_decolagem' => $firstTakeoff->format('Y-m-d H:i:s'),
            'data_ultimo_pouso' => $lastLanding->format('Y-m-d H:i:s')
        ];
    }

    private function haversineGreatCircleDistance($latitudeFrom, $longitudeFrom, $latitudeTo, $longitudeTo): float
    {
        $earthRadius = 6371000;
        $latFrom = deg2rad($latitudeFrom);
        $lonFrom = deg2rad($longitudeFrom);
        $latTo = deg2rad($latitudeTo);
        $lonTo = deg2rad($longitudeTo);
        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;
        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lonDelta / 2), 2)));
        return $angle * $earthRadius;
    }
}