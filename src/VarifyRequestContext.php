<?php

declare(strict_types=1);

namespace Varify\Shopware;

use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Varify\Core\Request\ForcedVariation;
use Varify\Core\Request\VarifyRequest;
use Varify\Core\Varify;

use function in_array;
use function is_array;

/**
 * One `Varify` per request, created on first use and kept on the request.
 */
final readonly class VarifyRequestContext
{
    private const ATTRIBUTE = '_varify';

    public function __construct(
        private VarifySettings $settings,
        private RequestStack $requestStack,
    ) {}

    public function current(): Varify
    {
        $request = $this->requestStack->getCurrentRequest();

        return $request === null ? Varify::none() : $this->forRequest($request);
    }

    public function forRequest(Request $request): Varify
    {
        $varify = $request->attributes->get(self::ATTRIBUTE);
        if ($varify instanceof Varify) {
            return $varify;
        }

        $varify = $this->create($request);
        $request->attributes->set(self::ATTRIBUTE, $varify);

        return $varify;
    }

    /**
     * An ESI fragment (header, footer) rendered and cached apart from its page. It only sees the cookies the page
     * request arrived with, never the cookie the page is about to set.
     */
    public static function isFragment(Request $request): bool
    {
        return $request->attributes->has('_sw_esi');
    }

    public static function isStorefront(Request $request): bool
    {
        $scopes = $request->attributes->get(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, []);

        return is_array($scopes) && in_array('storefront', $scopes, true);
    }

    public static function varifyRequest(Request $request, string $cookieName): VarifyRequest
    {
        return new VarifyRequest(
            $request->cookies->getString($cookieName),
            $request->query->getString(ForcedVariation::PARAM),
            (string) $request->headers->get('User-Agent', ''),
        );
    }

    private function create(Request $request): Varify
    {
        if (!$this->settings->isConfigured()) {
            return Varify::none();
        }

        $options = $this->settings->options();
        if (self::isFragment($request)) {
            return Varify::readOnly($options, self::varifyRequest($request, $options->cookie->name));
        }

        // Fragments rendered inline (with an external reverse proxy) are sub-requests of the page and share its
        // assignments, including the ones made in this very request.
        $main = $this->requestStack->getMainRequest();
        if ($main !== null && $main !== $request) {
            return $this->forRequest($main);
        }

        return Varify::forRequest($options, self::varifyRequest($request, $options->cookie->name));
    }
}
