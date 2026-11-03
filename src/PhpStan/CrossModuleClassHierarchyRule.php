<?php

declare(strict_types=1);

namespace Best\Blueprint\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** @implements Rule<Class_> */
final class CrossModuleClassHierarchyRule extends ModuleBoundaryRuleBase implements Rule
{
    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var Class_ $node */
        $errors = [];

        if ($node->extends instanceof Name) {
            $errors = [...$errors, ...$this->errorsForClassNames($scope, $node->getStartLine(), ...$this->classNamesFromName($scope, $node->extends))];
        }

        foreach ($node->implements as $implement) {
            $errors = [...$errors, ...$this->errorsForClassNames($scope, $node->getStartLine(), ...$this->classNamesFromName($scope, $implement))];
        }

        return $errors;
    }
}
