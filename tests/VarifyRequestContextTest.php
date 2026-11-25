<?php

declare(strict_types=1);

namespace Varify\Shopware\Tests;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Varify\Shopware\Tests\Support\Plugin;
use Varify\Shopware\VarifyRequestContext;

#[CoversClass(VarifyRequestContext::class)]
#[Group('unit')]
final class VarifyRequestContextTest extends TestCase
{
    #[Test]
    public function assignsANewVisitorOnThePage(): void
    {
        $page = Plugin::page();
        $context = Plugin::context(Plugin::stack($page));

        $varify = $context->forRequest($page);

        static::assertTrue($varify->needsCookieWrite());
        static::assertFalse($varify->visitorId()->isNone());
    }

    #[Test]
    public function keepsOneVarifyPerRequest(): void
    {
        $page = Plugin::page();
        $context = Plugin::context(Plugin::stack($page));

        $varify = $context->forRequest($page);

        static::assertSame($varify, $context->current());
    }

    #[Test]
    public function subRequestsShareThePagesAssignments(): void
    {
        $page = Plugin::page();
        $subRequest = Request::create('/widgets/header');
        $context = Plugin::context(Plugin::stack($page, $subRequest));

        $varify = $context->forRequest($subRequest);

        static::assertSame($context->forRequest($page), $varify);
    }

    #[Test]
    public function aFragmentNeverAssignsAVisitorWithoutACookie(): void
    {
        $fragment = Plugin::fragment();
        $context = Plugin::context(Plugin::stack($fragment));

        $varify = $context->forRequest($fragment);

        static::assertTrue($varify->visitorId()->isNone());
        static::assertTrue($varify->assignment(48812)->isOriginal());
        static::assertFalse($varify->needsCookieWrite());
    }

    #[Test]
    public function aFragmentReadsTheVisitorsAssignments(): void
    {
        $fragment = Plugin::fragment(Plugin::RETURNING_COOKIE);
        $context = Plugin::context(Plugin::stack($fragment));

        $varify = $context->forRequest($fragment);

        static::assertTrue($varify->assignment(48812)->is(48838));
    }

    #[Test]
    public function doesNothingWithoutATeamId(): void
    {
        $page = Plugin::page();
        $context = Plugin::context(Plugin::stack($page), '');

        $varify = $context->forRequest($page);

        static::assertTrue($varify->visitorId()->isNone());
        static::assertFalse($varify->needsCookieWrite());
    }
}
