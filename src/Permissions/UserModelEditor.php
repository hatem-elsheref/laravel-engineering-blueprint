<?php

namespace Best\Blueprint\Permissions;

/**
 * Adds spatie's HasRoles trait to a User model's source.
 */
final class UserModelEditor
{
    private const IMPORT = 'Spatie\Permission\Traits\HasRoles';

    /**
     * The edited source, unchanged when spatie's HasRoles is already used, or null when the model is left to the
     * user: no in-class `use A, B;` trait line to extend, or a HasRoles that isn't spatie's (imported from
     * elsewhere, or a trait of that name the model already uses without importing spatie's).
     */
    public static function addHasRoles(string $source): ?string
    {
        $imported = self::imports($source)['hasroles'] ?? null;

        // The short name is already imported from another class.
        if ($imported !== null && $imported !== self::IMPORT) {
            return null;
        }

        foreach (self::traitNames($source) as $trait) {
            if (strcasecmp($trait, '\\'.self::IMPORT) === 0) {
                return $source;
            }

            // The short name is spatie's only through its import; any other HasRoles is someone else's trait.
            if (strcasecmp($trait, 'HasRoles') === 0) {
                return $imported === self::IMPORT ? $source : null;
            }

            if (str_ends_with(strtolower($trait), '\\hasroles')) {
                return null;
            }
        }

        // First indented `use Foo, Bar;` (an in-class trait line; imports are not indented).
        $edited = preg_replace('/^([ \t]+use[ \t]+[A-Za-z_\\\\][^;{]*?)[ \t]*;/m', '$1, HasRoles;', $source, 1, $count);

        if ($edited === null || $count === 0) {
            return null;
        }

        return $imported === self::IMPORT ? $edited : self::addImport($edited);
    }

    /**
     * Class imports (not indented, so not traits) as lowercased alias => class, group imports expanded.
     *
     * @return array<string, string>
     */
    private static function imports(string $source): array
    {
        preg_match_all('/^use[ \t]+(?!function\b|const\b)([^;]+);/m', $source, $matches);
        $imports = [];

        foreach ($matches[1] as $body) {
            $prefix = '';

            if (preg_match('/^([^{]*)\{([^}]*)\}\s*$/', $body, $group) === 1) {
                [$prefix, $body] = [trim($group[1]), $group[2]];
            }

            foreach (array_filter(array_map('trim', explode(',', $body))) as $item) {
                $parts = preg_split('/\s+as\s+/i', $item) ?: [$item];
                $class = ltrim($prefix.trim($parts[0]), '\\');
                $alias = trim($parts[1] ?? substr((string) strrchr('\\'.$class, '\\'), 1));
                $imports[strtolower($alias)] = $class;
            }
        }

        return $imports;
    }

    /**
     * Trait names on in-class `use A, B;` lines, as written.
     *
     * @return list<string>
     */
    private static function traitNames(string $source): array
    {
        preg_match_all('/^[ \t]+use[ \t]+([^;{]+)[;{]/m', $source, $matches);
        $names = [];

        foreach ($matches[1] as $line) {
            foreach (array_filter(array_map('trim', explode(',', $line))) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private static function addImport(string $source): string
    {
        $line = 'use '.self::IMPORT.';';

        if (preg_match_all('/^use\s+([^;]+);$/m', $source, $matches, PREG_OFFSET_CAPTURE) === 0) {
            return (string) preg_replace('/^(namespace [^;]+;\R)/m', "$1\n{$line}\n", $source, 1);
        }

        // Insert before the first import that sorts after ours, else after the last import.
        foreach ($matches[0] as $i => [$text, $offset]) {
            if (strcasecmp(trim($matches[1][$i][0]), self::IMPORT) > 0 && ! str_starts_with($matches[1][$i][0], 'function ') && ! str_starts_with($matches[1][$i][0], 'const ')) {
                return substr($source, 0, $offset).$line."\n".substr($source, $offset);
            }
        }

        [$text, $offset] = end($matches[0]);

        return substr($source, 0, $offset + strlen($text))."\n".$line.substr($source, $offset + strlen($text));
    }
}
