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

    public function test_verify_confirms_on_site_for_a_precise_central_fix(): void
    {
        $site = new Site(['lat' => 33.7838, 'lng' => -112.15, 'radius_m' => 150]);
        [, $ok] = Geo::verify($site, Geo::coords(33.7838, -112.15, 8.0));
        $this->assertTrue($ok);
    }

    public function test_verify_flags_a_precise_fix_far_outside(): void
    {
        $site = new Site(['lat' => 33.7838, 'lng' => -112.15, 'radius_m' => 150]);
        [, $ok] = Geo::verify($site, Geo::coords(33.7963, -112.15, 12.0)); // ~1.4km, ±12m
        $this->assertFalse($ok);
    }

    public function test_verify_withholds_verdict_when_accuracy_straddles_the_fence(): void
    {
        // GYOHUI KIM's case: ~59 m from the centre of a tight 50 m fence, but the
        // fix is only good to ±40 m — she could easily be inside. Must NOT be a
        // confident off-site (that was the false "현장 밖" alarm).
        $site = new Site(['lat' => 33.7838, 'lng' => -112.15, 'radius_m' => 50]);
        [$dist, $ok] = Geo::verify($site, Geo::coords(33.78433, -112.15, 40.0));
        $this->assertGreaterThan(50, $dist);   // genuinely past the tight fence...
        $this->assertNull($ok);                // ...yet the verdict is withheld
    }

    public function test_verify_withholds_verdict_for_a_coarse_or_missing_fix(): void
    {
        $site = new Site(['lat' => 33.7838, 'lng' => -112.15, 'radius_m' => 150]);
        // dead-centre but only ±800 m accurate → too coarse to trust
        [, $coarse] = Geo::verify($site, Geo::coords(33.7838, -112.15, 800.0));
        $this->assertNull($coarse);
        // no accuracy reported at all → verdict withheld
        [, $noAcc] = Geo::verify($site, Geo::coords(33.7838, -112.15, null));
        $this->assertNull($noAcc);
        // no fix at all
        $this->assertSame([null, null], Geo::verify($site, null));
    }
}
