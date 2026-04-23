<?php

declare(strict_types=1);

namespace App\Service\FileStorage;

use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject\MembersUser;
use Pimcore\Model\Element\DuplicateFullPathException;

readonly class MemberFileListingService
{
    public function __construct(
        private MemberFileScopeResolver $scopeResolver
    ) {
    }

    /**
     * @return array<int, Asset>
     *
     * @throws DuplicateFullPathException
     */
    public function getUserFiles(MembersUser $user, ?int $folderId = null): array
    {
        return $this->getFilesFromFolder($this->scopeResolver->resolvePersonalFolder($user, $folderId));
    }

    /**
     * @return array<int, Folder>
     *
     * @throws DuplicateFullPathException
     */
    public function getUserFolders(MembersUser $user, ?int $folderId = null): array
    {
        return $this->getFoldersFromFolder($this->scopeResolver->resolvePersonalFolder($user, $folderId));
    }

    /**
     * @return array<int, Asset>
     *
     * @throws DuplicateFullPathException
     */
    public function getCompanyFiles(MembersUser $user, ?int $folderId = null): array
    {
        return $this->getFilesFromFolder($this->scopeResolver->resolveCompanyFolder($user, $folderId));
    }

    /**
     * @return array<int, Folder>
     *
     * @throws DuplicateFullPathException
     */
    public function getCompanyFolders(MembersUser $user, ?int $folderId = null): array
    {
        return $this->getFoldersFromFolder($this->scopeResolver->resolveCompanyFolder($user, $folderId));
    }

    /**
     * @return array<int, Folder>
     *
     * @throws DuplicateFullPathException
     */
    public function getPersonalMoveTargetFolders(MembersUser $user): array
    {
        return $this->getMoveTargetFoldersInScope($this->scopeResolver->getOrCreateUserFolder($user));
    }

    /**
     * @return array<int, Folder>
     *
     * @throws DuplicateFullPathException
     */
    public function getCompanyMoveTargetFolders(MembersUser $user): array
    {
        return $this->getMoveTargetFoldersInScope($this->scopeResolver->getOrCreateCompanyFolderForUser($user));
    }

    /**
     * @return array<int, Asset>
     */
    private function getFilesFromFolder(Folder $folder): array
    {
        $assets = $folder->getChildren()->getAssets();
        $files = array_values(array_filter($assets, static fn (Asset $asset): bool => ! $asset instanceof Folder));

        usort($files, static fn (Asset $a, Asset $b): int => $b->getCreationDate() <=> $a->getCreationDate());

        return $files;
    }

    /**
     * @return array<int, Folder>
     */
    private function getFoldersFromFolder(Folder $folder): array
    {
        $assets = $folder->getChildren()->getAssets();
        $folders = array_values(array_filter($assets, static fn (Asset $asset): bool => $asset instanceof Folder));

        if ($folders === []) {
            return [];
        }

        usort($folders, static fn (Folder $a, Folder $b): int => strcmp((string) $a->getFilename(), (string) $b->getFilename()));

        return $folders;
    }

    /**
     * @return array<int, Folder>
     */
    private function getMoveTargetFoldersInScope(Folder $scopeRoot): array
    {
        $folders = [$scopeRoot];
        $folders = array_merge($folders, $this->collectFoldersRecursively($scopeRoot));

        usort($folders, static fn (Folder $a, Folder $b): int => strcmp($a->getFullPath(), $b->getFullPath()));

        return $folders;
    }

    /**
     * @return array<int, Folder>
     */
    private function collectFoldersRecursively(Folder $parent): array
    {
        $folders = [];

        foreach ($this->getFoldersFromFolder($parent) as $childFolder) {
            $folders[] = $childFolder;
            $folders = array_merge($folders, $this->collectFoldersRecursively($childFolder));
        }

        return $folders;
    }
}
