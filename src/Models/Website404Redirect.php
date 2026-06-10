<?php

namespace Apogee\Website404Redirects\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Website404Redirect extends Model
{
    protected $table = 'website_404_redirects';

    protected $fillable = [
        'path',
        'hit_count',
        'first_seen_at',
        'last_seen_at',
        'redirect_to',
        'redirect_status',
        'is_ignored',
        'notes',
        'last_referer',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'hit_count' => 'integer',
            'redirect_status' => 'integer',
            'is_ignored' => 'boolean',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'updated_by');
    }

    public function hasActiveRedirect(): bool
    {
        return ! $this->is_ignored && filled($this->redirect_to);
    }
}
