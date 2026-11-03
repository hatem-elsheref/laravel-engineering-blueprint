<?php

declare(strict_types=1);

namespace Best\Blueprint\PhpStan;

use Best\Blueprint\Architecture\ModuleBoundaryPolicy;
use Best\Blueprint\Support\Modules;

final class ModuleBoundaryPolicyFactory
{
    public static function fromApplication(Modules $modules): ModuleBoundaryPolicy
    {
        return self::create(
            $modules->namespace(),
            (string) config('blueprint.module_path', 'app/Modules'),
            (array) config('blueprint.architecture.forbidden_cross_module_segments', []),
            (array) config('blueprint.architecture.allowed_cross_module_segments', []),
        );
    }

    /**
     * @param  list<string>  $forbiddenSegments
     * @param  list<string>  $allowedSegments
     */
    public static function create(
        string $moduleNamespace,
        string $modulesPathContains,
        array $forbiddenSegments,
        array $allowedSegments,
    ): ModuleBoundaryPolicy {
        return new ModuleBoundaryPolicy(
            rtrim($moduleNamespace, '\\').'\\',
            str_replace('\\', '/', $modulesPathContains),
            $forbiddenSegments,
            $allowedSegments,
        );
    }
}
