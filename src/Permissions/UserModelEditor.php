<?php

namespace Best\Blueprint\Permissions;

/**
 * Adds spatie's HasRoles trait to a User model's source.
 */
final class UserModelEditor
{
    private const IMPORT = 'Spatie\Permission\Traits\HasRoles';

    /**
     * The edited source, unchanged when HasRoles is already used, or null when there is no
     * in-class `use A, B;` trait line to extend.
     */
    public static function addHasRoles(string $source): ?string
    {
        if (preg_match('/^[ \t]+use[ \t]+[^;{]*\bHasRoles\b/m', $source) === 1) {
            return $source;
        }

        // The short name is already imported from another class: leave the model to the user.
        if (preg_match_all('/^use[ \t]+([^;\n]*\bHasRoles)[ \t]*;/m', $source, $imports) > 0
            && array_diff(array_map('trim', $imports[1]), [self::IMPORT, '\\'.self::IMPORT]) !== []) {
            return null;
        }

        // First indented `use Foo, Bar;` (an in-class trait line; imports are not indented).
        $edited = preg_replace('/^([ \t]+use[ \t]+[A-Za-z_\\\\][^;{]*?)[ \t]*;/m', '$1, HasRoles;', $source, 1, $count);

        if ($edited === null || $count === 0) {
            return null;
        }

        return preg_match('/^use[ \t]+'.preg_quote(self::IMPORT, '/').'[ \t]*;/m', $edited) === 1 ? $edited : self::addImport($edited);
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
