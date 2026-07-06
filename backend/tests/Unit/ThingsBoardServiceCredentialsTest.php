<?php

namespace Tests\Unit;

use App\Models\SystemSetting;
use App\Services\ThingsBoardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThingsBoardServiceCredentialsTest extends TestCase
{
    use RefreshDatabase;

    private function property(ThingsBoardService $service, string $name)
    {
        $ref = new \ReflectionProperty(ThingsBoardService::class, $name);
        $ref->setAccessible(true);
        return $ref->getValue($service);
    }

    public function test_uses_db_credentials_when_set()
    {
        SystemSetting::create(['key' => 'THINGSBOARD_HOST', 'value' => 'https://tb.example.com']);
        SystemSetting::create(['key' => 'THINGSBOARD_USERNAME', 'value' => 'db-user']);
        SystemSetting::create(['key' => 'THINGSBOARD_PASSWORD', 'value' => 'db-pass']);

        $service = new ThingsBoardService();

        $this->assertEquals('https://tb.example.com', $this->property($service, 'host'));
        $this->assertEquals('db-user', $this->property($service, 'username'));
        $this->assertEquals('db-pass', $this->property($service, 'password'));
    }

    public function test_falls_back_to_default_host_when_nothing_configured()
    {
        $service = new ThingsBoardService();

        $this->assertEquals('https://thingsboard.cloud', $this->property($service, 'host'));
    }
}
