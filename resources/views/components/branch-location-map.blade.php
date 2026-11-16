@props(['branch', 'readonly' => false])
@php
    $mapConfig = [
        'latitude' => $readonly ? $branch->latitude : old('latitude', $branch->latitude),
        'longitude' => $readonly ? $branch->longitude : old('longitude', $branch->longitude),
        'radius' => $readonly ? $branch->geofence_radius_meters : old('geofence_radius_meters', $branch->geofence_radius_meters),
        'readonly' => $readonly,
        'editing' => $branch->exists,
        'searchEnabled' => !$readonly && app(\App\Services\GeocodingService::class)->configured(),
        'searchUrl' => $readonly ? null : route('hr.branches.location-search'),
        'tileUrl' => config('maps.tile_url'),
        'attribution' => config('maps.tile_attribution'),
        'maxZoom' => config('maps.max_zoom'),
        'center' => config('maps.center'),
        'zoom' => config('maps.zoom'),
        'minRadius' => config('attendance.geofence_min_radius_meters'),
        'maxRadius' => config('attendance.geofence_max_radius_meters'),
    ];
@endphp
<section class="branch-location space-y-5" x-data="branchLocationMap" data-map-config="{{ json_encode($mapConfig) }}" aria-label="Branch Location">
    <div class="space-y-2">
        <h2 class="font-semibold text-slate-900">Branch Location &amp; Geofencing</h2>
        @if(!$readonly)
            <p class="small muted">Click on the map to set the branch location, or drag the marker to adjust its position. Enter the geofence radius in meters to preview the attendance boundary.</p>
        @endif
    </div>
    <div class="grid grid-cols-1 items-stretch gap-6 xl:grid-cols-[minmax(0,7fr)_minmax(280px,3fr)]">
        <div x-ref="map" class="branch-map h-[360px] min-w-0 sm:h-[400px] xl:h-full xl:min-h-[540px] rounded-xl border border-slate-200" :class="{ 'map-pan-enabled': panning }" role="region" aria-label="Branch location map" tabindex="0"></div>
        <aside class="branch-location-details min-w-0 space-y-4 rounded-xl border border-slate-200 bg-white p-5" aria-label="Location Details">
            <div class="space-y-2"><h3 class="font-semibold text-slate-900">Location Details</h3><p class="small muted">{{ $readonly ? 'Saved branch location and attendance radius.' : 'Select the branch location on the map and configure the allowed attendance radius.' }}</p></div>
            @if(!$readonly)
                <div class="space-y-3">
                    <label for="branch-location-search" class="field-label">Search Location</label>
                    <div class="flex items-stretch gap-2">
                    <input id="branch-location-search" class="field min-w-0 flex-1" type="search" maxlength="200" x-model="searchQuery" x-bind:disabled="searchBusy" x-on:keydown.enter.prevent="searchLocation()" placeholder="Search office, street, barangay, or city..." aria-describedby="location-search-configuration location-search-feedback">
                    <x-button type="button" class="h-[46px] shrink-0 px-3" x-on:click="searchLocation()" x-bind:disabled="!searchEnabled || searchBusy"><span x-text="searchBusy ? 'Searching…' : 'Search'">Search</span></x-button>
                    </div>
                    <p id="location-search-configuration" class="small muted" x-show="!searchEnabled">Location search is not configured. Contact the system administrator.</p>
                    <p id="location-search-feedback" class="small muted" role="status" x-text="searchMessage"></p>
                    <ul class="search-results space-y-2" x-show="searchResults.length" aria-label="Location search results" x-bind:aria-busy="searchBusy">
                        <template x-for="(result, index) in searchResults" :key="index"><li><button type="button" class="search-result" x-on:click="chooseSearchResult(result)" x-text="result.label"></button></li></template>
                    </ul>
                    <p class="small muted" x-cloak x-show="selectedLocation" x-text="'Selected: ' + selectedLocation"></p>
                    <p class="small muted">Search results are approximate. Refine the office position by clicking or dragging the marker.</p>
                    <p class="small muted">Search by <a class="text-blue-700 underline" href="https://photon.komoot.io/" target="_blank" rel="noopener noreferrer">Photon</a> · <a class="text-blue-700 underline" href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">OpenStreetMap contributors</a></p>
                </div>
            @endif
            @if(!$readonly)
                <div x-cloak x-show="pendingDetails !== null" class="space-y-3 rounded-lg border border-slate-200 bg-slate-50 p-3" role="group" aria-label="Apply location details" x-ref="detailsConfirmation" tabindex="-1">
                    <p class="small">Apply the selected location details to Branch Information? Existing entered address details may be replaced.</p>
                    <div class="flex flex-wrap gap-2">
                        <x-button type="button" x-on:click="applyPendingDetails()">Apply Details</x-button>
                        <x-button type="button" variant="secondary" x-on:click="pendingDetails = null">Keep Existing Details</x-button>
                    </div>
                </div>
                <p class="small muted" role="status" x-cloak x-show="detailsMessage" x-text="detailsMessage"></p>
            @endif
            <div class="flex flex-wrap items-center gap-3">
                <x-button variant="secondary" x-on:click="fitGeofence()" x-bind:disabled="!ready || !validLocation || !validRadius">Fit Geofence to View</x-button>
                @if(!$readonly)
                    <x-button variant="secondary" x-on:click="resetLocation()">Reset Location</x-button>
                @endif
            </div>
            @if(!$readonly)
                <label class="flex items-start gap-2 small"><input type="checkbox" x-model="manual" class="mt-0.5 h-4 w-4 shrink-0"> <span>Edit coordinates manually (advanced / keyboard access)</span></label>
                <div class="grid grid-cols-1 gap-4">
                    <x-input name="latitude" label="Latitude" type="number" step="any" min="-90" max="90" :value="$mapConfig['latitude']" x-model="latitude" x-bind:readonly="!manual" />
                    <x-input name="longitude" label="Longitude" type="number" step="any" min="-180" max="180" :value="$mapConfig['longitude']" x-model="longitude" x-bind:readonly="!manual" />
                    <x-input name="geofence_radius_meters" label="Geofencing Radius (Meters)" type="number" step="1" :min="$mapConfig['minRadius']" :max="$mapConfig['maxRadius']" :value="$mapConfig['radius']" x-model="radius" aria-describedby="radius-help radius-validation" />
                </div>
                <p id="radius-help" class="small muted">Use whole meters, {{ number_format($mapConfig['minRadius']) }}–{{ number_format($mapConfig['maxRadius']) }}. Radius is the distance from the office marker to the boundary.</p>
            @else
                <dl class="grid grid-cols-1 gap-4 small">
                    <div><dt class="muted">Latitude</dt><dd>{{ $branch->latitude ?? 'Not configured' }}</dd></div>
                    <div><dt class="muted">Longitude</dt><dd>{{ $branch->longitude ?? 'Not configured' }}</dd></div>
                    <div><dt class="muted">Geofencing Radius (Meters)</dt><dd>{{ $branch->geofence_radius_meters ?? 'Not configured' }}</dd></div>
                </dl>
            @endif
            <p class="small font-semibold text-teal-700" aria-live="polite" x-cloak x-show="validLocation && validRadius" x-text="`Geofence Radius: ${radius} meters`"></p>
            @if(!$readonly)
                <p id="radius-validation" class="field-error" x-cloak x-show="!validRadius && (String(radius).trim() !== '' || validLocation)">Enter a valid geofence radius in meters.</p>
            @endif
            <div class="space-y-3" aria-live="polite">
                <p class="small muted" x-show="!validLocation">{{ $branch->exists ? 'Location is not configured for this branch.' : 'No branch location selected. Click the map to place the office marker.' }}</p>
                <p class="field-error" x-cloak x-show="locationError" x-text="locationError"></p>
                <div class="alert" x-cloak x-show="mapError" role="alert">
                    <p x-text="mapError"></p>
                    <x-button class="mt-3" variant="secondary" x-on:click="startMap()">Retry Map</x-button>
                </div>
            </div>
            @if(!$readonly)
                <p class="small muted">Active branches require a complete geofence. Inactive branches may leave all three location fields empty. Use the zoom controls to find your office.</p>
            @endif
            <x-button variant="secondary" x-cloak x-show="touchDevice" x-on:click="togglePanning()" x-bind:aria-pressed="panning" x-text="panning ? 'Enable page scrolling' : 'Enable map panning'">Enable map panning</x-button>
            <p class="small muted" x-cloak x-show="touchDevice">Tap to select a location. Enable map panning to move the map with one finger; turn it off to scroll the page.</p>
            <noscript><p class="field-error">JavaScript is required for the map preview. HR can enter coordinates and radius in the fields above; server validation still applies.</p></noscript>
        </aside>
    </div>
</section>
