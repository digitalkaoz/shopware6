<?php declare(strict_types=1);

namespace Shopware\Context\Rule\CalculatedCart;

use Shopware\Context\Exception\InvalidMatchContext;
use Shopware\Context\MatchContext\CalculatedLineItemMatchContext;
use Shopware\Context\MatchContext\CartRuleMatchContext;
use Shopware\Context\MatchContext\RuleMatchContext;
use Shopware\Context\Rule\Container\Container;
use Shopware\Context\Rule\Match;

class LineItemWrapperRule
{
    /**
     * @var Container
     */
    private $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function match(
        RuleMatchContext $matchContext
    ): Match {
        if ($matchContext instanceof CalculatedLineItemMatchContext) {
            return $this->container->match($matchContext);
        }

        if (!$matchContext instanceof CartRuleMatchContext) {
            return new Match(false, ['Invalid match context. CartRuleMatchContext required.']);
        }

        foreach ($matchContext->getCalculatedCart()->getCalculatedLineItems() as $lineItem) {
            $context = new CalculatedLineItemMatchContext($lineItem, $matchContext->getContext());
            $match = $this->container->match($context);
            if ($match->matches()) {
                return $match;
            }
        }
    }
}
