<?php

namespace Best\Blueprint\Support;

final class RouteFile
{
    /**
     * Import the controller and append an apiResource route.
     */
    public static function addResource(string $contents, string $controllerClass, string $uri, string $controllerShort): string
    {
        $use = "use {$controllerClass};\n";

        if (preg_match_all('/^use [^;\n]+;\n/m', $contents, $matches, PREG_OFFSET_CAPTURE)) {
            [$line, $offset] = end($matches[0]);
            $contents = substr_replace($contents, $use, $offset + strlen($line), 0);
        } elseif (preg_match('/^declare\(strict_types=1\);\n/m', $contents, $match, PREG_OFFSET_CAPTURE)) {
            $end = $match[0][1] + strlen($match[0][0]);
            $contents = substr_replace($contents, "\n".$use, $end, 0);
        } else {
            $contents = preg_replace('/^<\?php\n/', "<?php\n\n".$use, $contents, 1);
        }

        return rtrim($contents)."\n\nRoute::apiResource('{$uri}', {$controllerShort}::class);\n";
    }
}
