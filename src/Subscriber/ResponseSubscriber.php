<?php

declare(strict_types=1);

namespace Varify\Shopware\Subscriber;

use Shopware\Core\PlatformRequest;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Varify\Core\Request\BotDetector;
use Varify\Core\Varify;
use Varify\Shopware\VarifyRequestContext;
use Varify\Shopware\VarifySettings;

use function is_string;
use function str_contains;
use function strripos;
use function strtolower;
use function substr_replace;
use function time;

final readonly class ResponseSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private VarifySettings $settings,
        private VarifyRequestContext $context,
        private bool $reverseProxyEnabled,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Before Shopware's CacheResponseSubscriber (-1500) decides about caching.
            KernelEvents::RESPONSE => [['onResponse', 0], ['preventSharedCaching', -1600]],
        ];
    }

    public function onResponse(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (!$this->settings->isConfigured() || !VarifyRequestContext::isStorefront($request)) {
            return;
        }

        $isFragment = VarifyRequestContext::isFragment($request);
        if (!$isFragment && !$event->isMainRequest()) {
            return;
        }

        $varify = $this->context->forRequest($request);
        $response = $event->getResponse();

        if (!$isFragment) {
            $this->writeCookie($varify, $request, $response);
            $this->keepBotsOutOfTheReverseProxy($request);
        }

        $this->appendTrackingSnippet($varify->trackingSnippet(), $response, $isFragment);
    }

    /**
     * Shopware's no-cache is `private, no-cache`; a reverse proxy may still give it a TTL and strip the cookie.
     */
    public function preventSharedCaching(ResponseEvent $event): void
    {
        $request = $event->getRequest();
        if (
            !$event->isMainRequest()
            || VarifyRequestContext::isFragment($request)
            || !$this->settings->isConfigured()
            || !VarifyRequestContext::isStorefront($request)
            || !$this->context->forRequest($request)->needsCookieWrite()
        ) {
            return;
        }

        $response = $event->getResponse();
        $response->setPrivate();
        $response->setMaxAge(0);
        $response->headers->addCacheControlDirective('no-store');
    }

    private function writeCookie(Varify $varify, Request $request, Response $response): void
    {
        if (!$varify->needsCookieWrite()) {
            return;
        }

        $cookie = $this->settings->options()->cookie;
        $response->headers->setCookie(Cookie::create(
            $cookie->name,
            $varify->cookieValue(),
            time() + $cookie->maxAgeSeconds,
            '/',
            $cookie->domain === '' ? null : $cookie->domain,
            $request->isSecure(),
            true,
            true,
            strtolower($cookie->sameSite->value),
        ));
    }

    /**
     * Behind Varnish or Fastly, the proxy can't tell a bot from a human without a cookie, so a cached bot page would
     * reach new visitors, who would then never be assigned.
     */
    private function keepBotsOutOfTheReverseProxy(Request $request): void
    {
        $userAgent = (string) $request->headers->get('User-Agent', '');
        if ($this->reverseProxyEnabled && BotDetector::isBot($userAgent)) {
            $request->attributes->remove(PlatformRequest::ATTRIBUTE_HTTP_CACHE);
        }
    }

    private function appendTrackingSnippet(string $snippet, Response $response, bool $isFragment): void
    {
        $content = $response->getContent();
        $contentType = (string) $response->headers->get('Content-Type', '');
        if ($snippet === '' || !is_string($content) || !str_contains($contentType, 'text/html')) {
            return;
        }

        if ($isFragment) {
            $response->setContent($content . $snippet);

            return;
        }

        $bodyEnd = strripos($content, '</body>');
        if ($bodyEnd !== false) {
            $response->setContent(substr_replace($content, $snippet, $bodyEnd, 0));
        }
    }
}
