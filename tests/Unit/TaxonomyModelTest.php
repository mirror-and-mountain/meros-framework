<?php

namespace MM\Meros\Tests\Unit;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Eloquent\Model;
use MM\Meros\App\Models\Form;
use MM\Meros\App\Models\Post;
use MM\Meros\App\Models\Taxonomy;
use MM\Meros\App\Models\Term;
use PHPUnit\Framework\TestCase;

final class TaxonomyModelTest extends TestCase {
    private ?ConnectionResolverInterface $originalResolver;

    protected function setUp(): void {
        $this->originalResolver = Model::getConnectionResolver();
        $capsule = new Capsule();
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => 'wp_']);
        $capsule->bootEloquent();
        $database = $capsule->getConnection();
        $database->statement('CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_type TEXT)');
        $database->statement('CREATE TABLE wp_terms (term_id INTEGER PRIMARY KEY, name TEXT NOT NULL, slug TEXT NOT NULL, term_group INTEGER NOT NULL)');
        $database->statement('CREATE TABLE wp_term_taxonomy (term_taxonomy_id INTEGER PRIMARY KEY, term_id INTEGER NOT NULL, taxonomy TEXT NOT NULL, description TEXT NOT NULL, parent INTEGER NOT NULL, count INTEGER NOT NULL)');
        $database->statement('CREATE TABLE wp_term_relationships (object_id INTEGER NOT NULL, term_taxonomy_id INTEGER NOT NULL, term_order INTEGER NOT NULL DEFAULT 0, PRIMARY KEY (object_id, term_taxonomy_id))');
        $database->table('posts')->insert([
            ['ID' => 11, 'post_type' => 'form'],
            ['ID' => 12, 'post_type' => 'form-template'],
            ['ID' => 13, 'post_type' => 'post'],
        ]);
        $database->table('terms')->insert([
            ['term_id' => 101, 'name' => 'Contact', 'slug' => 'contact', 'term_group' => 0],
            ['term_id' => 102, 'name' => 'Feedback', 'slug' => 'feedback', 'term_group' => 0],
        ]);
        $database->table('term_taxonomy')->insert([
            ['term_taxonomy_id' => 201, 'term_id' => 101, 'taxonomy' => 'form-tags', 'description' => '', 'parent' => 0, 'count' => 2],
            ['term_taxonomy_id' => 202, 'term_id' => 101, 'taxonomy' => 'category', 'description' => '', 'parent' => 0, 'count' => 1],
            ['term_taxonomy_id' => 203, 'term_id' => 102, 'taxonomy' => 'form-tags', 'description' => '', 'parent' => 0, 'count' => 1],
        ]);
        $database->table('term_relationships')->insert([
            ['object_id' => 11, 'term_taxonomy_id' => 201, 'term_order' => 3],
            ['object_id' => 11, 'term_taxonomy_id' => 202, 'term_order' => 0],
            ['object_id' => 12, 'term_taxonomy_id' => 201, 'term_order' => 7],
            ['object_id' => 12, 'term_taxonomy_id' => 203, 'term_order' => 0],
        ]);
    }

    protected function tearDown(): void {
        if ($this->originalResolver === null) {
            Model::unsetConnectionResolver();
        } else {
            Model::setConnectionResolver($this->originalResolver);
        }
        parent::tearDown();
    }

    public function test_posts_eager_load_taxonomy_records_and_terms_using_wordpress_keys(): void {
        $posts = Post::with('taxonomies.term')->orderBy('ID')->get();

        self::assertEqualsCanonicalizing([201, 202], $posts[0]->taxonomies->modelKeys());
        self::assertEqualsCanonicalizing([201, 203], $posts[1]->taxonomies->modelKeys());
        self::assertTrue($posts[2]->taxonomies->isEmpty());
        $tag = $posts[0]->taxonomies->firstWhere('taxonomy', 'form-tags');
        self::assertSame(101, $tag->term->getKey());
        self::assertSame('Contact', $tag->term->name);
        self::assertSame('contact', $tag->term->slug);
        self::assertSame(3, $tag->pivot->term_order);

        $form = Form::findOrFail(11);
        self::assertSame([201], $form->taxonomies()->where('taxonomy', 'form-tags')->get()->modelKeys());
    }

    public function test_taxonomies_and_terms_resolve_their_reverse_relationships(): void {
        $taxonomy = Taxonomy::with('posts')->findOrFail(201);
        self::assertEqualsCanonicalizing([11, 12], $taxonomy->posts->modelKeys());
        self::assertSame(7, $taxonomy->posts->firstWhere('ID', 12)->pivot->term_order);
        self::assertSame([11], Taxonomy::findOrFail(202)->posts->modelKeys());
        self::assertEqualsCanonicalizing([201, 202], Term::findOrFail(101)->taxonomies->modelKeys());
    }

    public function test_models_persist_wordpress_defaults_without_timestamp_columns(): void {
        $term = Term::create(['name' => 'New Tag', 'slug' => 'new-tag']);
        $taxonomy = Taxonomy::create(['term_id' => $term->getKey(), 'taxonomy' => 'form-tags']);

        self::assertSame('New Tag', $taxonomy->fresh()->term->name);
        self::assertSame(0, $term->fresh()->term_group);
        self::assertSame('', $taxonomy->fresh()->description);
        self::assertSame(0, $taxonomy->fresh()->parent);
        self::assertSame(0, $taxonomy->fresh()->count);
    }
}
