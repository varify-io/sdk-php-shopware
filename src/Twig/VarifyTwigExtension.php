<?php

declare(strict_types=1);

namespace Varify\Shopware\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use Varify\Shopware\VarifyRequestContext;

final class VarifyTwigExtension extends AbstractExtension
{
    public function __construct(private readonly VarifyRequestContext $context) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('varify_is', $this->is(...)),
            new TwigFunction('varify_is_original', $this->isOriginal(...)),
        ];
    }

    /**
     * Whether the visitor sees this variation. Reading it counts as an exposure.
     */
    public function is(int $experimentId, int $variationId): bool
    {
        return $experimentId > 0 && $this->context->current()->assignment($experimentId)->is($variationId);
    }

    /**
     * Whether the visitor sees the original. Reading it counts as an exposure.
     */
    public function isOriginal(int $experimentId): bool
    {
        return $experimentId < 1 || $this->context->current()->assignment($experimentId)->isOriginal();
    }
}
