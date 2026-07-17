<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\ThingsBoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThingsBoardServiceCredentialsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * host()/username()/password() resuelven perezosamente (no en el
     * constructor — ver ThingsBoardService::__construct), así que hay que
     * invocar el método, no leer la propiedad cruda, para forzar la
     * resolución.
     */
    private function invokeLazy(ThingsBoardService $service, string $method)
    {
        $ref = new \ReflectionMethod(ThingsBoardService::class, $method);
        $ref->setAccessible(true);
        return $ref->invoke($service);
    }

    public function test_uses_db_credentials_when_set()
    {
        SystemSetting::create(['key' => 'THINGSBOARD_HOST', 'value' => 'https://tb.example.com']);
        SystemSetting::create(['key' => 'THINGSBOARD_USERNAME', 'value' => 'db-user']);
        SystemSetting::create(['key' => 'THINGSBOARD_PASSWORD', 'value' => 'db-pass']);

        $service = new ThingsBoardService();

        $this->assertEquals('https://tb.example.com', $this->invokeLazy($service,'host'));
        $this->assertEquals('db-user', $this->invokeLazy($service,'username'));
        $this->assertEquals('db-pass', $this->invokeLazy($service,'password'));
    }

    public function test_falls_back_to_default_host_when_nothing_configured()
    {
        $service = new ThingsBoardService();

        $this->assertEquals('https://thingsboard.cloud', $this->invokeLazy($service,'host'));
    }
}
