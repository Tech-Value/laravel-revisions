<?php

namespace Neurony\Revisions\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

interface RevisionModelContract
{
    /**
     * @return BelongsTo|null
     */
    public function user(): BelongsTo|null;

    /**
     * @return MorphTo
     */
    public function revisionable(): MorphTo;

    /**
     * @param Builder $query
     * @param Authenticatable $user
     */
    public function scopeWhereUser(Builder $query, Authenticatable $user);

    /**
     * @param Builder $query
     * @param int $id
     * @param string $type
     */
    public function scopeWhereRevisionable(Builder $query, int $id, string $type);
}
