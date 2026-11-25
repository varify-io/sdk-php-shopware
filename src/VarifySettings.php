<?php

declare(strict_types=1);

namespace Varify\Shopware;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Cache\Psr16Cache;
use Varify\Core\CookieOptions;
use Varify\Core\Http\Transport;
use Varify\Core\VarifyError;
use Varify\Core\VarifyOptions;

/**
 * The plugin configuration. It is read shop-wide, not per sales channel: Shopware's HTTP cache looks pages up before
 * the request is assigned to a sales channel, and the cache key depends on these settings.
 */
final readonly class VarifySettings
{
    public function __construct(
        private SystemConfigService $systemConfig,
        private CacheItemPoolInterface $cachePool,
        private Transport $transport,
        private LoggerInterface $logger,
    ) {}

    public function isConfigured(): bool
    {
        return $this->teamId() !== '';
    }

    public function options(): VarifyOptions
    {
        $timeoutMs = $this->systemConfig->getInt('VarifyShopware.config.timeoutMs');
        $cacheTtlSeconds = $this->systemConfig->getInt('VarifyShopware.config.cacheTtlSeconds');

        $options = VarifyOptions::forTeam($this->teamId())
            ->withTransport($this->transport)
            ->withCache(new Psr16Cache($this->cachePool), $cacheTtlSeconds > 0 ? $cacheTtlSeconds : 60)
            ->withTimeout($timeoutMs > 0 ? $timeoutMs : 300)
            ->withOnError(function (VarifyError $error): void {
                $this->logger->warning('Varify: ' . $error->getMessage(), [
                    'reason' => $error->reason->value,
                    'exception' => $error,
                ]);
            });

        $configUrl = $this->systemConfig->getString('VarifyShopware.config.configUrl');
        if ($configUrl !== '') {
            $options = $options->withConfigUrl($configUrl);
        }

        $cookieDomain = $this->systemConfig->getString('VarifyShopware.config.cookieDomain');
        if ($cookieDomain !== '') {
            $options = $options->withCookie(CookieOptions::defaults()->withDomain($cookieDomain));
        }

        return $options;
    }

    private function teamId(): string
    {
        return $this->systemConfig->getString('VarifyShopware.config.teamId');
    }
}
