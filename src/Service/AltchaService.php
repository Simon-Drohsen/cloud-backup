<?php

declare(strict_types=1);

namespace App\Service;

use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\ChallengeOptions;

class AltchaService
{
    private const array SUPPORTED_CONTEXTS = [
        'members_login',
        'members_registration',
        'member_files_upload_personal',
        'member_files_upload_company',
        'member_files_folder_personal',
        'member_files_folder_company',
    ];

    private Altcha $altcha;

    public function __construct(
        string $hmacKey,
        private readonly int $ttlSeconds = 300
    ) {
        $this->altcha = new Altcha($hmacKey);
    }

    public function supportsContext(string $context): bool
    {
        return in_array($context, self::SUPPORTED_CONTEXTS, true);
    }

    /**
     * @return array{algorithm: string, challenge: string, maxnumber: int, salt: string, signature: string}
     */
    public function createChallenge(string $context): array
    {
        if (! $this->supportsContext($context)) {
            throw new \InvalidArgumentException(sprintf('Unsupported ALTCHA context "%s".', $context));
        }

        $challenge = $this->altcha->createChallenge(new ChallengeOptions(
            expires: new \DateTimeImmutable(sprintf('+%d seconds', $this->ttlSeconds)),
            params: ['context' => $context]
        ));

        return [
            'algorithm' => $challenge->algorithm,
            'challenge' => $challenge->challenge,
            'maxnumber' => $challenge->maxNumber,
            'salt' => $challenge->salt,
            'signature' => $challenge->signature,
        ];
    }

    public function isValidPayload(?string $payload, string $expectedContext): bool
    {
        if ($payload === null || trim($payload) === '' || ! $this->supportsContext($expectedContext)) {
            return false;
        }

        if (! $this->altcha->verifySolution($payload, true)) {
            return false;
        }

        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            return false;
        }

        try {
            $data = json_decode($decoded, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return false;
        }

        if (! is_array($data) || ! isset($data['salt']) || ! is_string($data['salt'])) {
            return false;
        }

        $query = parse_url($data['salt'], \PHP_URL_QUERY);
        if (! is_string($query)) {
            return false;
        }

        parse_str($query, $params);

        return ($params['context'] ?? null) === $expectedContext;
    }
}
