<?php

declare(strict_types=1);

namespace Varify\Shopware\Tests\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Varify\Shopware\Subscriber\ResponseSubscriber;
use Varify\Shopware\Tests\Support\Plugin;

#[CoversClass(ResponseSubscriber::class)]
#[Group('unit')]
final class ResponseSubscriberTest extends TestCase
{
    private const PAGE = '<html><body><main>shop</main></body></html>';

    #[Test]
    public function writesTheCookieForANewVisitor(): void
    {
        $page = Plugin::page();
        $event = $this->event($page, self::PAGE);

        self::subscriber($page)->onResponse($event);

        $cookie = $event->getResponse()->headers->getCookies()[0];
        static::assertSame('varify', $cookie->getName());
        static::assertTrue($cookie->isHttpOnly());
        static::assertSame('lax', $cookie->getSameSite());
        static::assertMatchesRegularExpression('/^[0-9a-f-]{36}\.269-\d+_48812-\d+$/', (string) $cookie->getValue());
    }

    #[Test]
    public function writesNoCookieForAReturningVisitor(): void
    {
        $page = Plugin::page(Plugin::RETURNING_COOKIE);
        $event = $this->event($page, self::PAGE);

        self::subscriber($page)->onResponse($event);

        static::assertSame([], $event->getResponse()->headers->getCookies());
    }

    #[Test]
    public function addsTheTrackingSnippetBeforeTheEndOfTheBody(): void
    {
        $page = Plugin::page(Plugin::RETURNING_COOKIE);
        $context = Plugin::context(Plugin::stack($page));
        $context->forRequest($page)->assignment(48812);
        $event = $this->event($page, self::PAGE);

        (new ResponseSubscriber(Plugin::settings(), $context, false))->onResponse($event);

        static::assertMatchesRegularExpression(
            '#<main>shop</main><script>.*"id":48812.*</script></body></html>$#',
            (string) $event->getResponse()->getContent(),
        );
    }

    #[Test]
    public function appendsTheSnippetToAFragment(): void
    {
        $fragment = Plugin::fragment(Plugin::RETURNING_COOKIE);
        $context = Plugin::context(Plugin::stack($fragment));
        $context->forRequest($fragment)->assignment(48812);
        $event = $this->event($fragment, '<header>shop</header>');

        (new ResponseSubscriber(Plugin::settings(), $context, false))->onResponse($event);

        static::assertMatchesRegularExpression(
            '#^<header>shop</header><script>.*</script>$#',
            (string) $event->getResponse()->getContent(),
        );
        static::assertSame([], $event->getResponse()->headers->getCookies());
    }

    #[Test]
    public function keepsBotPagesOutOfAReverseProxy(): void
    {
        $page = Plugin::page('', Plugin::BOT);
        $page->attributes->set(PlatformRequest::ATTRIBUTE_HTTP_CACHE, true);
        $event = $this->event($page, self::PAGE);

        (new ResponseSubscriber(Plugin::settings(), Plugin::context(Plugin::stack($page)), true))->onResponse($event);

        static::assertFalse($page->attributes->has(PlatformRequest::ATTRIBUTE_HTTP_CACHE));
    }

    #[Test]
    public function forbidsSharedCachingOfAResponseThatWritesTheCookie(): void
    {
        $page = Plugin::page();
        $event = $this->event($page, self::PAGE);

        self::subscriber($page)->preventSharedCaching($event);

        static::assertTrue($event->getResponse()->headers->hasCacheControlDirective('no-store'));
        static::assertTrue($event->getResponse()->headers->hasCacheControlDirective('private'));
    }

    #[Test]
    public function ignoresNonStorefrontRequests(): void
    {
        $request = Request::create('/api/_info/version');
        $event = $this->event($request, '{}');

        self::subscriber($request)->onResponse($event);

        static::assertSame([], $event->getResponse()->headers->getCookies());
        static::assertFalse($request->attributes->has('_varify'));
    }

    private function event(Request $request, string $content): ResponseEvent
    {
        $response = new Response($content, 200, ['Content-Type' => 'text/html; charset=UTF-8']);

        return new ResponseEvent(
            static::createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            $response,
        );
    }

    private static function subscriber(Request $request): ResponseSubscriber
    {
        return new ResponseSubscriber(Plugin::settings(), Plugin::context(Plugin::stack($request)), false);
    }
}
