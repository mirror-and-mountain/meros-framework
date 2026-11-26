<?php

namespace MM\Meros\Tests\Unit {

    use MM\Meros\Contracts\Features\Content\PostType;
    use MM\Meros\Contracts\Features\Content\Taxonomy;
    use MM\Meros\App\Content\Form;
    use MM\Meros\App\Content\FormTags;
    use MM\Meros\App\Content\FormTemplate;
    use MM\Meros\Facades\Content\PostTypes;
    use MM\Meros\Facades\Content\Taxonomies;
    use MM\Meros\Foundations\Testing\WordPressState;
    use MM\Meros\Testing\FeatureProvider;
    use MM\Meros\Testing\TestCase;

    final class TaxonomyTest extends TestCase {
        protected function setUp(): void {
            parent::setUp();
            WordPressState::$hooks = [];
            TaxonomyWordPressState::$taxonomies = [];
            TaxonomyWordPressState::$postTypes = ['post' => [], 'page' => []];
            TaxonomyWordPressState::$registrations = [];
            TaxonomyWordPressState::$events = [];
            TaxonomyWordPressState::$failRegistration = false;
        }

        public function test_standalone_taxonomy_registration_preserves_configuration_and_ownership(): void {
            $provider = new FeatureProvider($this->directory);
            Taxonomies::checkout($provider)->register(Genre::class, 'genres');
            $taxonomy = Taxonomies::checkout($provider)->makeFrom('genres');
            $taxonomy->description('Book genres')->postTypes(['post', 'page'])->postType('post');

            do_action('init');
            $taxonomy->register();

            $this->assertSame($provider, $taxonomy->getProvider());
            $this->assertSame($taxonomy, Taxonomies::get('genre'));
            $this->assertSame(['post', 'page'], TaxonomyWordPressState::$taxonomies['genre']['postTypes']);
            $this->assertSame(['genre'], TaxonomyWordPressState::$registrations);
            $args = TaxonomyWordPressState::$taxonomies['genre']['args'];
            $this->assertSame('Genres', $args['labels']['name']);
            $this->assertSame('Book genres', $args['description']);
            $this->assertTrue($args['hierarchical']);
            $this->assertTrue($args['show_in_rest']);
        }

        public function test_callback_taxonomy_uses_the_inferred_post_type_handle_and_preserves_raw_taxonomies(): void {
            $provider = new FeatureProvider($this->directory);
            $postType = PostTypes::checkout($provider)->makeFrom(Book::class);

            do_action('init');

            $this->assertSame(['book'], TaxonomyWordPressState::$taxonomies['reading-level']['postTypes']);
            $this->assertSame(['category'], TaxonomyWordPressState::$postTypes['book']['taxonomies']);
            $this->assertSame(['title', 'editor'], TaxonomyWordPressState::$postTypes['book']['supports']);
            $this->assertSame($provider, Taxonomies::get('reading-level')->getProvider());
            $this->assertSame('book', $postType->getHandle());
        }

        public function test_label_changes_refresh_generated_labels_without_losing_overrides(): void {
            $provider = new FeatureProvider($this->directory);
            $taxonomy = Taxonomies::checkout($provider)->make(function (Taxonomy $taxonomy) {
                $taxonomy->handle('genre')->labels(['menu_name' => 'Browse Genres']);
                $taxonomy->label('Genre');
            });
            $taxonomy->label('Book Genre', 'Book Genres');
            $taxonomy->register();

            $labels = TaxonomyWordPressState::$taxonomies['genre']['args']['labels'];
            $this->assertSame('Book Genres', $labels['name']);
            $this->assertSame('Add New Book Genre', $labels['add_new_item']);
            $this->assertSame('Browse Genres', $labels['menu_name']);
        }

        public function test_class_taxonomies_are_reused_and_associate_when_registered_before_post_types(): void {
            $provider = new FeatureProvider($this->directory);
            $first = PostTypes::checkout($provider)->make(['handle' => 'book']);
            $second = PostTypes::checkout($provider)->make(['handle' => 'film']);
            $taxonomy = $first->taxonomy(Genre::class);
            $this->assertSame($taxonomy, $second->taxonomy(Genre::class));
            $first->taxonomy(Genre::class);

            $taxonomy->register();
            $first->register();
            $second->register();

            $this->assertSame(['book', 'film'], $taxonomy->getPostTypes());
            $this->assertSame(['book', 'film'], TaxonomyWordPressState::$taxonomies['genre']['postTypes']);
            $this->assertSame(['genre'], TaxonomyWordPressState::$registrations);
        }

        public function test_existing_core_taxonomy_can_be_associated_after_post_type_registration(): void {
            TaxonomyWordPressState::$taxonomies['category'] = ['postTypes' => ['post'], 'args' => ['public' => true]];
            $provider = new FeatureProvider($this->directory);
            $postType = PostTypes::checkout($provider)->make(['handle' => 'book']);
            $postType->register();
            $taxonomy = $postType->taxonomy(function (Taxonomy $taxonomy) {
                $taxonomy->handle('category')->core()->private();
            });
            $taxonomy->register();

            $this->assertSame(['post', 'book'], TaxonomyWordPressState::$taxonomies['category']['postTypes']);
            $this->assertSame(['public' => true], TaxonomyWordPressState::$taxonomies['category']['args']);
            $this->assertSame([], TaxonomyWordPressState::$registrations);
        }

        public function test_taxonomy_hook_runs_after_post_types_even_when_taxonomy_is_created_first(): void {
            $provider = new FeatureProvider($this->directory);
            $taxonomy = Taxonomies::checkout($provider)->makeFrom(InferredTaxonomy::class);
            PostTypes::checkout($provider)->make(function (PostType $postType) {
                $postType->handle('book');
                $postType->taxonomy(InferredTaxonomy::class);
            });
            do_action('init');

            $this->assertSame('inferredtaxonomy', $taxonomy->getHandle());
            $this->assertSame(['book'], TaxonomyWordPressState::$taxonomies[$taxonomy->getHandle()]['postTypes']);
            $this->assertSame(['post:book', 'taxonomy:inferredtaxonomy'], TaxonomyWordPressState::$events);
        }

        /** @dataProvider invalidHandles */
        public function test_invalid_taxonomy_handles_fail_explicitly(string $handle): void {
            $provider = new FeatureProvider($this->directory);
            $taxonomy = Taxonomies::checkout($provider)->make(['handle' => $handle]);
            $this->expectException(\InvalidArgumentException::class);
            $taxonomy->register();
        }

        public static function invalidHandles(): array {
            return [[''], [str_repeat('a', 33)], ['invalid handle']];
        }

        public function test_wordpress_registration_errors_are_reported(): void {
            $provider = new FeatureProvider($this->directory);
            $taxonomy = Taxonomies::checkout($provider)->make(['handle' => str_repeat('a', 32)]);
            TaxonomyWordPressState::$failRegistration = true;
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Registration rejected');
            $taxonomy->register();
        }

        public function test_taxonomy_rejects_feature_classes_outside_the_contract(): void {
            $provider = new FeatureProvider($this->directory);
            $postType = PostTypes::checkout($provider)->make(['handle' => 'book']);
            $this->expectException(\InvalidArgumentException::class);
            $postType->taxonomy(Book::class);
        }

        public function test_forms_and_form_templates_share_the_non_hierarchical_form_tags_taxonomy(): void {
            $provider = new FeatureProvider($this->directory);
            $form = PostTypes::checkout($provider)->makeFrom(Form::class);
            $template = PostTypes::checkout($provider)->makeFrom(FormTemplate::class);

            do_action('init');

            $taxonomy = Taxonomies::get('form-tags');
            $this->assertInstanceOf(FormTags::class, $taxonomy);
            $this->assertSame(['form', 'form-template'], TaxonomyWordPressState::$taxonomies['form-tags']['postTypes']);
            $this->assertFalse(TaxonomyWordPressState::$taxonomies['form-tags']['args']['hierarchical']);
            $this->assertTrue(TaxonomyWordPressState::$taxonomies['form-tags']['args']['show_in_rest']);
            $this->assertTrue(TaxonomyWordPressState::$taxonomies['form-tags']['args']['show_admin_column']);
            $this->assertSame(['form-tags'], TaxonomyWordPressState::$registrations);
            $this->assertSame($taxonomy, $form->taxonomy(FormTags::class));
            $this->assertSame($taxonomy, $template->taxonomy(FormTags::class));
            $this->assertSame('meros-forms', TaxonomyWordPressState::$postTypes['form']['show_in_menu']);
            $this->assertSame('meros-forms', TaxonomyWordPressState::$postTypes['form-template']['show_in_menu']);
            $this->assertFalse(TaxonomyWordPressState::$postTypes['form-template']['public']);
            $this->assertTrue(TaxonomyWordPressState::$postTypes['form-template']['show_ui']);
            $this->assertFalse(TaxonomyWordPressState::$taxonomies['form-tags']['args']['show_in_menu']);
        }
    }

    class Genre extends Taxonomy {
        protected function configure(): void {
            $this->handle('genre')->label('Genre')->public()->hierarchical()->showInRest();
        }
    }

    class InferredTaxonomy extends Taxonomy {}

    class Book extends PostType {
        protected function configure(): void {
            $this->taxonomies(['category']);
            $this->taxonomy(function (Taxonomy $taxonomy) {
                $taxonomy->handle('reading-level')->label('Reading Level')->showInRest();
            });
        }
    }

    final class TaxonomyWordPressState {
        public static array $taxonomies = [];
        public static array $postTypes = [];
        public static array $registrations = [];
        public static array $events = [];
        public static bool $failRegistration = false;
    }

    final class TaxonomyRegistrationError {
        public function get_error_message(): string { return 'Registration rejected'; }
    }
}

namespace MM\Meros\Contracts\Features\Content {
    use MM\Meros\Tests\Unit\TaxonomyRegistrationError;
    use MM\Meros\Tests\Unit\TaxonomyWordPressState;

    function taxonomy_exists(string $handle): bool {
        return isset(TaxonomyWordPressState::$taxonomies[$handle]);
    }

    function post_type_exists(string $handle): bool {
        return isset(TaxonomyWordPressState::$postTypes[$handle]);
    }

    function get_post_types(): array {
        return array_keys(TaxonomyWordPressState::$postTypes);
    }

    function register_post_type(string $handle, array $args): void {
        TaxonomyWordPressState::$events[] = 'post:' . $handle;
        TaxonomyWordPressState::$postTypes[$handle] = $args;
    }

    function register_taxonomy(string $handle, array $postTypes, array $args): object {
        if (TaxonomyWordPressState::$failRegistration) {
            return new TaxonomyRegistrationError();
        }
        TaxonomyWordPressState::$registrations[] = $handle;
        TaxonomyWordPressState::$events[] = 'taxonomy:' . $handle;
        TaxonomyWordPressState::$taxonomies[$handle] = ['postTypes' => $postTypes, 'args' => $args];
        return (object) ['name' => $handle];
    }

    function register_taxonomy_for_object_type(string $handle, string $postType): bool {
        if (!taxonomy_exists($handle) || !post_type_exists($postType)) {
            return false;
        }
        if (!in_array($postType, TaxonomyWordPressState::$taxonomies[$handle]['postTypes'], true)) {
            TaxonomyWordPressState::$taxonomies[$handle]['postTypes'][] = $postType;
        }
        return true;
    }

    function is_wp_error(mixed $result): bool {
        return $result instanceof TaxonomyRegistrationError;
    }
}
