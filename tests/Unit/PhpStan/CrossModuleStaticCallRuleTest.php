<?php

declare(strict_types=1);

namespace Best\Blueprint\Tests\Unit\PhpStan;

use Best\Blueprint\Architecture\ModuleBoundaryPolicy;
use Best\Blueprint\PhpStan\CrossModuleStaticCallRule;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;

final class CrossModuleStaticCallRuleTest extends RuleTestCase
{
    public function test_cross_module_static_call(): void
    {
        $this->analyse([dirname(__DIR__, 2).'/Fixtures/module-boundaries/app/Modules/Orders/Actions/CrossModuleStatic.php'], [
            [
                'Cross-module Models reference to App\Modules\Inventory\Models\Product is not allowed; use Shared contracts, public Actions/Queries, or Events.',
                13,
            ],
        ]);
    }

    protected function getRule(): Rule
    {
        return new CrossModuleStaticCallRule(new ModuleBoundaryPolicy(
            'App\\Modules\\',
            'app/Modules',
            ['Controllers', 'Requests', 'Models', 'Services', 'Policies', 'Resources', 'Http', 'Data'],
            ['Actions', 'Queries', 'Events', 'Jobs'],
        ));
    }
}
