<?php

return [
    'geocoding_provider' => env('GEOCODING_PROVIDER', 'photon'),
    'tile_url' => env('MAP_TILE_URL', 'https://tile.openstreetmap.org/{z}/{x}/{y}.png'),
    'tile_attribution' => env('MAP_TILE_ATTRIBUTION', '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'),
    'max_zoom' => (int) env('MAP_MAX_ZOOM', 19),
    'center' => [(float) env('MAP_DEFAULT_LATITUDE', 12.8797), (float) env('MAP_DEFAULT_LONGITUDE', 121.7740)],
    'zoom' => (int) env('MAP_DEFAULT_ZOOM', 6),
];
