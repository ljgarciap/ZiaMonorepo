<?php

namespace Tests\Feature;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemSettingModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_value_is_encrypted_at_rest_but_readable_through_the_model()
    {
        $setting = SystemSetting::create(['key' => 'MISTRAL_API_KEY', 'value' => 'sk-plaintext-secret']);

        $raw = \DB::table('system_settings')->where('id', $setting->id)->value('value');
        $this->assertNotEquals('sk-plaintext-secret', $raw);

        $this->assertEquals('sk-plaintext-secret', $setting->fresh()->value);
    }

    public function test_resolve_returns_db_value_when_present()
    {
        SystemSetting::create(['key' => 'MISTRAL_API_KEY', 'value' => 'db-value']);

        $this->assertEquals('db-value', SystemSetting::resolve('MISTRAL_API_KEY'));
    }

    public function test_resolve_falls_back_to_env_when_not_in_db()
    {
        config(['app.env' => 'testing']);
        putenv('SOME_TEST_KEY=env-fallback-value');

        $this->assertEquals('env-fallback-value', SystemSetting::resolve('SOME_TEST_KEY'));

        putenv('SOME_TEST_KEY');
    }

    public function test_masked_value_shows_only_last_four_characters()
    {
        SystemSetting::create(['key' => 'ANTHROPIC_API_KEY', 'value' => 'sk-ant-abcd1234']);

        $this->assertEquals('***********1234', SystemSetting::maskedValue('ANTHROPIC_API_KEY'));
    }

    public function test_masked_value_is_null_when_key_not_set()
    {
        $this->assertNull(SystemSetting::maskedValue('MISTRAL_API_KEY'));
    }

    public function test_db_value_returns_the_stored_value_without_any_env_fallback()
    {
        SystemSetting::create(['key' => 'MISTRAL_API_KEY', 'value' => 'db-only-value']);

        $this->assertEquals('db-only-value', SystemSetting::dbValue('MISTRAL_API_KEY'));
    }

    public function test_db_value_is_null_when_not_set_even_if_env_has_a_value()
    {
        // A diferencia de resolve(), dbValue() nunca debe caer a env() — un
        // servicio externo (zia-agent) tiene su propio .env, y Laravel jamás
        // debería reportar SU env como si fuera el del otro servicio.
        putenv('MISTRAL_API_KEY=leaked-from-laravel-env');

        $this->assertNull(SystemSetting::dbValue('MISTRAL_API_KEY'));

        putenv('MISTRAL_API_KEY');
    }
}
