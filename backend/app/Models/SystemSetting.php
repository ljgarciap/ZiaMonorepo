<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    protected $casts = [
        'value' => 'encrypted',
    ];

    /**
     * Únicas keys gestionables desde la UI de administración — cualquier otra
     * key es rechazada por el controller, para no convertir esta tabla en un
     * key-value store arbitrario.
     */
    public const MANAGED_KEYS = [
        'MISTRAL_API_KEY',
        'ANTHROPIC_API_KEY',
        'LANGFUSE_PUBLIC_KEY',
        'LANGFUSE_SECRET_KEY',
        'THINGSBOARD_HOST',
        'THINGSBOARD_USERNAME',
        'THINGSBOARD_PASSWORD',
    ];

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Valor vigente de una key: BD primero (permite rotarla sin redeploy),
     * variable de entorno como fallback (compatibilidad con despliegues que
     * todavía no usan la UI de administración).
     */
    public static function resolve(string $key, ?string $default = null): ?string
    {
        $setting = static::where('key', $key)->first();

        return $setting ? $setting->value : env($key, $default);
    }

    /**
     * Valor SOLO de la BD, sin fallback a env() — usado para exponer overrides
     * a servicios externos (zia-agent) que tienen su propio .env independiente
     * del de Laravel. env() aquí sería el .env de Laravel, no el del servicio
     * que realmente usa la key, así que caer a él sería incorrecto: "sin
     * override" debe significar null, para que sea el servicio externo quien
     * decida su propio fallback (su propio .env), no Laravel el suyo.
     */
    public static function dbValue(string $key): ?string
    {
        return static::where('key', $key)->first()?->value;
    }

    public static function maskedValue(string $key): ?string
    {
        $setting = static::where('key', $key)->first();
        if (!$setting || !$setting->value) {
            return null;
        }

        $value = $setting->value;
        $length = strlen($value);
        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($value, -4);
    }
}
