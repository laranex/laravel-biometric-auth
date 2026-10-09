<?php

declare(strict_types=1);

namespace Laranex\LaravelBiometricAuth\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;

/**
 * @property string $id
 * @property string $authenticable_id
 * @property string $authenticable_type
 * @property string $public_key
 * @property string|null $challenge
 * @property bool|int $revoked
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $instance
 *
 * @method static Builder active()
 */
class Biometric extends Model
{
    use HasUuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['id', 'authenticable_id', 'authenticable_type', 'public_key', 'challenge', 'revoked'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = ['public_key'];

    /**
     * Get the table associated with the model.
     */
    public function getTable(): string
    {
        $table = Config::get('biometric-auth.table');

        return is_string($table) && $table !== '' ? $table : parent::getTable();
    }

    /**
     * Scope the query to biometrics that have not been revoked.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('revoked', false);
    }

    /**
     * The authenticatable model (User, Admin, ...) that registered this biometric.
     */
    public function instance(): MorphTo
    {
        return $this->morphTo('authenticable');
    }
}
