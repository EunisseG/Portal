<?php

namespace App\Domain\Services\Models;

use App\Domain\Services\Enums\ServerPanel;
use Database\Factories\ServerFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Server extends Model
{
    /** @use HasFactory<ServerFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return ServerFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'hostname',
        'ip_address',
        'notes',
        'is_active',
        // Conexión al panel de control (WHM/cPanel o Plesk).
        'panel',
        'panel_scheme',
        'panel_host',
        'panel_port',
        'panel_user',
        'panel_token',
        'panel_verify_tls',
        'panel_user_prefix',
        'panel_password_length',
        // Nameservers sugeridos para dominios de este servidor.
        'ns1',
        'ns2',
        'ns3',
        'ns4',
        // Campos WHM heredados (compatibilidad; ya no se usan directamente).
        'whm_enabled',
        'whm_base_url',
        'whm_user',
        'whm_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'panel' => ServerPanel::class,
            'panel_port' => 'integer',
            // El token del panel se cifra at-rest; nunca se expone en texto plano.
            'panel_token' => 'encrypted',
            'panel_verify_tls' => 'boolean',
            'panel_password_length' => 'integer',
            'whm_enabled' => 'boolean',
            'whm_token' => 'encrypted',
        ];
    }

    /**
     * @var list<string>
     */
    protected $hidden = [
        'panel_token',
        'whm_token',
    ];

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    /**
     * ¿El servidor tiene una conexión de panel utilizable (gestor + host + token)?
     */
    public function hasPanel(): bool
    {
        return $this->panel !== null
            && ! empty($this->panel_host)
            && ! empty($this->panel_token);
    }

    /** Gestor de panel configurado (enum) o null. */
    public function panelDriver(): ?ServerPanel
    {
        return $this->panel;
    }

    /**
     * URL base del panel a partir de esquema, host y puerto.
     */
    public function panelBaseUrl(): ?string
    {
        if (empty($this->panel_host)) {
            return null;
        }

        $scheme = $this->panel_scheme ?: 'https';
        $port = $this->panel_port ?: ($this->panel?->defaultPort() ?? 2087);

        return sprintf('%s://%s:%d', $scheme, $this->panel_host, $port);
    }

    /**
     * Compatibilidad: ¿tiene conexión WHM utilizable? (ahora se apoya en el
     * esquema de panel genérico con gestor WHM).
     */
    public function hasWhm(): bool
    {
        return $this->panel === ServerPanel::Whm && $this->hasPanel();
    }
}
