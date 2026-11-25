<?php

declare(strict_types=1);

namespace Varify\Shopware\Tests\Twig;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Varify\Shopware\Tests\Support\Plugin;
use Varify\Shopware\Twig\VarifyTwigExtension;

#[CoversClass(VarifyTwigExtension::class)]
#[Group('unit')]
final class VarifyTwigExtensionTest extends TestCase
{
    #[Test]
    public function rendersTheVisitorsVariation(): void
    {
        $twig = self::twig(Plugin::RETURNING_COOKIE);

        $html = $twig->render('page');

        static::assertSame('variation 48838 | original: no | unknown: original', $html);
    }

    #[Test]
    public function recordsWhatTheTemplateReadAsExposures(): void
    {
        $page = Plugin::page(Plugin::RETURNING_COOKIE);
        $context = Plugin::context(Plugin::stack($page));
        $twig = new Environment(new ArrayLoader(['page' => '{{ varify_is(48812, 48838) ? "yes" }}']));
        $twig->addExtension(new VarifyTwigExtension($context));

        $twig->render('page');

        static::assertStringContainsString('"id":48812', $context->forRequest($page)->trackingSnippet());
    }

    private static function twig(string $cookie): Environment
    {
        $template = '{% if varify_is(48812, 48838) %}variation 48838{% endif %}'
            . ' | original: {{ varify_is_original(48812) ? "yes" : "no" }}'
            . ' | unknown: {{ varify_is_original(999) ? "original" : "variation" }}';
        $twig = new Environment(new ArrayLoader(['page' => $template]));
        $twig->addExtension(new VarifyTwigExtension(Plugin::context(Plugin::stack(Plugin::page($cookie)))));

        return $twig;
    }
}
