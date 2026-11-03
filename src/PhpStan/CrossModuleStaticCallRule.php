<?php

declare(strict_types=1);

namespace Best\Blueprint\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** @implements Rule<StaticCall> */
final class CrossModuleStaticCallRule extends ModuleBoundaryRuleBase implements Rule
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var StaticCall $node */
        if (! $node->class instanceof Name) {
            return [];
        }

        return $this->errorsForClassNames($scope, $node->getStartLine(), ...$this->classNamesFromName($scope, $node->class));
    }
}
