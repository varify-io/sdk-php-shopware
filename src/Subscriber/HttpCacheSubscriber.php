<?php

declare(strict_types=1);

namespace Varify\Shopware\Subscriber;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent;
use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Adapter\Cache\Http\Extension\CacheHashRequiredExtension;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Varify\Core\Cache\CacheVariant;
use Varify\Shopware\VarifyRequestContext;
use Varify\Shopware\VarifySettings;

use function str_starts_with;

/**
 * Keeps one cached copy of a page per combination of assignments, never per visitor.
 *
 * - Shopware's own HTTP cache: the cache key gets the visitor's cache variant, computed from the request cookie
 *   before anything renders. A human without a cookie bypasses the cache, so the page that assigns them is rendered.
 * - Reverse proxies (Varnish, Fastly) vary on the `sw-cache-hash` cookie, which carries the same variant.
 * - A response that writes the cookie is never stored: caches drop `Set-Cookie`, and the key comes from the request.
 */
final readonly class HttpCacheSubscriber implements EventSubscriberInterface
{
    private const KEY_PART = 'varify';

    public function __construct(
        private VarifySettings $settings,
        private VarifyRequestContext $context,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            HttpCacheKeyEvent::class => 'onCacheKey',
            HttpCacheCookieEvent::class => 'onCacheCookie',
            ExtensionDispatcher::post(CacheHashRequiredExtension::NAME) => 'onCacheHashRequired',
        ];
    }

    public function onCacheKey(HttpCacheKeyEvent $event): void
    {
        // The cache runs before routing, so API traffic is told apart by its path.
        if (!$this->settings->isConfigured() || self::isApi($event->request)) {
            return;
        }

        $options = $this->settings->options();
        $variant = CacheVariant::forRequest(
            $options,
            VarifyRequestContext::varifyRequest($event->request, $options->cookie->name),
        );

        if (!$variant->cacheable) {
            $event->isCacheable = false;

            return;
        }

        $event->add(self::KEY_PART, $variant->key);
    }

    public function onCacheCookie(HttpCacheCookieEvent $event): void
    {
        if (!$this->isPage($event->request)) {
            return;
        }

        $varify = $this->context->forRequest($event->request);
        if ($varify->visitorId()->isNone()) {
            return;
        }

        $event->add(self::KEY_PART, $varify->cacheVariant()->key);
        if ($varify->needsCookieWrite()) {
            $event->doNotStore = true;
        }
    }

    public function onCacheHashRequired(CacheHashRequiredExtension $extension): void
    {
        if (!$this->isPage($extension->request)) {
            return;
        }

        if (!$this->context->forRequest($extension->request)->visitorId()->isNone()) {
            $extension->result = true;
        }
    }

    private function isPage(Request $request): bool
    {
        return $this->settings->isConfigured()
            && VarifyRequestContext::isStorefront($request)
            && !VarifyRequestContext::isFragment($request);
    }

    private static function isApi(Request $request): bool
    {
        $path = $request->getPathInfo();

        return str_starts_with($path, '/api/') || str_starts_with($path, '/store-api/');
    }
}
