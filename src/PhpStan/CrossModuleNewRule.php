<?php

declare(strict_types=1);

namespace Best\Blueprint\PhpStan;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PHPStan\Analyser\Scope;
use PHPStan\Rules\Rule;

/** @implements Rule<New_> */
final class CrossModuleNewRule extends ModuleBoundaryRuleBase implements Rule
{
    public function getNodeType(): string
    {
        return New_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        /** @var New_ $node */

        return $this->errorsForClassNames($scope, $node->getStartLine(), ...$this->objectClassNames($scope, $node));
    }
}
