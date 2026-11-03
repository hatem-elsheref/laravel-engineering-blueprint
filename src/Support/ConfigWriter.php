<?php

namespace Best\Blueprint\Support;

use Illuminate\Filesystem\Filesystem;

class ConfigWriter
{
    /**
     * A top-level (one indent level) 'profile' key whose value is null or a plain string literal
     * (single-quoted, or double-quoted without interpolation).
     */
    private const PROFILE_LINE = '/^(?<prefix>(?:    |\t)\'profile\'\s*=>\s*)(?<value>null|\'[^\'\n]*\'|"[^"\n$]*")(?=\s*,)/m';

    private const ROUTE_AUTH_NULL = "/(?<prefix>(?:    |\t)'route_auth'\\s*=>\\s*)null(?=\\s*,)/";

    private const ROUTE_AUTH_WEB_NULL = "/(?<prefix>(?:    |\t)'route_auth_web'\\s*=>\\s*)null(?=\\s*,)/";

    public function __construct(private readonly Filesystem $files) {}

    public function publish(string $target): void
    {
        $this->files->ensureDirectoryExists(dirname($target));
        $this->files->copy(__DIR__.'/../../config/blueprint.php', $target);
    }

    public function setProfile(string $path, Profile $profile): ProfileWrite
    {
        $contents = $this->files->get($path);
        $match = $this->profileMatch($contents);

        if ($match === null) {
            return new ProfileWrite(ProfileWrite::UNRECORDABLE);
        }

        [$offset, $prefix, $raw] = $match;
        $old = $raw === 'null' ? null : substr($raw, 1, -1);

        if ($old === $profile->value) {
            return new ProfileWrite(ProfileWrite::UNCHANGED, $old);
        }

        $this->files->put($path, substr_replace($contents, $prefix."'{$profile->value}'", $offset, strlen($prefix.$raw)));

        return new ProfileWrite(ProfileWrite::UPDATED, $old);
    }

    /**
     * The profile literal currently written in the file, or null when absent or not a plain literal.
     */
    public function recordedProfile(string $path): ?string
    {
        if (! $this->files->isFile($path)) {
            return null;
        }

        $match = $this->profileMatch($this->files->get($path));

        return $match === null || $match[2] === 'null' ? null : substr($match[2], 1, -1);
    }

    /** Sets route_auth / route_auth_web when still null (does not overwrite existing values). */
    public function applySecureRouteAuthDefaults(string $path): bool
    {
        $contents = $this->files->get($path);
        $updated = $contents;

        $updated = preg_replace(self::ROUTE_AUTH_NULL, "$1'sanctum'", $updated, 1) ?? $updated;
        $updated = preg_replace(self::ROUTE_AUTH_WEB_NULL, "$1'auth'", $updated, 1) ?? $updated;

        if ($updated === $contents) {
            return false;
        }

        $this->files->put($path, $updated);

        return true;
    }

    /**
     * The single profile line outside block comments: [offset, prefix, raw value], or null.
     *
     * @return array{int, string, string}|null
     */
    private function profileMatch(string $contents): ?array
    {
        preg_match_all('#/\*.*?\*/#s', $contents, $comments, PREG_OFFSET_CAPTURE);
        preg_match_all(self::PROFILE_LINE, $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        $found = array_values(array_filter($matches, function (array $match) use ($comments): bool {
            foreach ($comments[0] as [$text, $start]) {
                if ($match[0][1] >= $start && $match[0][1] < $start + strlen($text)) {
                    return false;
                }
            }

            return true;
        }));

        if (count($found) !== 1) {
            return null;
        }

        return [$found[0][0][1], $found[0]['prefix'][0], $found[0]['value'][0]];
    }
}
