<?php

use Best\Blueprint\Support\PhpSource;

it('finds a declared class in a namespace', function () {
    expect(PhpSource::declares("<?php\n\nnamespace App\\M;\n\nfinal class Provider {}\n", 'App\M', 'Provider'))->toBeTrue();
});

it('ignores commented-out declarations', function (string $code) {
    expect(PhpSource::declares($code, 'App\M', 'Provider'))->toBeFalse();
})->with([
    "<?php\n\nnamespace App\\M;\n\n// class Provider\nfinal class ModuleServiceProvider {}\n",
    "<?php\n\n// namespace App\\M;\nnamespace Other;\n\nclass Provider {}\n",
    "<?php\n\nnamespace App\\M;\n\n/* class Provider */\nclass Thing {}\n",
    "<?php\n\nnamespace App\\M;\n\n/** class Provider */\nclass Thing {}\n",
]);

it('ignores class names in strings and ::class', function () {
    expect(PhpSource::declares("<?php\n\nnamespace App\\M;\n\n\$x = 'class Provider';\n\$y = Provider::class;\n", 'App\M', 'Provider'))->toBeFalse();
});
