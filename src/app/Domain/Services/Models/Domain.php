<?php

namespace App\Domain\Services\Models;

use App\Domain\Clients\Models\Client;
use App\Domain\Services\Enums\DomainStatus;
use Database\Factories\DomainFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    /** @use HasFactory<DomainFactory> */
    use HasFactory;

    protected static function newFactory(): Factory
    {
        return DomainFactory::new();
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'service_id',
        'name',
        'status',
        'registered_at',
        'expires_at',
        'auto_renew',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'registered_at' => 'date',
            'expires_at' => 'date',
            'auto_renew' => 'boolean',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }
}
