<?php

namespace Neurony\Revisions\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Neurony\Revisions\Options\RevisionOptions;
use Neurony\Revisions\Traits\HasRevisions;

class Post extends Model
{
    use HasRevisions;

    /**
     * The database table.
     *
     * @var string
     */
    protected $table = 'posts';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'author_id',
        'name',
        'slug',
        'content',
        'votes',
        'views',
    ];

    /**
     * @return BelongsTo
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(Author::class, 'author_id');
    }

    /**
     * @return HasOne
     */
    public function reply(): HasOne
    {
        return $this->hasOne(Reply::class, 'post_id');
    }

    /**
     * @return HasMany
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class, 'post_id');
    }

    /**
     * A post has and belongs to many tags.
     *
     * @return BelongsToMany
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'post_tag', 'post_id', 'tag_id');
    }

    /**
     * @return RevisionOptions
     */
    public function getRevisionOptions(): RevisionOptions
    {
        return RevisionOptions::instance();
    }
}
