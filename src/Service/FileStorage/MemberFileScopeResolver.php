<?php

declare(strict_types=1);

namespace App\Service\FileStorage;

use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject\MembersCompany;
use Pimcore\Model\DataObject\MembersCompany\Listing as MembersCompanyListing;
use Pimcore\Model\DataObject\MembersUser;
use Pimcore\Model\Element\DuplicateFullPathException;
use Pimcore\Model\Element\Service as ElementService;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use function PHPStan\autoloadFunctions;

class MemberFileScopeResolver
{
    private const string PROTECTED_STORAGE_ROOT = '/restricted-assets';

    private const string MEMBER_ROOT_FOLDER_NAME = 'protected-members';

    private const string COMPANY_ROOT_FOLDER_NAME = 'companies';

    public function hasCompany(MembersUser $user): bool
    {
        return $this->findUserCompany($user) instanceof MembersCompany;
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function resolvePersonalFolder(MembersUser $user, ?int $folderId = null): Folder
    {
        return $this->resolveFolderInScope($this->getOrCreateUserFolder($user), $folderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function resolveCompanyFolder(MembersUser $user, ?int $folderId = null): Folder
    {
        return $this->resolveFolderInScope($this->getOrCreateCompanyFolderForUser($user), $folderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function getOrCreateUserFolder(MembersUser $user): Folder
    {
        $rootFolder = $this->getOrCreateMemberRootFolder();
        $safeUserName = $this->getSafeUserFolderKey($user);

        return $this->getOrCreateChildFolder($rootFolder, $safeUserName, [
            'memberUserId' => $user->getId(),
            'memberUserName' => $user->getUserName(),
        ]);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function getOrCreateCompanyFolderForUser(MembersUser $user): Folder
    {
        $company = $this->getUserCompanyOrFail($user);
        $companyRootFolder = $this->getOrCreateCompanyRootFolder();
        $companyFolderKey = $this->getSafeCompanyFolderKey($company);

        return $this->getOrCreateChildFolder($companyRootFolder, $companyFolderKey, [
            'membersCompanyId' => $company->getId(),
            'membersCompanyName' => $company->getName(),
        ]);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function assetBelongsToUser(Asset $asset, MembersUser $user): bool
    {
        $userFolderPath = $this->getOrCreateUserFolder($user)->getFullPath();

        return str_starts_with($asset->getFullPath(), $userFolderPath . '/');
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function assetBelongsToCompanyOfUser(Asset $asset, MembersUser $user): bool
    {
        $companyFolderPath = $this->getOrCreateCompanyFolderForUser($user)->getFullPath();

        return str_starts_with($asset->getFullPath(), $companyFolderPath . '/');
    }

    public function assertElementInScope(Asset $asset, Folder $scopeRoot): void
    {
        $scopePath = rtrim($scopeRoot->getFullPath(), '/');
        $elementPath = rtrim($asset->getFullPath(), '/');

        if ($elementPath !== $scopePath && ! str_starts_with($elementPath, $scopePath . '/')) {
            throw new AccessDeniedHttpException('Zugriff auf dieses Element ist nicht erlaubt.');
        }
    }

    private function findUserCompany(MembersUser $user): ?MembersCompany
    {
        $adminMatch = $this->extractSingleCompany(MembersCompany::getByCompanyAdmin($user, 1));
        if ($adminMatch instanceof MembersCompany) {
            return $adminMatch;
        }

        return $this->extractSingleCompany(MembersCompany::getByCoworker($user, 1));
    }

    private function extractSingleCompany(mixed $companyResult): ?MembersCompany
    {
        if ($companyResult instanceof MembersCompany) {
            return $companyResult;
        }

        if ($companyResult instanceof MembersCompanyListing) {
            $matches = $companyResult->load();

            return $matches[0] ?? null;
        }

        if (is_array($companyResult)) {
            $candidate = $companyResult[0] ?? null;

            return $candidate instanceof MembersCompany ? $candidate : null;
        }

        return null;
    }

    private function getUserCompanyOrFail(MembersUser $user): MembersCompany
    {
        $company = $this->findUserCompany($user);

        if (! $company instanceof MembersCompany) {
            throw new AccessDeniedHttpException('Kein Firmenzugriff vorhanden.');
        }

        return $company;
    }

    /**
     * @throws DuplicateFullPathException
     */
    private function getOrCreateMemberRootFolder(): Folder
    {
        $protectedRoot = Asset::getByPath(self::PROTECTED_STORAGE_ROOT);

        if (! $protectedRoot instanceof Folder) {
            if ($protectedRoot instanceof Asset) {
                throw new RuntimeException(sprintf('Der Schutzordner "%s" ist kein Ordner.', self::PROTECTED_STORAGE_ROOT));
            }

            $assetRoot = Asset::getById(1);
            if (! $assetRoot instanceof Folder) {
                throw new RuntimeException('Der Pimcore-Asset-Root konnte nicht geladen werden.');
            }

            $protectedRoot = new Folder();
            $protectedRoot->setParent($assetRoot);
            $protectedRoot->setFilename(trim(self::PROTECTED_STORAGE_ROOT, '/'));
            $protectedRoot->save();
        }

        $memberRootPath = sprintf('%s/%s', self::PROTECTED_STORAGE_ROOT, self::MEMBER_ROOT_FOLDER_NAME);
        $memberRoot = Asset::getByPath($memberRootPath);

        if ($memberRoot instanceof Folder) {
            return $memberRoot;
        }

        if ($memberRoot instanceof Asset) {
            throw new RuntimeException(sprintf('Der Mitglieder-Root "%s" ist kein Ordner.', $memberRootPath));
        }

        $folder = new Folder();
        $folder->setParent($protectedRoot);
        $folder->setFilename(self::MEMBER_ROOT_FOLDER_NAME);
        $folder->save();

        return $folder;
    }

    /**
     * @throws DuplicateFullPathException
     */
    private function getOrCreateCompanyRootFolder(): Folder
    {
        $memberRoot = $this->getOrCreateMemberRootFolder();

        return $this->getOrCreateChildFolder($memberRoot, self::COMPANY_ROOT_FOLDER_NAME);
    }

    private function getSafeUserFolderKey(MembersUser $user): string
    {
        $userName = trim((string) $user->getUserName());
        $baseKey = ElementService::getValidKey($userName !== '' ? $userName : sprintf('member-%d', $user->getId()), 'asset');

        return $baseKey !== '' ? $baseKey : sprintf('member-%d', $user->getId());
    }

    private function getSafeCompanyFolderKey(MembersCompany $company): string
    {
        $source = $company->getKey();
        if ($source === null || $source === '') {
            $source = $company->getName() ?: sprintf('company-%d', $company->getId());
        }

        $baseKey = ElementService::getValidKey($source, 'asset');

        return $baseKey !== '' ? $baseKey : 'company';
    }

    private function resolveFolderInScope(Folder $scopeRoot, ?int $folderId): Folder
    {
        if ($folderId === null || $folderId <= 0) {
            return $scopeRoot;
        }

        $folder = Asset::getById($folderId);

        if (! $folder instanceof Folder) {
            throw new NotFoundHttpException('Ordner wurde nicht gefunden.');
        }

        $this->assertElementInScope($folder, $scopeRoot);

        return $folder;
    }

    /**
     * @param array<string, mixed> $customSettings
     *
     * @throws DuplicateFullPathException
     */
    private function getOrCreateChildFolder(Folder $parentFolder, string $folderName, array $customSettings = []): Folder
    {
        $folderPath = sprintf('%s/%s', rtrim($parentFolder->getFullPath(), '/'), $folderName);
        $existing = Asset::getByPath($folderPath);

        if ($existing instanceof Folder) {
            return $existing;
        }

        if ($existing instanceof Asset) {
            throw new RuntimeException(sprintf('Der Ordner-Pfad "%s" ist bereits belegt.', $folderPath));
        }

        $folder = new Folder();
        $folder->setParent($parentFolder);
        $folder->setFilename($folderName);

        if ($customSettings !== []) {
            $folder->setCustomSettings($customSettings);
        }

        $folder->save();

        return $folder;
    }
}
