<?php

declare(strict_types=1);

namespace App\Service\FileStorage;

use Exception;
use InvalidArgumentException;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject\MembersUser;
use Pimcore\Model\Element\DuplicateFullPathException;
use Pimcore\Model\Element\Service as ElementService;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

readonly class MemberFileMutationService
{
    public function __construct(
        private MemberFileScopeResolver $scopeResolver
    ) {
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function storeUploadedFile(MembersUser $user, UploadedFile $uploadedFile, ?int $targetFolderId = null): Asset
    {
        $folder = $this->scopeResolver->resolvePersonalFolder($user, $targetFolderId);
        $filename = $this->buildStoredFilename($uploadedFile);

        return Asset::create($folder->getId(), [
            'filename' => $filename,
            'data' => (string) file_get_contents($uploadedFile->getPathname()),
        ]);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function storeCompanyUploadedFile(MembersUser $user, UploadedFile $uploadedFile, ?int $targetFolderId = null): Asset
    {
        $folder = $this->scopeResolver->resolveCompanyFolder($user, $targetFolderId);
        $filename = $this->buildStoredFilename($uploadedFile);

        return Asset::create($folder->getId(), [
            'filename' => $filename,
            'data' => (string) file_get_contents($uploadedFile->getPathname()),
        ]);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function getOwnedAssetOrFail(int $assetId, MembersUser $user): Asset
    {
        $asset = Asset::getById($assetId);

        if (! $asset instanceof Asset || $asset instanceof Folder) {
            throw new NotFoundHttpException('Datei wurde nicht gefunden.');
        }

        if (! $this->scopeResolver->assetBelongsToUser($asset, $user)) {
            throw new AccessDeniedHttpException('Zugriff auf diese Datei ist nicht erlaubt.');
        }

        return $asset;
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function getOwnedCompanyAssetOrFail(int $assetId, MembersUser $user): Asset
    {
        $asset = Asset::getById($assetId);

        if (! $asset instanceof Asset || $asset instanceof Folder) {
            throw new NotFoundHttpException('Datei wurde nicht gefunden.');
        }

        if (! $this->scopeResolver->assetBelongsToCompanyOfUser($asset, $user)) {
            throw new AccessDeniedHttpException('Zugriff auf diese Datei ist nicht erlaubt.');
        }

        return $asset;
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function createPersonalFolder(MembersUser $user, string $name, ?int $targetFolderId = null): Folder
    {
        return $this->createFolderInScope($this->scopeResolver->resolvePersonalFolder($user, $targetFolderId), $name);
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function createCompanyFolder(MembersUser $user, string $name, ?int $targetFolderId = null): Folder
    {
        return $this->createFolderInScope($this->scopeResolver->resolveCompanyFolder($user, $targetFolderId), $name);
    }

    /**
     * @throws DuplicateFullPathException
     * @throws Exception
     */
    public function deletePersonalFile(MembersUser $user, int $assetId): void
    {
        $this->deleteFileInScope($assetId, $this->scopeResolver->getOrCreateUserFolder($user));
    }

    /**
     * @throws DuplicateFullPathException
     * @throws Exception
     */
    public function deleteCompanyFile(MembersUser $user, int $assetId): void
    {
        $this->deleteFileInScope($assetId, $this->scopeResolver->getOrCreateCompanyFolderForUser($user));
    }

    /**
     * @throws DuplicateFullPathException
     * @throws Exception
     */
    public function deletePersonalFolder(MembersUser $user, int $folderId): void
    {
        $this->deleteFolderInScope($folderId, $this->scopeResolver->getOrCreateUserFolder($user));
    }

    /**
     * @throws DuplicateFullPathException
     * @throws Exception
     */
    public function deleteCompanyFolder(MembersUser $user, int $folderId): void
    {
        $this->deleteFolderInScope($folderId, $this->scopeResolver->getOrCreateCompanyFolderForUser($user));
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function movePersonalFile(MembersUser $user, int $assetId, int $targetFolderId): void
    {
        $this->moveFileInScope($assetId, $targetFolderId, $this->scopeResolver->getOrCreateUserFolder($user));
    }

    /**
     * @throws DuplicateFullPathException
     */
    public function moveCompanyFile(MembersUser $user, int $assetId, int $targetFolderId): void
    {
        $this->moveFileInScope($assetId, $targetFolderId, $this->scopeResolver->getOrCreateCompanyFolderForUser($user));
    }

    /**
     * @throws DuplicateFullPathException
     */
    private function createFolderInScope(Folder $scopeRoot, string $name): Folder
    {
        $candidate = ElementService::getValidKey(trim($name), 'asset');
        if ($candidate === '') {
            throw new InvalidArgumentException('Der Ordnername ist ungültig.');
        }

        $folderName = $this->generateUniqueChildKey($scopeRoot, $candidate);

        $folder = new Folder();
        $folder->setParent($scopeRoot);
        $folder->setFilename($folderName);
        $folder->save();

        return $folder;
    }

    /**
     * @throws Exception
     */
    private function deleteFileInScope(int $assetId, Folder $scopeRoot): void
    {
        $asset = Asset::getById($assetId);

        if (! $asset instanceof Asset || $asset instanceof Folder) {
            throw new NotFoundHttpException('Datei wurde nicht gefunden.');
        }

        $this->scopeResolver->assertElementInScope($asset, $scopeRoot);
        $asset->delete();
    }

    /**
     * @throws Exception
     */
    private function deleteFolderInScope(int $folderId, Folder $scopeRoot): void
    {
        $folder = Folder::getById($folderId);

//        dd($folderId, $folder, $scopeRoot);

        if (! $folder instanceof Folder) {
            throw new NotFoundHttpException('Ordner wurde nicht gefunden.');
        }

        if ($folder->getId() === $scopeRoot->getId()) {
            throw new AccessDeniedHttpException('Der Basisordner darf nicht gelöscht werden.');
        }

        $this->scopeResolver->assertElementInScope($folder, $scopeRoot);

//        if (! $this->isFolderEmpty($folder)) {
//            throw new AccessDeniedHttpException('Nur leere Ordner dürfen gelöscht werden.');
//        }

        $folder->delete();
    }

    /**
     * @throws DuplicateFullPathException
     */
    private function moveFileInScope(int $assetId, int $targetFolderId, Folder $scopeRoot): void
    {
        $asset = Asset::getById($assetId);
        if (! $asset instanceof Asset || $asset instanceof Folder) {
            throw new NotFoundHttpException('Datei wurde nicht gefunden.');
        }

        $targetFolder = Asset::getById($targetFolderId);
        if (! $targetFolder instanceof Folder) {
            throw new NotFoundHttpException('Zielordner wurde nicht gefunden.');
        }

        $this->scopeResolver->assertElementInScope($asset, $scopeRoot);
        $this->scopeResolver->assertElementInScope($targetFolder, $scopeRoot);

        $asset->setParent($targetFolder);
        $asset->setFilename($this->generateUniqueFilename($targetFolder, $asset->getFilename(), $asset->getId()));
        $asset->save();
    }

    private function generateUniqueFilename(Folder $targetFolder, string $currentFilename, int $sourceAssetId): string
    {
        $pathInfo = pathinfo($currentFilename);
        $name = $pathInfo['filename'];
        $extension = isset($pathInfo['extension']) && $pathInfo['extension'] !== '' ? '.' . $pathInfo['extension'] : '';

        $candidate = $name . $extension;
        $counter = 1;

        while (true) {
            $existing = Asset::getByPath(sprintf('%s/%s', rtrim($targetFolder->getFullPath(), '/'), $candidate));

            if (! $existing instanceof Asset || $existing->getId() === $sourceAssetId) {
                return $candidate;
            }

            $candidate = sprintf('%s-%d%s', $name, $counter, $extension);
            $counter++;
        }
    }

    private function isFolderEmpty(Folder $folder): bool
    {
        $children = $folder->getChildren();
        $assets = $children->getAssets();
        $folders = array_values(array_filter($assets, static fn (Asset $asset): bool => $asset instanceof Folder));

        return count($assets) === 0 && count($folders) === 0;
    }

    private function generateUniqueChildKey(Folder $parentFolder, string $baseKey): string
    {
        $candidate = $baseKey;
        $counter = 1;

        while (Asset::getByPath(sprintf('%s/%s', rtrim($parentFolder->getFullPath(), '/'), $candidate)) instanceof Asset) {
            $candidate = sprintf('%s-%d', $baseKey, $counter);
            $counter++;
        }

        return $candidate;
    }

    private function buildStoredFilename(UploadedFile $uploadedFile): string
    {
        $originalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $baseName = ElementService::getValidKey($originalName !== '' ? $originalName : 'upload', 'asset');
        $baseName = $baseName !== '' ? $baseName : 'upload';

        $extension = strtolower((string) pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = strtolower((string) $uploadedFile->guessExtension());
        }

        if ($extension !== '') {
            return sprintf('%s.%s', $baseName, $extension);
        }

        return $baseName;
    }
}
