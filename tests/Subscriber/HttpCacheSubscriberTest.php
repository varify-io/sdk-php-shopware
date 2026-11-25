<?php

declare(strict_types=1);

namespace Varify\Shopware\Tests\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\Extension\CacheHashRequiredExtension;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;
use Varify\Core\Tests\Support\Fixtures;
use Varify\Shopware\Subscriber\HttpCacheSubscriber;
use Varify\Shopware\Tests\Support\Plugin;

#[CoversClass(HttpCacheSubscriber::class)]
#[Group('unit')]
final class HttpCacheSubscriberTest extends TestCase
{
    #[Test]
    public function keysAReturningVisitorByTheirAssignments(): void
    {
        $event = new HttpCacheKeyEvent(Plugin::page(Plugin::RETURNING_COOKIE));

        self::subscriber(Plugin::page())->onCacheKey($event);

        static::assertTrue($event->isCacheable);
        static::assertSame(Fixtures::config()->version . ':269-311_48812-48838', $event->get('varify'));
    }

    #[Test]
    public function letsAVisitorWithoutACookiePassTheCache(): void
    {
        $event = new HttpCacheKeyEvent(Plugin::page());

        self::subscriber(Plugin::page())->onCacheKey($event);

        static::assertFalse($event->isCacheable);
    }

    #[Test]
    public function keysBotsTogether(): void
    {
        $event = new HttpCacheKeyEvent(Plugin::page('', Plugin::BOT));

        self::subscriber(Plugin::page())->onCacheKey($event);

        static::assertTrue($event->isCacheable);
        static::assertSame('bot', $event->get('varify'));
    }

    #[Test]
    public function leavesTheStoreApiAlone(): void
    {
        $event = new HttpCacheKeyEvent(Request::create('/store-api/product'));

        self::subscriber(Plugin::page())->onCacheKey($event);

        static::assertTrue($event->isCacheable);
        static::assertFalse($event->has('varify'));
    }

    #[Test]
    public function addsTheVariantToTheCacheHashAndKeepsAnAssigningResponseOutOfTheCache(): void
    {
        $page = Plugin::page();
        $event = new HttpCacheCookieEvent($page, Generator::generateSalesChannelContext(), []);

        self::subscriber($page)->onCacheCookie($event);

        static::assertIsString($event->get('varify'));
        static::assertTrue($event->doNotStore);
    }

    #[Test]
    public function storesTheResponseOfAReturningVisitor(): void
    {
        $page = Plugin::page(Plugin::RETURNING_COOKIE);
        $event = new HttpCacheCookieEvent($page, Generator::generateSalesChannelContext(), []);

        self::subscriber($page)->onCacheCookie($event);

        static::assertSame(Fixtures::config()->version . ':269-311_48812-48838', $event->get('varify'));
        static::assertFalse($event->doNotStore);
    }

    #[Test]
    public function requiresTheCacheHashForEveryVisitor(): void
    {
        $page = Plugin::page();
        $extension = new CacheHashRequiredExtension($page, Generator::generateSalesChannelContext(), new Cart('token'));

        self::subscriber($page)->onCacheHashRequired($extension);

        static::assertTrue($extension->result);
    }

    #[Test]
    public function doesNotRequireTheCacheHashForBots(): void
    {
        $page = Plugin::page('', Plugin::BOT);
        $extension = new CacheHashRequiredExtension($page, Generator::generateSalesChannelContext(), new Cart('token'));

        self::subscriber($page)->onCacheHashRequired($extension);

        static::assertNotTrue($extension->result);
    }

    private static function subscriber(Request $page): HttpCacheSubscriber
    {
        return new HttpCacheSubscriber(Plugin::settings(), Plugin::context(Plugin::stack($page)));
    }
}
