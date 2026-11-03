<?php

namespace Best\Blueprint\Support;

use Illuminate\Filesystem\Filesystem;

class ConfigWriter
{
    /**
     * A top-level (one indent level) 'profile' key whose value is null or a quoted string literal.
     */
    private const PROFILE_LINE = '/^(?<prefix>(?:    |\t)\'profile\'\s*=>\s*)(?<value>null|\'[^\'\n]*\'|"[^"\n]*")(?=\s*,)/m';

    public function __construct(private readonly Filesystem $files) {}

    public function publish(string $target): void
    {
        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copy(__DIR__.'/../../config/blueprint.php', $target);
    }

    public function setProfile(string $path, Profile $profile): ProfileWrite
    {
        $contents = $this->files->get($path);

        if (preg_match_all(self::PROFILE_LINE, $contents, $matches) !== 1) {
            return new ProfileWrite(ProfileWrite::UNRECORDABLE);
        }

        $raw = $matches['value'][0];
        $old = $raw === 'null' ? null : substr($raw, 1, -1);

        if ($old === $profile->value) {
            return new ProfileWrite(ProfileWrite::UNCHANGED, $old);
        }

        $updated = preg_replace_callback(
            self::PROFILE_LINE,
            fn (array $m) => $m['prefix']."'{$profile->value}'",
            $contents,
        );

        $this->files->put($path, $updated);

        return new ProfileWrite(ProfileWrite::UPDATED, $old);
    }
}
