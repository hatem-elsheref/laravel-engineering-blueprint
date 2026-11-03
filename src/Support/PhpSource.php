<?php

namespace Best\Blueprint\Support;

final class PhpSource
{
    /**
     * Whether the code really declares $class inside $namespace (comments and strings don't count).
     */
    public static function declares(string $code, string $namespace, string $class): bool
    {
        $tokens = array_values(array_filter(
            token_get_all($code),
            fn ($token) => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true),
        ));

        $current = '';

        foreach ($tokens as $i => $token) {
            if (! is_array($token)) {
                continue;
            }

            if ($token[0] === T_NAMESPACE && isset($tokens[$i + 1]) && is_array($tokens[$i + 1])) {
                $current = $tokens[$i + 1][1];
            }

            $previous = $tokens[$i - 1] ?? null;
            $afterDoubleColon = is_array($previous) && $previous[0] === T_DOUBLE_COLON;

            if ($token[0] === T_CLASS && ! $afterDoubleColon && isset($tokens[$i + 1]) && is_array($tokens[$i + 1])
                && $tokens[$i + 1][0] === T_STRING && $tokens[$i + 1][1] === $class && $current === $namespace) {
                return true;
            }
        }

        return false;
    }
}
