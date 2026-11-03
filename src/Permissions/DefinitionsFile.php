<?php

namespace Best\Blueprint\Permissions;

use PhpToken;

/**
 * Edits the source of a permission definitions class: the array literal returned by groups().
 */
final class DefinitionsFile
{
    /**
     * Adds '<group>' => [abilities] as the last entry of the array groups() returns.
     *
     * @param  list<string>  $abilities
     *
     * @throws InvalidPermissionDefinition when groups() doesn't return an array literal
     */
    public static function addGroup(string $source, string $group, array $abilities): string
    {
        [$tokens, $open, $close] = self::groupsArray($source);
        $returnIndent = self::indentOf($source, $tokens[self::previous($tokens, $open)]->pos);
        $entry = self::entry($group, $abilities);

        // Insert the entry on its own line before the closing bracket.
        $closePos = $tokens[$close]->pos;
        $lineStart = strrpos(substr($source, 0, $closePos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;

        $source = trim(substr($source, $lineStart, $closePos - $lineStart)) === ''
            ? substr_replace($source, "{$returnIndent}    {$entry}\n", $lineStart, 0)
            : substr_replace($source, "\n{$returnIndent}    {$entry}\n{$returnIndent}", $closePos, 0);

        // The previous last entry needs a trailing comma.
        $last = self::previous($tokens, $close);

        if ($last !== $open && $tokens[$last]->text !== ',') {
            $source = substr_replace($source, ',', $tokens[$last]->pos + strlen($tokens[$last]->text), 0);
        }

        return $source;
    }

    /**
     * Whether the array groups() returns has the key '<group>'.
     *
     * @throws InvalidPermissionDefinition when groups() doesn't return an array literal
     */
    public static function hasGroup(string $source, string $group): bool
    {
        [$tokens, $open, $close] = self::groupsArray($source);
        $depth = 0;

        for ($i = $open + 1; $i < $close; $i++) {
            $text = $tokens[$i]->text;
            $depth += match ($text) {
                '[', '(' => 1,
                ']', ')' => -1,
                default => 0,
            };

            if ($depth === 0 && $tokens[$i]->is(T_CONSTANT_ENCAPSED_STRING)
                && $tokens[self::next($tokens, $i)]->is(T_DOUBLE_ARROW)
                && substr($text, 1, -1) === $group) {
                return true;
            }
        }

        return false;
    }

    /**
     * The entry addGroup() inserts; also the line to add by hand when it can't.
     *
     * @param  list<string>  $abilities
     */
    public static function entry(string $group, array $abilities): string
    {
        return "'{$group}' => ['".implode("', '", $abilities)."'],";
    }

    /**
     * Tokens and the indexes of the opening and closing brackets of the array literal groups() returns.
     *
     * @return array{list<PhpToken>, int, int}
     */
    private static function groupsArray(string $source): array
    {
        $tokens = PhpToken::tokenize($source);
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $next = self::next($tokens, $i);

            if (! $tokens[$i]->is(T_FUNCTION) || ! isset($tokens[$next]) || ! $tokens[$next]->is(T_STRING) || $tokens[$next]->text !== 'groups') {
                continue;
            }

            for ($j = $next; $j < $count && ! $tokens[$j]->is(T_RETURN); $j++);

            $open = $j < $count ? self::next($tokens, $j) : $count;

            if ($open >= $count || $tokens[$open]->text !== '[') {
                break;
            }

            $depth = 0;

            for ($k = $open; $k < $count; $k++) {
                $depth += match ($tokens[$k]->text) {
                    '[', '(' => 1,
                    ']', ')' => -1,
                    default => 0,
                };

                if ($depth === 0) {
                    $semicolon = self::next($tokens, $k);

                    if ($tokens[$k]->text === ']' && $semicolon < $count && $tokens[$semicolon]->text === ';') {
                        return [$tokens, $open, $k];
                    }

                    break 2;
                }
            }

            break;
        }

        throw new InvalidPermissionDefinition('groups() must return an array literal: return [ ... ];');
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function next(array $tokens, int $index): int
    {
        do {
            $index++;
        } while (isset($tokens[$index]) && $tokens[$index]->isIgnorable());

        return $index;
    }

    /**
     * @param  list<PhpToken>  $tokens
     */
    private static function previous(array $tokens, int $index): int
    {
        do {
            $index--;
        } while ($index > 0 && $tokens[$index]->isIgnorable());

        return $index;
    }

    private static function indentOf(string $source, int $pos): string
    {
        $lineStart = strrpos(substr($source, 0, $pos), "\n");
        $lineStart = $lineStart === false ? 0 : $lineStart + 1;
        preg_match('/^[ \t]*/', substr($source, $lineStart), $indent);

        return $indent[0];
    }
}
