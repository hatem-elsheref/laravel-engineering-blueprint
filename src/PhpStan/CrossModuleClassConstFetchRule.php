<?php

declare(strict_types=1);

namespace Best\Blueprint\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** @implements Rule<ClassConstFetch> */
final class CrossModuleClassConstFetchRule extends ModuleBoundaryRuleBase implements Rule
{
    public function getNodeType(): string
    {
        return ClassConstFetch::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var ClassConstFetch $node */
        if (! $node->class instanceof Name) {
            return [];
        }

        return $this->errorsForClassNames($scope, $node->getStartLine(), ...$this->objectClassNames($scope, $node));
    }
}
