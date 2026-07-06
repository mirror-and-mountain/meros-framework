<?php

namespace MM\Meros\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IntegrationEnvironment extends Model {
    protected $table = 'meros_integration_environments';

    protected $fillable = [
        'provider',
        'integration_handle',
        'environment',
        'label',
        'is_default',
        'settings',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'settings' => 'array',
    ];

    public function accounts(): HasMany {
        return $this->hasMany(IntegrationAccount::class, 'integration_handle', 'integration_handle')
            ->where('provider', $this->provider)
            ->where('environment', $this->environment);
    }

    public function oauthSetting(string $key, mixed $default = null): mixed {
        $settings = is_array($this->settings) ? $this->settings : [];

        return data_get($settings, $key, $default);
    }
}
