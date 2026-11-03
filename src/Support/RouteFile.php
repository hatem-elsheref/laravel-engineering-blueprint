<?php

namespace Best\Blueprint\Support;

use RuntimeException;

final class RouteFile
{
    /**
     * Import the controller (in sorted position) and append a route line, keeping the file's line endings.
     *
     * @throws RuntimeException when there is no place to put the import
     */
    public static function addResource(string $contents, string $controllerClass, string $routeLine): string
    {
        $eol = str_contains($contents, "\r\n") ? "\r\n" : "\n";
        $contents = str_replace("\r\n", "\n", $contents);
        $use = "use {$controllerClass};\n";

        if (preg_match_all('/^use ([^;\n]+);[ \t]*\n/m', $contents, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            $insertAt = null;

            foreach ($matches as $match) {
                if ($insertAt === null && strcasecmp($match[1][0], $controllerClass) > 0) {
                    $insertAt = $match[0][1];
                }
            }

            $last = end($matches);
            $contents = substr_replace($contents, $use, $insertAt ?? $last[0][1] + strlen($last[0][0]), 0);
        } elseif (preg_match('/declare\(strict_types=1\);[ \t]*\n/', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $contents = substr_replace($contents, "\n".$use, $match[0][1] + strlen($match[0][0]), 0);
        } elseif (preg_match('/^<\?php[ \t]*\n/', $contents, $match)) {
            $contents = substr_replace($contents, "\n".$use, strlen($match[0]), 0);
        } else {
            throw new RuntimeException('no place to add the controller import in routes/api.php');
        }

        $contents = rtrim($contents)."\n\n".rtrim($routeLine)."\n";

        return str_replace("\n", $eol, $contents);
    }
}
