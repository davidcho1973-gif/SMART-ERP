<?php

namespace Tests\Unit;

use App\Models\Site;
use App\Support\Geo;
use PHPUnit\Framework\TestCase;

class GeoTest extends TestCase
{
    public function test_distance_is_zero_for_identical_points(): void
    {
        $this->assertSame(0.0, Geo::distanceMeters(33.7838, -112.15, 33.7838, -112.15));
    }

    public function test_one_degree_of_longitude_at_equator_is_about_111km(): void
    {
        $d = Geo::distanceMeters(0.0, 0.0, 0.0, 1.0);
        $this->assertEqualsWithDelta(111195, $d, 50); // 6371km sphere → ~111.2km / deg
    }

    public function test_distance_grows_with_separation(): void
    {
        $near = Geo::distanceMeters(33.7838, -112.1500, 33.7840, -112.1500);
        $far = Geo::distanceMeters(33.7838, -112.1500, 33.7900, -112.1500);
        $this->assertGreaterThan($near, $far);
    }

    public function test_verify_site_flags_inside_and_outside_the_radius(): void
    {
        $site = new Site(['lat' => 33.7838, 'lng' => -112.15, 'radius_m' => 150]);

        [$distIn, $okIn] = Geo::verifySite($site, 33.7838, -112.15);
        $this->assertTrue($okIn);
        $this->assertLessThan(150, $distIn);

        // ~1.4km north — well outside the 150m fence
        [$distOut, $okOut] = Geo::verifySite($site, 33.7963, -112.15);
        $this->assertFalse($okOut);
        $this->assertGreaterThan(150, $distOut);
    }

    public function test_verify_site_returns_nulls_when_coords_or_location_missing(): void
    {
        $sited = new Site(['lat' => 33.7838, 'lng' => -112.15, 'radius_m' => 150]);
        $this->assertSame([null, null], Geo::verifySite($sited, null, null));      // no coords
        $this->assertSame([null, null], Geo::verifySite($sited, '', ''));          // blank coords
        $this->assertSame([null, null], Geo::verifySite(null, 33.7838, -112.15));  // no site
        $this->assertSame([null, null], Geo::verifySite(new Site, 33.7838, -112.15)); // site w/o location
    }
}
