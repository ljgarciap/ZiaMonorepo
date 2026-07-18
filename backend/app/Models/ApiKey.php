<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'key_prefix',
        'key_hash',
        'created_by',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /** Nunca serializar el hash — no hay razón para que salga en ningún JSON. */
    protected $hidden = ['key_hash'];

    const PREFIX = 'zia_live_';

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Genera una key nueva para una empresa. Devuelve la key en texto plano
     * (para mostrar al admin UNA sola vez) junto con el modelo persistido —
     * el texto plano no se guarda en ningún lado, solo su hash.
     *
     * @return array{key: string, model: self}
     */
    public static function generateFor(Company $company, string $name, ?int $createdBy = null): array
    {
        $plainKey = self::PREFIX . Str::random(40);

        $model = self::create([
            'company_id' => $company->id,
            'name' => $name,
            'key_prefix' => substr($plainKey, 0, 8),
            'key_hash' => self::hash($plainKey),
            'created_by' => $createdBy,
        ]);

        return ['key' => $plainKey, 'model' => $model];
    }

    public static function hash(string $plainKey): string
    {
        return hash('sha256', $plainKey);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }
}
