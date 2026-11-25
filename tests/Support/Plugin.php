<?php

declare(strict_types=1);

namespace Varify\Shopware\Tests\Support;

use Psr\Log\NullLogger;
use Shopware\Core\PlatformRequest;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Varify\Core\Tests\Support\FakeTransport;
use Varify\Core\Tests\Support\Fixtures;
use Varify\Shopware\VarifyRequestContext;
use Varify\Shopware\VarifySettings;

final class Plugin
{
    public const BROWSER = 'Mozilla/5.0 (Macintosh) AppleWebKit/537.36 Chrome/131.0.0.0 Safari/537.36';
    public const BOT = 'Mozilla/5.0 (compatible; Googlebot/2.1)';
    public const RETURNING_COOKIE = Fixtures::VISITOR_ID . '.269-311_48812-48838';

    public static function settings(string $teamId = 'team'): VarifySettings
    {
        return new VarifySettings(
            new StaticSystemConfigService(['VarifyShopware.config.teamId' => $teamId]),
            new ArrayAdapter(),
            FakeTransport::responding(Fixtures::CONFIG_JSON),
            new NullLogger(),
        );
    }

    public static function context(RequestStack $requestStack, string $teamId = 'team'): VarifyRequestContext
    {
        return new VarifyRequestContext(self::settings($teamId), $requestStack);
    }

    public static function page(string $cookie = '', string $userAgent = self::BROWSER, string $path = '/'): Request
    {
        $request = Request::create(
            $path,
            Request::METHOD_GET,
            [],
            $cookie === '' ? [] : ['varify' => $cookie],
            [],
            ['HTTP_USER_AGENT' => $userAgent],
        );
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, ['storefront']);

        return $request;
    }

    public static function fragment(string $cookie = ''): Request
    {
        $request = self::page($cookie, self::BROWSER, '/_esi/global/header');
        $request->attributes->set('_sw_esi', true);

        return $request;
    }

    public static function stack(Request ...$requests): RequestStack
    {
        $stack = new RequestStack();
        foreach ($requests as $request) {
            $stack->push($request);
        }

        return $stack;
    }
}
