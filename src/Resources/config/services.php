<?php

declare(strict_types=1);

use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Varify\Core\Http\CurlTransport;
use Varify\Shopware\Subscriber\HttpCacheSubscriber;
use Varify\Shopware\Subscriber\ResponseSubscriber;
use Varify\Shopware\Twig\VarifyTwigExtension;
use Varify\Shopware\VarifyRequestContext;
use Varify\Shopware\VarifySettings;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->set(CurlTransport::class);

    $services->set(VarifySettings::class)
        ->args([
            service(SystemConfigService::class),
            service('cache.object'),
            service(CurlTransport::class),
            service('logger'),
        ]);

    $services->set(VarifyRequestContext::class)
        ->args([service(VarifySettings::class), service('request_stack')]);

    $services->set(HttpCacheSubscriber::class)
        ->args([service(VarifySettings::class), service(VarifyRequestContext::class)])
        ->tag('kernel.event_subscriber');

    $services->set(ResponseSubscriber::class)
        ->args([
            service(VarifySettings::class),
            service(VarifyRequestContext::class),
            param('shopware.http_cache.reverse_proxy.enabled'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(VarifyTwigExtension::class)
        ->args([service(VarifyRequestContext::class)])
        ->tag('twig.extension');
};
