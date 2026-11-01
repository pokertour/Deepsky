<?php

namespace Tests\Unit;

use App\Services\SimbadService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SimbadServiceTest extends TestCase
{
    public function test_it_parses_a_successful_response(): void
    {
        Http::fake(['*' => Http::response($this->simbadBody())]);

        $result = SimbadService::lookup('M81');

        $this->assertSame("Bode's Galaxy", $result['title']);
        $this->assertSame('M 81', $result['id']);
        $this->assertSame('Sy2', $result['type']);

        Http::assertSent(fn ($request) => str_contains($request->url(), 'simbad.cds.unistra.fr'));
    }

    public function test_it_returns_null_on_a_failed_response(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);

        $this->assertNull(SimbadService::lookup('Nonexistent 999'));
    }

    public function test_results_are_cached(): void
    {
        Http::fake(['*' => Http::response($this->simbadBody())]);

        SimbadService::lookup('M81');
        SimbadService::lookup('M81');

        Http::assertSentCount(1);
    }

    private function simbadBody(): string
    {
        $marker = '::data::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::::';

        return "C.D.S. header lines\n{$marker}\nM 81|NAME M 81,NAME Bode's Galaxy|Sy2\n";
    }
}
