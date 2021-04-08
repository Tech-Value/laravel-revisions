<?php

namespace Neurony\Revisions\Tests;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Neurony\Revisions\Models\Revision;
use Neurony\Revisions\Options\RevisionOptions;
use Neurony\Revisions\Tests\Models\Comment;
use Neurony\Revisions\Tests\Models\Post;
use Neurony\Revisions\Tests\Models\Tag;

class HasRevisionsTest extends TestCase
{
    /** @test */
    public function it_automatically_creates_a_revision_when_the_record_changes(): void
    {
        $this->makeModels();
        $this->modifyPost();

        self::assertEquals(1, Revision::count());
    }

    /** @test */
    public function it_can_manually_create_a_revision(): void
    {
        $this->makeModels();

        $this->post->saveAsRevision();

        self::assertEquals(1, Revision::count());
    }

    /** @test */
    public function it_stores_the_original_attribute_values_when_creating_a_revision(): void
    {
        $this->makeModels();
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        self::assertEquals('Post name', $revision->metadata['name']);
        self::assertEquals('post-slug', $revision->metadata['slug']);
        self::assertEquals('Post content', $revision->metadata['content']);
        self::assertEquals(10, $revision->metadata['votes']);
        self::assertEquals(100, $revision->metadata['views']);
    }

    /** @test */
    public function it_can_rollback_to_a_past_revision(): void
    {
        $this->makeModels();
        $this->modifyPost();

        self::assertEquals('Another post name', $this->post->name);
        self::assertEquals('another-post-slug', $this->post->slug);
        self::assertEquals('Another post content', $this->post->content);
        self::assertEquals(20, $this->post->votes);
        self::assertEquals(200, $this->post->views);

        $this->post->rollbackToRevision($this->post->revisions()->first());

        self::assertEquals('Post name', $this->post->name);
        self::assertEquals('post-slug', $this->post->slug);
        self::assertEquals('Post content', $this->post->content);
        self::assertEquals(10, $this->post->votes);
        self::assertEquals(100, $this->post->views);
    }

    /** @test */
    public function it_creates_a_new_revision_when_rolling_back_to_a_past_revision(): void
    {
        $this->makeModels();
        $this->modifyPost();

        $this->post->rollbackToRevision($this->post->revisions()->first());

        self::assertEquals(2, Revision::count());
    }

    /** @test */
    public function it_can_delete_all_revisions_of_a_record(): void
    {
        $this->makeModels();
        $this->modifyPost();
        $this->modifyPostAgain();

        self::assertEquals(2, Revision::count());

        $this->post->deleteAllRevisions();

        self::assertEquals(0, Revision::count());
    }

    /** @test */
    public function it_can_create_a_revision_when_creating_the_record(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->enableRevisionOnCreate();
            }
        };

        $this->makeModels($model);

        self::assertEquals(1, Revision::count());
    }

    /** @test */
    public function it_can_limit_the_number_of_revisions_a_record_can_have(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->limitRevisionsTo(5);
            }
        };

        $this->makeModels($model);

        for ($i = 1; $i <= 10; $i++) {
            $this->modifyPost();
            $this->modifyPostAgain();
        }

        self::assertEquals(5, Revision::count());
    }

    /** @test */
    public function it_deletes_the_oldest_revisions_when_the_limit_is_achieved(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->limitRevisionsTo(5);
            }
        };

        $this->makeModels($model);

        for ($i = 1; $i <= 10; $i++) {
            $this->modifyPost();
            $this->modifyPostAgain();
        }

        self::assertEquals(16, $this->post->revisions()->oldest()->first()->id);
    }

    /** @test */
    public function it_can_specify_only_certain_fields_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->fieldsToRevision('name', 'votes');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        self::assertArrayHasKey('name', $revision->metadata);
        self::assertArrayHasKey('votes', $revision->metadata);
        self::assertArrayNotHasKey('slug', $revision->metadata);
        self::assertArrayNotHasKey('content', $revision->metadata);
        self::assertArrayNotHasKey('views', $revision->metadata);
    }

    /** @test */
    public function it_can_exclude_certain_fields_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->fieldsToNotRevision('name', 'votes');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        self::assertArrayNotHasKey('name', $revision->metadata);
        self::assertArrayNotHasKey('votes', $revision->metadata);
        self::assertArrayHasKey('slug', $revision->metadata);
        self::assertArrayHasKey('content', $revision->metadata);
        self::assertArrayHasKey('views', $revision->metadata);
    }

    /** @test */
    public function it_can_include_timestamps_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->withTimestamps();
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        self::assertArrayHasKey('created_at', $revision->metadata);
        self::assertArrayHasKey('updated_at', $revision->metadata);
    }

    /** @test */
    public function it_can_save_belongs_to_relations_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('author');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        self::assertArrayHasKey('author', $revision->metadata['relations']);
        self::assertArrayHasKey('records', $revision->metadata['relations']['author']);
        self::assertEquals(BelongsTo::class, $revision->metadata['relations']['author']['type']);

        self::assertEquals($this->post->author->title, $revision->metadata['relations']['author']['records']['items'][0]['title']);
        self::assertEquals($this->post->author->name, $revision->metadata['relations']['author']['records']['items'][0]['name']);
        self::assertEquals($this->post->author->age, $revision->metadata['relations']['author']['records']['items'][0]['age']);
    }

    /** @test */
    public function it_stores_the_original_attribute_values_of_belongs_to_relations_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('author');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $this->post->author()->update([
            'title' => 'Author title updated',
            'name' => 'Author name updated',
            'age' => 100,
        ]);

        $author = $this->post->author;
        $revision = $this->post->revisions()->first();

        self::assertEquals('Author title updated', $author->title);
        self::assertEquals('Author name updated', $author->name);
        self::assertEquals('100', $author->age);

        self::assertEquals('Author title', $revision->metadata['relations']['author']['records']['items'][0]['title']);
        self::assertEquals('Author name', $revision->metadata['relations']['author']['records']['items'][0]['name']);
        self::assertEquals('30', $revision->metadata['relations']['author']['records']['items'][0]['age']);
    }

    /** @test */
    public function it_rolls_back_belongs_to_relations_when_rolling_back_to_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('author');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $this->post->author()->update([
            'title' => 'Author title updated',
            'name' => 'Author name updated',
            'age' => 100,
        ]);

        $this->post->rollbackToRevision(
            $this->post->revisions()->first()
        );

        $author = $this->post->fresh()->author;

        self::assertEquals('Author title', $author->title);
        self::assertEquals('Author name', $author->name);
        self::assertEquals('30', $author->age);
    }

    /** @test */
    public function it_can_save_has_one_relations_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('reply');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        self::assertArrayHasKey('reply', $revision->metadata['relations']);
        self::assertArrayHasKey('records', $revision->metadata['relations']['reply']);
        self::assertEquals(HasOne::class, $revision->metadata['relations']['reply']['type']);

        self::assertEquals($this->post->id, $revision->metadata['relations']['reply']['records']['items'][0]['post_id']);
        self::assertEquals('Reply subject', $revision->metadata['relations']['reply']['records']['items'][0]['subject']);
        self::assertEquals('Reply content', $revision->metadata['relations']['reply']['records']['items'][0]['content']);
    }

    /** @test */
    public function it_stores_the_original_attribute_values_of_has_one_relations_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('reply');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $this->post->reply()->update([
            'subject' => 'Reply subject updated',
            'content' => 'Reply content updated',
        ]);

        $reply = $this->post->reply;
        $revision = $this->post->revisions()->first();

        self::assertEquals('Reply subject updated', $reply->subject);
        self::assertEquals('Reply content updated', $reply->content);

        self::assertEquals('Reply subject', $revision->metadata['relations']['reply']['records']['items'][0]['subject']);
        self::assertEquals('Reply content', $revision->metadata['relations']['reply']['records']['items'][0]['content']);
    }

    /** @test */
    public function it_rolls_back_has_one_relations_when_rolling_back_to_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('reply');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $this->post->reply()->update([
            'subject' => 'Reply subject updated',
            'content' => 'Reply content updated',
        ]);

        $this->post->rollbackToRevision(
            $this->post->revisions()->first()
        );

        $reply = $this->post->fresh()->reply;

        self::assertEquals('Reply subject', $reply->subject);
        self::assertEquals('Reply content', $reply->content);
    }

    /** @test */
    public function it_removes_extra_created_has_one_relations_when_rolling_back_to_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('reply');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $relatedCountForRevisionCheckpoint = $this->post->reply()->count();

        $this->post->reply()->create([
            'subject' => 'Extra reply subject',
            'content' => 'Extra reply content',
        ]);

        $this->post->rollbackToRevision(
            $this->post->revisions()->first()
        );

        self::assertEquals($relatedCountForRevisionCheckpoint, $this->post->reply()->count());
    }

    /** @test */
    public function it_can_save_has_many_relations_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('comments');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        self::assertArrayHasKey('comments', $revision->metadata['relations']);
        self::assertArrayHasKey('records', $revision->metadata['relations']['comments']);
        self::assertEquals(HasMany::class, $revision->metadata['relations']['comments']['type']);

        for ($i = 1; $i <= 3; $i++) {
            $comment = Comment::limit(1)->offset($i - 1)->first();

            self::assertEquals($this->post->id, $revision->metadata['relations']['comments']['records']['items'][$i - 1]['post_id']);
            self::assertEquals($comment->title, $revision->metadata['relations']['comments']['records']['items'][$i - 1]['title']);
            self::assertEquals($comment->content, $revision->metadata['relations']['comments']['records']['items'][$i - 1]['content']);
            self::assertEquals($comment->date, $revision->metadata['relations']['comments']['records']['items'][$i - 1]['date']);
            self::assertEquals($comment->active, $revision->metadata['relations']['comments']['records']['items'][$i - 1]['active']);
        }
    }

    /** @test */
    public function it_stores_the_original_attribute_values_of_has_many_relations_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('comments');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        for ($i = 1; $i <= 3; $i++) {
            $this->post->comments()->limit(1)->offset($i - 1)->first()->update([
                'title' => 'Comment title '.$i.' updated',
                'content' => 'Comment content '.$i.' updated',
                'active' => false,
            ]);
        }

        $revision = $this->post->revisions()->first();

        for ($i = 1; $i <= 3; $i++) {
            $comment = $this->post->fresh()->comments()->limit(1)->offset($i - 1)->first();

            self::assertEquals('Comment title '.$i.' updated', $comment->title);
            self::assertEquals('Comment content '.$i.' updated', $comment->content);
            self::assertEquals(0, $comment->active);

            self::assertEquals('Comment title '.$i, $revision->metadata['relations']['comments']['records']['items'][$i - 1]['title']);
            self::assertEquals('Comment content '.$i, $revision->metadata['relations']['comments']['records']['items'][$i - 1]['content']);
            self::assertEquals(1, $revision->metadata['relations']['comments']['records']['items'][$i - 1]['active']);
        }
    }

    /** @test */
    public function it_rolls_back_has_many_relations_when_rolling_back_to_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('comments');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        for ($i = 1; $i <= 3; $i++) {
            $this->post->comments()->limit(1)->offset($i - 1)->first()->update([
                'title' => 'Comment title '.$i.' updated',
                'content' => 'Comment content '.$i.' updated',
                'active' => false,
            ]);
        }

        $this->post->rollbackToRevision(
            $this->post->revisions()->first()
        );

        for ($i = 1; $i <= 3; $i++) {
            $comment = $this->post->fresh()->comments()->limit(1)->offset($i - 1)->first();

            self::assertEquals('Comment title '.$i, $comment->title);
            self::assertEquals('Comment content '.$i, $comment->content);
            self::assertEquals(1, $comment->active);
        }
    }

    /** @test */
    public function it_removes_extra_created_has_many_relations_when_rolling_back_to_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('comments');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $relatedCountForRevisionCheckpoint = $this->post->comments()->count();

        $this->post->comments()->create([
            'title' => 'Extra comment title',
            'content' => 'Extra comment content',
            'date' => Carbon::now(),
            'active' => true,
        ]);

        $this->post->rollbackToRevision(
            $this->post->revisions()->first()
        );

        self::assertEquals($relatedCountForRevisionCheckpoint, $this->post->comments()->count());
    }

    /** @test */
    public function it_can_save_belongs_to_many_relations_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('tags');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        self::assertArrayHasKey('tags', $revision->metadata['relations']);
        self::assertArrayHasKey('records', $revision->metadata['relations']['tags']);
        self::assertArrayHasKey('pivots', $revision->metadata['relations']['tags']);
        self::assertEquals(BelongsToMany::class, $revision->metadata['relations']['tags']['type']);

        for ($i = 1; $i <= 3; $i++) {
            $tag = Tag::find($i);

            self::assertEquals($tag->name, $revision->metadata['relations']['tags']['records']['items'][$i - 1]['name']);
            self::assertEquals($this->post->id, $revision->metadata['relations']['tags']['pivots']['items'][$i - 1]['post_id']);
            self::assertEquals($tag->id, $revision->metadata['relations']['tags']['pivots']['items'][$i - 1]['tag_id']);
        }
    }

    /** @test */
    public function it_stores_the_original_pivot_values_of_belongs_to_many_relations_when_creating_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('tags');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $revision = $this->post->revisions()->first();

        $this->post->tags()->detach(
            $this->post->tags()->first()->id
        );

        self::assertCount(3, $revision->metadata['relations']['tags']['pivots']['items']);
    }

    /** @test */
    public function it_rolls_back_belongs_to_many_relations_when_rolling_back_to_a_revision(): void
    {
        $model = new class extends Post {
            public function getRevisionOptions(): RevisionOptions
            {
                return parent::getRevisionOptions()->relationsToRevision('tags');
            }
        };

        $this->makeModels($model);
        $this->modifyPost();

        $this->post->tags()->detach(
            $this->post->tags()->first()->id
        );

        self::assertEquals(2, $this->post->tags()->count());

        $this->post->rollbackToRevision(
            $this->post->revisions()->first()
        );

        self::assertEquals(3, $this->post->tags()->count());
    }

    /**
     * @return void
     */
    protected function modifyPost(): void
    {
        $this->post->update([
            'name' => 'Another post name',
            'slug' => 'another-post-slug',
            'content' => 'Another post content',
            'votes' => 20,
            'views' => 200,
        ]);
    }

    /**
     * @return void
     */
    protected function modifyPostAgain(): void
    {
        $this->post->update([
            'name' => 'Yet another post name',
            'slug' => 'yet-another-post-slug',
            'content' => 'Yet another post content',
            'votes' => 30,
            'views' => 300,
        ]);
    }
}
