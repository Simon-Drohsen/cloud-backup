<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\AltchaService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class AltchaController extends AbstractController
{
    public function __construct(private readonly AltchaService $altchaService)
    {
    }

    #[Route('/altcha/challenge/{context}', name: 'app_altcha_challenge', requirements: ['context' => '[a-z0-9_]+'], methods: ['GET'])]
    public function challengeAction(string $context): JsonResponse
    {
        if (! $this->altchaService->supportsContext($context)) {
            throw $this->createNotFoundException('Unknown ALTCHA context.');
        }

        $response = new JsonResponse($this->altchaService->createChallenge($context));
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-store', true);

        return $response;
    }
}
