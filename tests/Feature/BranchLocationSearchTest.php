<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\Phase3Data;
use Tests\TestCase;

class BranchLocationSearchTest extends TestCase
{
    use Phase3Data, RefreshDatabase;

    public function test_only_authenticated_hr_can_search(): void
    {
        Http::preventStrayRequests();
        $this->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertUnauthorized();
        $this->actingAs(User::factory()->create())->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_missing_configuration_disables_search_without_disabling_branch_maps(): void
    {
        Http::preventStrayRequests();
        config(['services.photon.base_url' => null]);
        $this->actingAs($this->hr())->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertServiceUnavailable()->assertJsonPath('message', 'Location search is not configured. Contact the system administrator.');
        $this->get(route('hr.branches.create'))->assertOk()->assertSee('Search Location')->assertSee('branchLocationMap')->assertDontSee('apiKey');
        Http::assertNothingSent();
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_queries_do_not_contact_provider(mixed $query): void
    {
        Http::preventStrayRequests();
        $this->actingAs($this->hr())->postJson(route('hr.branches.location-search'), ['query' => $query])->assertUnprocessable()->assertJsonValidationErrors('query');
        Http::assertNothingSent();
    }

    public static function invalidQueries(): array
    {
        return [[''], ['   '], ['ab'], [str_repeat('a', 201)], [[]], ['!!!']];
    }

    public function test_photon_longitude_latitude_results_are_normalized_and_cached(): void
    {
        Http::preventStrayRequests();
        config(['maps.geocoding_provider' => 'photon', 'services.photon.base_url' => 'https://photon.komoot.io']);
        Http::fake(['photon.komoot.io/api/*' => Http::response(['type' => 'FeatureCollection', 'features' => [
            ['geometry' => ['type' => 'Point', 'coordinates' => [121.1622, 13.9411]], 'properties' => ['name' => 'Lipa City', 'state' => 'Batangas', 'country' => 'Philippines']],
            ['geometry' => ['type' => 'Point', 'coordinates' => [120, 91]], 'properties' => ['name' => 'Invalid latitude']],
            ['geometry' => ['type' => 'Polygon', 'coordinates' => [[120, 13]]], 'properties' => ['name' => 'Wrong geometry']],
            ['geometry' => ['type' => 'Point', 'coordinates' => ['bad', 13]], 'properties' => ['name' => 'Invalid coordinates']],
            ['properties' => ['name' => 'Missing coordinates']],
        ]])]);
        $this->actingAs($this->hr());
        $this->postJson(route('hr.branches.location-search'), ['query' => '  Lipa   City  '])->assertOk()->assertExactJson(['results' => [
            ['label' => 'Lipa City, Batangas, Philippines', 'latitude' => 13.9411, 'longitude' => 121.1622, 'details' => ['province' => 'Batangas']],
        ]]);
        $this->postJson(route('hr.branches.location-search'), ['query' => 'lipa city'])->assertOk()->assertJsonCount(1, 'results');
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request['q'] === 'lipa city' && ! isset($request['apiKey']) && $request['limit'] === 5);
    }

    public function test_no_results_are_cached_and_provider_failures_are_safe(): void
    {
        Http::preventStrayRequests();
        config(['maps.geocoding_provider' => 'photon', 'services.photon.base_url' => 'https://photon.komoot.io']);
        Http::fake(['photon.komoot.io/api/*' => Http::sequence()->push(['type' => 'FeatureCollection', 'features' => []])->push(['secret' => 'never return provider body'], 500)->push(['unexpected' => 'payload'])]);
        $this->actingAs($this->hr());
        $this->postJson(route('hr.branches.location-search'), ['query' => 'Unknown place'])->assertOk()->assertExactJson(['results' => []]);
        $this->postJson(route('hr.branches.location-search'), ['query' => 'Unknown place'])->assertOk()->assertExactJson(['results' => []]);
        $this->postJson(route('hr.branches.location-search'), ['query' => 'Provider fails'])->assertServiceUnavailable()->assertDontSee('never return provider body');
        $this->postJson(route('hr.branches.location-search'), ['query' => 'Malformed payload'])->assertServiceUnavailable();
        Http::assertSentCount(3);
    }

    public function test_connection_timeout_is_safe_and_can_be_retried(): void
    {
        Http::preventStrayRequests();
        config(['maps.geocoding_provider' => 'photon', 'services.photon.base_url' => 'https://photon.komoot.io']);
        Http::fake(['photon.komoot.io/api/*' => Http::failedConnection()]);
        $this->actingAs($this->hr())->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertStatus(504)->assertJsonPath('message', 'Location search timed out. Please try again.');
        Http::assertSentCount(1);
    }

    public function test_search_rate_limits_apply_before_external_requests(): void
    {
        Http::preventStrayRequests();
        config(['services.photon.base_url' => null]);
        $this->actingAs($this->hr());
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $this->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertServiceUnavailable();
        }
        $this->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertTooManyRequests();
        Http::assertNothingSent();
    }

    public function test_provider_budget_limits_all_hr_requests(): void
    {
        Http::preventStrayRequests();
        config(['maps.geocoding_provider' => 'photon', 'services.photon.base_url' => 'https://photon.komoot.io']);
        RateLimiter::increment('geocoding:photon-provider', 60, 20);
        $this->actingAs($this->hr())->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertTooManyRequests();
        Http::assertNothingSent();
    }

    public function test_photon_rate_limit_does_not_trigger_retries(): void
    {
        Http::preventStrayRequests();
        Http::fake(['photon.komoot.io/api/*' => Http::response(['error' => 'provider detail'], 429)]);
        $this->actingAs($this->hr())->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertTooManyRequests()->assertJsonPath('message', 'Location search is temporarily limited. Please try again later.');
        Http::assertSentCount(1);
    }

    public function test_photon_details_map_named_offices_and_partial_localities_without_inventing_values(): void
    {
        Http::preventStrayRequests();
        Http::fake(['photon.komoot.io/api/*' => Http::response(['type' => 'FeatureCollection', 'features' => [
            ['geometry' => ['type' => 'Point', 'coordinates' => [121.16, 13.94]], 'properties' => ['osm_key' => 'office', 'name' => 'Synthetic Cooperative Office', 'housenumber' => '12', 'street' => 'Gen. Luna Street', 'district' => 'Barangay 1', 'city' => 'Lipa City', 'state' => 'Batangas', 'postcode' => '4217', 'country' => 'Philippines', 'branch_code' => 'UNTRUSTED', 'status' => 'inactive']],
            ['geometry' => ['type' => 'Point', 'coordinates' => [121.17, 13.95]], 'properties' => ['osm_key' => 'place', 'name' => 'Lipa City', 'city' => 'Lipa City', 'state' => 'CALABARZON', 'county' => 'Batangas']],
            ['geometry' => ['type' => 'Point', 'coordinates' => [121.18, 13.96]], 'properties' => ['osm_key' => 'highway', 'name' => 'Test Road']],
        ]])]);

        $response = $this->actingAs($this->hr())->postJson(route('hr.branches.location-search'), ['query' => 'Synthetic Office'])->assertOk();

        $this->assertSame(['address' => '12 Gen. Luna Street, Barangay 1', 'city' => 'Lipa City', 'province' => 'Batangas', 'postal_code' => '4217', 'branch_name' => 'Synthetic Cooperative Office'], $response->json('results.0.details'));
        $this->assertSame(['city' => 'Lipa City', 'province' => 'Batangas'], $response->json('results.1.details'));
        $this->assertSame(['address' => 'Test Road'], $response->json('results.2.details'));
        Http::assertSentCount(1);
    }

    #[DataProvider('invalidProviderUrls')]
    public function test_invalid_provider_configuration_never_sends_requests(string $url): void
    {
        Http::preventStrayRequests();
        config(['services.photon.base_url' => $url]);
        $this->actingAs($this->hr())->postJson(route('hr.branches.location-search'), ['query' => 'Lipa City'])->assertServiceUnavailable();
        Http::assertNothingSent();
    }

    public static function invalidProviderUrls(): array
    {
        return [['not-a-url'], ['file:///etc/passwd'], ['https://user:password@example.test'], ['https://example.test?url=http://example.test']];
    }
}
