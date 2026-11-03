<?php

namespace Best\Blueprint\Support;

final class PhpNames
{
    /**
     * Keywords and reserved type names that cannot be used as a class name.
     */
    private const RESERVED = [
        '__halt_compiler', 'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
        'clone', 'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else', 'elseif', 'empty',
        'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch', 'endwhile', 'enum', 'eval', 'exit',
        'extends', 'final', 'finally', 'fn', 'for', 'foreach', 'function', 'global', 'goto', 'if',
        'implements', 'include', 'include_once', 'instanceof', 'insteadof', 'interface', 'isset', 'list',
        'match', 'namespace', 'new', 'or', 'print', 'private', 'protected', 'public', 'readonly', 'require',
        'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset', 'use', 'var', 'while',
        'xor', 'yield', 'bool', 'false', 'float', 'int', 'iterable', 'mixed', 'never', 'null', 'numeric',
        'object', 'parent', 'resource', 'self', 'string', 'true', 'void',
    ];

    public static function isReserved(string $name): bool
    {
        return in_array(strtolower($name), self::RESERVED, true);
    }
}
