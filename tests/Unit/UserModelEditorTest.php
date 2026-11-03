<?php

use Best\Blueprint\Permissions\UserModelEditor;
use Symfony\Component\Process\Process;

function lintSource(string $source): int
{
    $file = tempnam(sys_get_temp_dir(), 'leb-user').'.php';
    file_put_contents($file, $source);
    $lint = new Process([PHP_BINARY, '-l', $file]);
    $lint->run();
    unlink($file);

    return (int) $lint->getExitCode();
}

it('adds HasRoles to the stock user model', function (string $fixture) {
    $source = (string) file_get_contents(__DIR__."/../Fixtures/user-models/{$fixture}.php.txt");
    $result = UserModelEditor::addHasRoles($source);

    expect($result)->toContain('use HasFactory, Notifiable, HasRoles;')
        ->toContain('use Spatie\Permission\Traits\HasRoles;')
        ->and(lintSource((string) $result))->toBe(0);

    // import sorted into the block: after Notifications, before the class
    expect(strpos((string) $result, 'use Illuminate\Notifications\Notifiable;'))
        ->toBeLessThan(strpos((string) $result, 'use Spatie\Permission\Traits\HasRoles;'));
})->with(['laravel-12', 'laravel-13']);

it('leaves a model that already uses HasRoles unchanged', function () {
    $source = (string) file_get_contents(__DIR__.'/../Fixtures/user-models/laravel-12.php.txt');
    $once = (string) UserModelEditor::addHasRoles($source);

    expect(UserModelEditor::addHasRoles($once))->toBe($once);
});

it('returns null when there is no trait line', function () {
    $source = "<?php\n\nnamespace App\\Models;\n\nuse Illuminate\\Foundation\\Auth\\User as Authenticatable;\n\nclass User extends Authenticatable\n{\n}\n";

    expect(UserModelEditor::addHasRoles($source))->toBeNull();
});

it('adds the trait when only the import exists, without duplicating it', function () {
    $source = (string) file_get_contents(__DIR__.'/../Fixtures/user-models/laravel-12.php.txt');
    $source = str_replace("use Illuminate\\Notifications\\Notifiable;\n", "use Illuminate\\Notifications\\Notifiable;\nuse Spatie\\Permission\\Traits\\HasRoles;\n", $source);
    $result = (string) UserModelEditor::addHasRoles($source);

    expect($result)->toContain('use HasFactory, Notifiable, HasRoles;')
        ->and(substr_count($result, 'use Spatie\Permission\Traits\HasRoles;'))->toBe(1)
        ->and(lintSource($result))->toBe(0);
});
