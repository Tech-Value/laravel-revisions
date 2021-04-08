<?php

namespace Neurony\Revisions\Models;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Neurony\Revisions\Contracts\RevisionModelContract;

class Revision extends Model implements RevisionModelContract
{
    /**
     * The database table.
     *
     * @var string
     */
    protected $table = 'revisions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'revisionable_id',
        'revisionable_type',
        'metadata',
    ];

    /**
     * The attributes that are casted to a specific type.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Revision belongs to user.
     *
     * @return BelongsTo|null
     */
    public function user(): BelongsTo|null
    {
        $user = config('revisions.user_model');

        if ($user && class_exists($user)) {
            return $this->belongsTo($user, 'user_id');
        }

        return null;
    }

    /**
     * Get all of the owning revisionable models.
     *
     * @return MorphTo
     */
    public function revisionable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Filter the query by the given user id.
     *
     * @param Builder $query
     * @param Authenticatable $user
     */
    public function scopeWhereUser(Builder $query, Authenticatable $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * Filter the query by the given revisionable params (id, type).
     *
     * @param Builder $query
     * @param int $id
     * @param string $type
     */
    public function scopeWhereRevisionable(Builder $query, int $id, string $type): void
    {
        $query->where([
            'revisionable_id' => $id,
            'revisionable_type' => $type,
        ]);
    }
}
