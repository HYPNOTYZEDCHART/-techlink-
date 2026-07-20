<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class LoginRateLimiterSubscriber implements EventSubscriberInterface
{
    private const MAX_ATTEMPTS = 5;
    private const BLOCK_DURATION = 60; // secondes

    public function __construct(
        private CacheInterface $cache,
        private RequestStack $requestStack,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckPassportEvent::class => 'onCheckPassport',
        ];
    }

    public function onCheckPassport(CheckPassportEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $ip = $request->getClientIp();
        $key = 'login_block_' . md5($ip);

        $blockedUntil = $this->cache->get($key, function (ItemInterface $item) {
            $item->expiresAfter(self::BLOCK_DURATION);
            return null;
        });

        if ($blockedUntil !== null && $blockedUntil > time()) {
            $remaining = $blockedUntil - time();
            throw new TooManyRequestsHttpException($remaining, sprintf('Trop de tentatives de connexion. Réessaie dans %d secondes.', $remaining));
        }

        $attemptsKey = 'login_attempts_' . md5($ip);
        $attempts = $this->cache->get($attemptsKey, function (ItemInterface $item) {
            $item->expiresAfter(self::BLOCK_DURATION);
            return 0;
        });

        $attempts++;
        $this->cache->delete($attemptsKey);
        $this->cache->get($attemptsKey, function (ItemInterface $item) use ($attempts) {
            $item->expiresAfter(self::BLOCK_DURATION);
            return $attempts;
        });

        if ($attempts >= self::MAX_ATTEMPTS) {
            $blockUntil = time() + self::BLOCK_DURATION;
            $this->cache->delete($key);
            $this->cache->get($key, function (ItemInterface $item) use ($blockUntil) {
                $item->expiresAfter(self::BLOCK_DURATION);
                return $blockUntil;
            });

            throw new TooManyRequestsHttpException(self::BLOCK_DURATION, 'Trop de tentatives de connexion. Réessaie dans une minute.');
        }
    }
}