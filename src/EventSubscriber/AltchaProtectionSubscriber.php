<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Service\AltchaService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class AltchaProtectionSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly AltchaService $altchaService,
        private readonly RouterInterface $router
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => 'onKernelRequest',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (! $request->isMethod('POST')) {
            return;
        }

        $route = (string) $request->attributes->get('_route');
        $context = match ($route) {
            'members_user_security_check' => 'members_login',
            'members_user_registration_register' => 'members_registration',
            default => null,
        };

        if ($context === null) {
            return;
        }

        $payload = (string) $request->request->get('altcha', '');
        if ($this->altchaService->isValidPayload($payload, $context)) {
            return;
        }

        $session = $request->getSession();
        $session->getFlashBag()->add('error', 'Bitte bestaetige zuerst den Spam-Schutz.');

        if ($route === 'members_user_security_check') {
            $event->setResponse(new RedirectResponse($this->router->generate('members_user_security_login')));

            return;
        }

        $event->setResponse(new RedirectResponse($request->getRequestUri()));
    }
}
