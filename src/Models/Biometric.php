<?php

namespace Laranex\LaravelBiometricAuth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Biometric extends Model
{
    use HasUuids;

    protected $primaryKey = 'id';

    protected $fillable = ['id', 'authenticable_id', 'authenticable_type', 'public_key', 'challenge', 'revoked'];

    protected $hidden = ['public_key'];

    public function getTable()
    {
        return config('biometric-auth.table', parent::getTable());
    }

    public function instance(): MorphTo
    {
        return $this->morphTo('authenticable');
    }
}
