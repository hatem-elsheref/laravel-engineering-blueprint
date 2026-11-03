<?php

use App\Models\User;
use App\Modules\Library\Models\Book;
use Best\Blueprint\Features\FeatureAdmin;
use Illuminate\Support\Facades\File;

// Module/entity names here must not be used by other test files: generated classes stay loaded for the run.
beforeEach(function () {
    require_once __DIR__.'/../Fixtures/User.php';
    require_once __DIR__.'/../Fixtures/UserFactory.php';
    spl_autoload_register(function (string $class): void {
        $roots = ['App\\Modules\\' => app_path('Modules/'), 'Database\\Factories\\Library\\' => database_path('factories/Library/')];

        foreach ($roots as $prefix => $dir) {
            if (str_starts_with($class, $prefix) && is_file($file = $dir.str_replace('\\', '/', substr($class, strlen($prefix))).'.php')) {
                require_once $file;
            }
        }
    });

    config()->set('blueprint.profile', 'saas');
    config()->set('blueprint.module_path', 'app/Modules');
    useTestFlags();
    $this->providersPath = app()->getBootstrapProvidersPath();
    $this->providers = file_get_contents($this->providersPath);

    $this->artisan('make:module', ['name' => 'Library', '--api' => true, '--web' => true])->assertExitCode(0);
    $this->artisan('make:crud', [
        'module' => 'Library', 'entity' => 'Book', '--api' => true, '--web' => true, '--feature' => 'orders',
        '--fields' => 'title:string,pages:int,price:?float,available:bool,tags:array,publishedAt:?Carbon\CarbonImmutable',
    ])->assertExitCode(0);

    $this->loadLaravelMigrations();
    createFlagTables();
    $this->artisan('migrate', ['--path' => glob(database_path('migrations/*_create_books_table.php'))[0], '--realpath' => true])->assertExitCode(0);
    app(FeatureAdmin::class)->enable('orders');
    app()->register('App\Modules\Library\Provider');
    app('router')->getRoutes()->refreshNameLookups();
    $this->user = User::factory()->create();
});

afterEach(function () {
    File::deleteDirectory(app_path('Modules'));
    File::cleanDirectory(database_path('migrations'));
    File::deleteDirectory(database_path('factories/Library'));
    File::deleteDirectory(base_path('tests/Feature/Modules'));
    File::deleteDirectory(resource_path('views/library'));
    File::deleteDirectory(resource_path('views/layouts'));
    file_put_contents($this->providersPath, $this->providers);
});

function bookForm(array $overrides = []): array
{
    return array_merge([
        'title' => 'Dune', 'pages' => '412', 'price' => '9.50', 'available' => '1',
        'tags' => ['sci-fi', '', 'classic'], 'published_at' => '1965-08-01T10:00',
    ], $overrides);
}

it('answers browsers with html and api clients with json', function () {
    $this->get('/books')->assertForbidden()->assertHeader('content-type', 'text/html; charset=utf-8');
    $this->getJson('/api/books')->assertForbidden()->assertJsonStructure(['message']);
    $this->get('/books/999')->assertForbidden();
    $this->actingAs($this->user)->get('/books/999')->assertNotFound();
    $this->actingAs($this->user)->getJson('/api/books/999')->assertNotFound()->assertJsonStructure(['message']);
});

it('creates through the form and shows the result', function () {
    $this->actingAs($this->user)->get('/books/create')->assertOk()->assertSee('name="tags[]"', false)->assertSee('type="datetime-local"', false);

    $response = $this->actingAs($this->user)->post('/books', bookForm())->assertSessionHasNoErrors();
    $book = Book::first();

    $response->assertRedirect(route('books.show', $book));
    expect($book->tags)->toBe(['sci-fi', 'classic'])
        ->and($book->available)->toBeTrue()
        ->and($book->pages)->toBe(412);

    $this->actingAs($this->user)->followingRedirects()->post('/books', bookForm(['title' => 'Emma']))->assertOk()->assertSee('Book created.')->assertSee('Emma');
});

it('sends validation errors and old input back to the form', function () {
    $this->actingAs($this->user)->from('/books/create')->post('/books', ['title' => 'Kept'])
        ->assertRedirect('/books/create')
        ->assertSessionHasErrors(['pages', 'available', 'tags'])
        ->assertSessionHasInput('title', 'Kept');

    $this->actingAs($this->user)->postJson('/api/books', [])->assertUnprocessable()->assertJsonValidationErrors(['title', 'pages', 'available', 'tags']);
});

it('updates through the form, including an unchecked box and blank array inputs', function () {
    $this->actingAs($this->user)->post('/books', bookForm());
    $book = Book::first();

    $this->actingAs($this->user)->get("/books/{$book->id}/edit")->assertOk()->assertSee('value="Dune"', false)->assertSee('value="1965-08-01T10:00"', false);

    $this->actingAs($this->user)->put("/books/{$book->id}", bookForm(['available' => '0', 'tags' => ['', ''], 'price' => null]))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('books.show', $book));

    expect($book->refresh()->available)->toBeFalse()->and($book->tags)->toBe([])->and($book->price)->toBeNull();
});

it('deletes through the form', function () {
    $this->actingAs($this->user)->post('/books', bookForm());
    $book = Book::first();

    $this->actingAs($this->user)->delete("/books/{$book->id}")->assertRedirect(route('books.index'))->assertSessionHas('status', 'Book deleted.');

    expect(Book::count())->toBe(0);
});

it('escapes user input on every page', function () {
    $this->actingAs($this->user)->post('/books', bookForm(['title' => '<script>alert(1)</script>']));
    $book = Book::first();

    foreach (['/books', "/books/{$book->id}", "/books/{$book->id}/edit"] as $url) {
        $this->actingAs($this->user)->get($url)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
    }
});

it('serves the same records to the api', function () {
    $this->actingAs($this->user)->post('/books', bookForm());

    $this->actingAs($this->user)->getJson('/api/books')->assertOk()->assertJsonPath('data.0.title', 'Dune');
    expect(route('api.books.index'))->toEndWith('/api/books')->and(route('books.index'))->toEndWith('/books');
});

it('blocks both sides when the feature is disabled', function () {
    app(FeatureAdmin::class)->disable('orders');
    app()->forgetScopedInstances();

    $this->actingAs($this->user)->get('/books')->assertForbidden();
    $this->actingAs($this->user)->getJson('/api/books')->assertForbidden();
});

it('rejects nested arrays instead of storing them', function () {
    $this->actingAs($this->user)->postJson('/api/books', array_merge(bookForm(), ['tags' => [['x'], ['k' => 'v']]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tags.0', 'tags.1']);
});

it('renders the pages in arabic, right to left', function () {
    config()->set('blueprint.locales', ['en', 'ar']);
    $this->actingAs($this->user)->post('/books', bookForm());
    $book = Book::first();

    $this->actingAs($this->user)->from("/books/{$book->id}")->get('/locale/ar')->assertRedirect("/books/{$book->id}");

    $this->actingAs($this->user)->get("/books/{$book->id}")
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertSee('lang="ar"', false)
        ->assertSee('تعديل')
        ->assertSee('العودة إلى القائمة')
        ->assertSee('href="http://localhost/locale/en"', false);

    $this->actingAs($this->user)->getJson('/api/books', ['Accept-Language' => 'en'])->assertOk();
    $this->actingAs($this->user)->get('/locale/en');
    $this->actingAs($this->user)->get('/books')->assertSee('dir="ltr"', false)->assertSee('View');
});
