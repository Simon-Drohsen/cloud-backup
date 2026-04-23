<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\FileStorage\MemberFileListingService;
use App\Service\FileStorage\MemberFileMutationService;
use App\Service\FileStorage\MemberFileScopeResolver;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject\MembersUser;
use Pimcore\Model\Element\DuplicateFullPathException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

readonly class MemberFileStorageService
{
    public function __construct(
        private MemberFileScopeResolver $scopeResolver,
        private MemberFileListingService $listingService,
        private MemberFileMutationService $mutationService,
    ) {
    }

    public function hasCompany(MembersUser $user): bool
    {
        return $this->scopeResolver->hasCompany($user);
    }

    /**
     * @return array<int, Asset>
     *
     * @throws DuplicateFullPathException
     */
    public function getUserFiles(MembersUser $user, ?int $folderId = null): array
    {
        return $this->listingService->getUserFiles($user, $folderId);
    }

    /**
     * @return array<int, Folder>
     *
     * @throws DuplicateFullPathException
     */
    public function getUserFolders(MembersUser $user, ?int $folderId = null): array
    {
        return $this->listingService->getUserFolders($user, $folderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function storeUploadedFile(MembersUser $user, UploadedFile $uploadedFile, ?int $targetFolderId = null): Asset
    {
        return $this->mutationService->storeUploadedFile($user, $uploadedFile, $targetFolderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function getOwnedAssetOrFail(int $assetId, MembersUser $user): Asset
    {
        return $this->mutationService->getOwnedAssetOrFail($assetId, $user);
    }

    /**
     * @return array<int, Asset>
     *
     * @throws DuplicateFullPathException
     */
    public function getCompanyFiles(MembersUser $user, ?int $folderId = null): array
    {
        return $this->listingService->getCompanyFiles($user, $folderId);
    }

    /**
     * @return array<int, Folder>
     *
     * @throws DuplicateFullPathException
     */
    public function getCompanyFolders(MembersUser $user, ?int $folderId = null): array
    {
        return $this->listingService->getCompanyFolders($user, $folderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function storeCompanyUploadedFile(MembersUser $user, UploadedFile $uploadedFile, ?int $targetFolderId = null): Asset
    {
        return $this->mutationService->storeCompanyUploadedFile($user, $uploadedFile, $targetFolderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function getOwnedCompanyAssetOrFail(int $assetId, MembersUser $user): Asset
    {
        return $this->mutationService->getOwnedCompanyAssetOrFail($assetId, $user);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function createPersonalFolder(MembersUser $user, string $name, ?int $targetFolderId = null): Folder
    {
        return $this->mutationService->createPersonalFolder($user, $name, $targetFolderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function createCompanyFolder(MembersUser $user, string $name, ?int $targetFolderId = null): Folder
    {
        return $this->mutationService->createCompanyFolder($user, $name, $targetFolderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function resolvePersonalFolder(MembersUser $user, ?int $folderId = null): Folder
    {
        return $this->scopeResolver->resolvePersonalFolder($user, $folderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function resolveCompanyFolder(MembersUser $user, ?int $folderId = null): Folder
    {
        return $this->scopeResolver->resolveCompanyFolder($user, $folderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function deletePersonalFile(MembersUser $user, int $assetId): void
    {
        $this->mutationService->deletePersonalFile($user, $assetId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function deleteCompanyFile(MembersUser $user, int $assetId): void
    {
        $this->mutationService->deleteCompanyFile($user, $assetId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function deletePersonalFolder(MembersUser $user, int $folderId): void
    {
        $this->mutationService->deletePersonalFolder($user, $folderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function deleteCompanyFolder(MembersUser $user, int $folderId): void
    {
        $this->mutationService->deleteCompanyFolder($user, $folderId);
    }

    /**
     * @return array<int, Folder>
     *
     * @throws DuplicateFullPathException
     */
    public function getPersonalMoveTargetFolders(MembersUser $user): array
    {
        return $this->listingService->getPersonalMoveTargetFolders($user);
    }

    /**
     * @return array<int, Folder>
     *
     * @throws DuplicateFullPathException
     */
    public function getCompanyMoveTargetFolders(MembersUser $user): array
    {
        return $this->listingService->getCompanyMoveTargetFolders($user);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function movePersonalFile(MembersUser $user, int $assetId, int $targetFolderId): void
    {
        $this->mutationService->movePersonalFile($user, $assetId, $targetFolderId);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function moveCompanyFile(MembersUser $user, int $assetId, int $targetFolderId): void
    {
        $this->mutationService->moveCompanyFile($user, $assetId, $targetFolderId);
    }
}
