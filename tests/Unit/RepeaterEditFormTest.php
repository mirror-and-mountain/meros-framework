<?php

namespace MM\Meros\Tests\Unit;

use Livewire\Component;
use Livewire\Livewire;
use MM\Meros\App\Components\Fields\Hidden;
use MM\Meros\App\Components\Fields\Repeater;
use MM\Meros\App\Components\Fields\Text;
use MM\Meros\App\Livewire\Concerns\InteractsWithForms;
use MM\Meros\Contracts\Features\Components\Form;
use MM\Meros\Facades\Components\Fields;
use MM\Meros\Facades\Components\Forms;
use MM\Meros\Testing\FeatureProvider;
use MM\Meros\Testing\TestCase;

final class RepeaterEditFormTest extends TestCase {
    private FeatureProvider $provider;

    protected function setUp(): void {
        parent::setUp();
        $this->createApplication([\Livewire\LivewireServiceProvider::class, \Carbon\Laravel\ServiceProvider::class]);
        $this->provider = new FeatureProvider($this->directory);
        foreach (['text' => Text::class, 'hidden' => Hidden::class, 'repeater' => Repeater::class] as $alias => $class) {
            Fields::checkout($this->provider)->register($class, $alias);
        }
        $this->app['view']->addNamespace('meros', dirname(__DIR__, 2) . '/src/resources/views');
    }

    private function makeForm(): Form {
        return Forms::checkout($this->provider)->make(function (Form $form) {
            $form->id('custom-form')->onSubmit('customSubmit')->useDefaultStyles();
            $form->field('repeater', function (Repeater $repeater) {
                $repeater->id('custom-repeater')->name('items');
                $repeater->field('text', ['name' => 'title']);
                $repeater->editForm(function (Form $editForm, array $row, array $value) {
                    $editForm->useDefaultStyles();
                    $editForm->field('text', ['name' => 'notes', 'label' => 'Notes: ' . count($value)]);
                    return $editForm;
                });
            });
        });
    }

    public function test_renderer_uses_callback_and_row_values_without_wordpress_ajax(): void {
        $form = $this->makeForm();
        $html = $form->renderRepeaterEditForm('items', 'items', [
            'title' => 'First',
            'form_data' => ['notes' => 'Saved <notes>'],
        ], [['title' => 'First'], ['title' => 'Second']]);

        self::assertStringContainsString('Saved &lt;notes&gt;', $html);
        self::assertStringContainsString('Notes: 2', $html);
        self::assertStringContainsString('data-onsubmit=meros_repeater_form_submit', $html);
    }

    public function test_renderer_supports_original_names_without_mutating_the_repeater(): void {
        $form = $this->makeForm();
        $html = $form->renderRepeaterEditForm('renamed_items', 'items');

        self::assertStringContainsString('data-name="renamed_items_edit_form"', $html);
        self::assertSame('items', $form->getFields(true)->first()->getName());
    }

    public function test_renderer_rejects_fields_outside_the_requested_form(): void {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to get repeater edit form.');
        $this->makeForm()->renderRepeaterEditForm('unknown', 'unknown');
    }

    public function test_renderer_rejects_callbacks_that_do_not_return_a_form(): void {
        $form = $this->makeForm();
        $form->getFields(true)->first()->editForm(static function (Form $editForm) {});

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('must return a Form instance');
        $form->renderRepeaterEditForm('items', 'items');
    }

    public function test_livewire_bridge_rejects_a_different_form_id(): void {
        RepeaterLoaderFixture::$factory = fn () => $this->makeForm();
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unable to locate the requested form.');
        (new RepeaterLoaderFixture())->renderRepeaterEditForm('other-form', 'items', 'items');
    }

    public function test_livewire_bridge_rebuilds_forms_without_rendering_the_component(): void {
        RepeaterLoaderFixture::$factory = fn () => $this->makeForm();
        RepeaterLoaderFixture::$renders = 0;
        $this->writeFile('fixture.blade.php', '<div>Custom form</div>');
        $this->app['view']->addNamespace('repeater-tests', $this->directory);
        Livewire::component('repeater-loader-fixture', RepeaterLoaderFixture::class);
        $html = Livewire::mount('repeater-loader-fixture');
        preg_match('/wire:snapshot="([^"]+)"/', $html, $matches);
        $snapshot = json_decode(html_entity_decode($matches[1], ENT_QUOTES), true, flags: JSON_THROW_ON_ERROR);
        $this->app['request']->headers->set('X-Livewire', 'true');

        [, $effects] = Livewire::update($snapshot, [], [[
            'method' => 'renderRepeaterEditForm',
            'params' => ['custom-form', 'items', 'items', ['form_data' => ['notes' => 'Livewire notes']], []],
        ]]);

        self::assertStringContainsString('Livewire notes', $effects['returns'][0]);
        self::assertArrayNotHasKey('html', $effects);
        self::assertSame(1, RepeaterLoaderFixture::$renders);
    }
}

final class RepeaterLoaderFixture extends Component {
    use InteractsWithForms;

    public static \Closure $factory;
    public static int $renders = 0;

    protected function makeMerosForm(string $formId): Form {
        return (self::$factory)();
    }

    public function render() {
        self::$renders++;
        return view('repeater-tests::fixture');
    }
}

namespace MM\Meros\Support;

function admin_url(string $path): string { return 'https://meros.test/wp-admin/' . $path; }
function is_user_logged_in(): bool { return false; }

namespace MM\Meros\Contracts\Features\Components;

function wp_unslash($value) { return is_array($value) ? array_map(__FUNCTION__, $value) : stripslashes($value); }
