<?php

declare(strict_types=1);

namespace Best\Blueprint\PhpStan;

use Best\Blueprint\Architecture\ModuleBoundaryPolicy;
use PhpParser\Node;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\IdentifierRuleError;
use PHPStan\Rules\RuleErrorBuilder;

abstract class ModuleBoundaryRuleBase
{
    public function __construct(protected readonly ModuleBoundaryPolicy $policy) {}

    /**
     * @return list<IdentifierRuleError>
     */
    protected function errorsForClassNames(Scope $scope, int $line, string ...$classNames): array
    {
        $owner = $this->policy->ownerModuleFromAbsolutePath($scope->getFile());

        if ($owner === null) {
            return [];
        }

        $errors = [];

        foreach ($classNames as $className) {
            $reason = $this->policy->violationReason($owner, $className);

            if ($reason === null) {
                continue;
            }

            $errors[] = RuleErrorBuilder::message($reason)->identifier('blueprint.moduleBoundary')->line($line)->build();
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    protected function objectClassNames(Scope $scope, Node\Expr $node): array
    {
        return $scope->getType($node)->getObjectClassNames();
    }

    /**
     * @return list<string>
     */
    protected function classNamesFromName(Scope $scope, Name $name): array
    {
        return [$scope->resolveName($name)];
    }
}
