<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\MemberFileUploadType;
use App\Form\MemberFolderCreateType;
use App\Service\AltchaService;
use App\Service\MemberFileStorageService;
use Pimcore\Controller\FrontendController;
use Pimcore\Model\Asset;
use Pimcore\Model\Asset\Folder;
use Pimcore\Model\DataObject\MembersUser;
use Pimcore\Model\Element\DuplicateFullPathException;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Attribute\Route;

class MemberFileController extends FrontendController
{
    private const string TAB_PERSONAL = 'personal';

    private const string TAB_COMPANY = 'company';

    public function __construct(
        private readonly MemberFileStorageService $memberFileStorageService,
        private readonly AltchaService $altchaService
    ) {
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/my-files', name: 'app_member_files_index', methods: ['GET'])]
    public function indexAction(Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $personalFolderId = $this->resolveFolderId($request, 'folder');

        return $this->renderFilePage(
            $user,
            self::TAB_PERSONAL,
            $this->createPersonalUploadForm(),
            $this->memberFileStorageService->hasCompany($user) ? $this->createCompanyUploadForm() : null,
            $this->createPersonalFolderForm(),
            $this->memberFileStorageService->hasCompany($user) ? $this->createCompanyFolderForm() : null,
            Response::HTTP_OK,
            $personalFolderId
        );
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/company-files', name: 'app_company_files_index', methods: ['GET'])]
    public function companyIndexAction(Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $this->denyAccessUnlessUserHasCompany($user);
        $companyFolderId = $this->resolveFolderId($request, 'folder');

        return $this->renderFilePage(
            $user,
            self::TAB_COMPANY,
            $this->createPersonalUploadForm(),
            $this->createCompanyUploadForm(),
            $this->createPersonalFolderForm(),
            $this->createCompanyFolderForm(),
            Response::HTTP_OK,
            null,
            $companyFolderId
        );
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/my-files/upload', name: 'app_member_files_upload', methods: ['POST'])]
    public function uploadAction(Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $personalFolderId = $this->resolveFolderId($request, 'folderId');
        $form = $this->createPersonalUploadForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->appendAltchaError($form, $request, 'member_files_upload_personal');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('file')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                $this->memberFileStorageService->storeUploadedFile($user, $uploadedFile, $personalFolderId);
                $this->addFlash('success', 'Datei wurde hochgeladen.');
            }

            return $this->redirectToRoute('app_member_files_index', $this->buildFolderRouteParams($personalFolderId));
        }

        return $this->renderFilePage(
            $user,
            self::TAB_PERSONAL,
            $form,
            $this->memberFileStorageService->hasCompany($user) ? $this->createCompanyUploadForm() : null,
            $this->createPersonalFolderForm(),
            $this->memberFileStorageService->hasCompany($user) ? $this->createCompanyFolderForm() : null,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $personalFolderId
        );
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/company-files/upload', name: 'app_company_files_upload', methods: ['POST'])]
    public function companyUploadAction(Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $this->denyAccessUnlessUserHasCompany($user);
        $companyFolderId = $this->resolveFolderId($request, 'folderId');

        $form = $this->createCompanyUploadForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->appendAltchaError($form, $request, 'member_files_upload_company');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadedFile = $form->get('file')->getData();
            if ($uploadedFile instanceof UploadedFile) {
                $this->memberFileStorageService->storeCompanyUploadedFile($user, $uploadedFile, $companyFolderId);
                $this->addFlash('success', 'Datei wurde hochgeladen.');
            }

            return $this->redirectToRoute('app_company_files_index', $this->buildFolderRouteParams($companyFolderId));
        }

        return $this->renderFilePage(
            $user,
            self::TAB_COMPANY,
            $this->createPersonalUploadForm(),
            $form,
            $this->createPersonalFolderForm(),
            $this->createCompanyFolderForm(),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            null,
            $companyFolderId
        );
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/my-files/folders', name: 'app_member_files_create_folder', methods: ['POST'])]
    public function createFolderAction(Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $personalFolderId = $this->resolveFolderId($request, 'folderId');
        $form = $this->createPersonalFolderForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->appendAltchaError($form, $request, 'member_files_folder_personal');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $folderName = (string) $form->get('name')->getData();
            $this->memberFileStorageService->createPersonalFolder($user, $folderName, $personalFolderId);
            $this->addFlash('success', 'Ordner wurde erstellt.');

            return $this->redirectToRoute('app_member_files_index', $this->buildFolderRouteParams($personalFolderId));
        }

        return $this->renderFilePage(
            $user,
            self::TAB_PERSONAL,
            $this->createPersonalUploadForm(),
            $this->memberFileStorageService->hasCompany($user) ? $this->createCompanyUploadForm() : null,
            $form,
            $this->memberFileStorageService->hasCompany($user) ? $this->createCompanyFolderForm() : null,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            $personalFolderId
        );
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/company-files/folders', name: 'app_company_files_create_folder', methods: ['POST'])]
    public function companyCreateFolderAction(Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $this->denyAccessUnlessUserHasCompany($user);
        $companyFolderId = $this->resolveFolderId($request, 'folderId');

        $form = $this->createCompanyFolderForm();
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->appendAltchaError($form, $request, 'member_files_folder_company');
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $folderName = (string) $form->get('name')->getData();
            $this->memberFileStorageService->createCompanyFolder($user, $folderName, $companyFolderId);
            $this->addFlash('success', 'Ordner wurde erstellt.');

            return $this->redirectToRoute('app_company_files_index', $this->buildFolderRouteParams($companyFolderId));
        }

        return $this->renderFilePage(
            $user,
            self::TAB_COMPANY,
            $this->createPersonalUploadForm(),
            $this->createCompanyUploadForm(),
            $this->createPersonalFolderForm(),
            $form,
            Response::HTTP_UNPROCESSABLE_ENTITY,
            null,
            $companyFolderId
        );
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/my-files/{assetId}/delete', name: 'app_member_files_delete', requirements: [
        'assetId' => '\\d+',
    ], methods: ['POST'])]
    public function deleteFileAction(int $assetId, Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $personalFolderId = $this->resolveFolderId($request, 'folderId');

        if (! $this->isCsrfTokenValid('delete-personal-file-' . $assetId, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Ungültiges CSRF-Token.');
        }

        $this->memberFileStorageService->deletePersonalFile($user, $assetId);
        $this->addFlash('success', 'Datei wurde gelöscht.');

        return $this->redirectToRoute('app_member_files_index', $this->buildFolderRouteParams($personalFolderId));
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/company-files/{assetId}/delete', name: 'app_company_files_delete', requirements: [
        'assetId' => '\\d+',
    ], methods: ['POST'])]
    public function companyDeleteFileAction(int $assetId, Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $companyFolderId = $this->resolveFolderId($request, 'folderId');

        if (! $this->isCsrfTokenValid('delete-company-file-' . $assetId, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Ungültiges CSRF-Token.');
        }

        $this->memberFileStorageService->deleteCompanyFile($user, $assetId);
        $this->addFlash('success', 'Datei wurde gelöscht.');

        return $this->redirectToRoute('app_company_files_index', $this->buildFolderRouteParams($companyFolderId));
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/my-files/{assetId}/move', name: 'app_member_files_move', requirements: [
        'assetId' => '\\d+',
    ], methods: ['POST'])]
    public function moveFileAction(int $assetId, Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $personalFolderId = $this->resolveFolderId($request, 'folderId');

        if (! $this->isCsrfTokenValid('move-personal-file-' . $assetId, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Ungültiges CSRF-Token.');
        }

        $targetFolderId = (int) $request->request->get('targetFolderId', 0);
        $this->memberFileStorageService->movePersonalFile($user, $assetId, $targetFolderId);
        $this->addFlash('success', 'Datei wurde verschoben.');

        return $this->redirectToRoute('app_member_files_index', $this->buildFolderRouteParams($personalFolderId));
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/company-files/{assetId}/move', name: 'app_company_files_move', requirements: [
        'assetId' => '\\d+',
    ], methods: ['POST'])]
    public function companyMoveFileAction(int $assetId, Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $companyFolderId = $this->resolveFolderId($request, 'folderId');

        if (! $this->isCsrfTokenValid('move-company-file-' . $assetId, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Ungültiges CSRF-Token.');
        }

        $targetFolderId = (int) $request->request->get('targetFolderId', 0);
        $this->memberFileStorageService->moveCompanyFile($user, $assetId, $targetFolderId);
        $this->addFlash('success', 'Datei wurde verschoben.');

        return $this->redirectToRoute('app_company_files_index', $this->buildFolderRouteParams($companyFolderId));
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/my-files/folders/{folderId}/delete', name: 'app_member_files_delete_folder', requirements: [
        'folderId' => '\\d+',
    ], methods: ['POST'])]
    public function deleteFolderAction(int $folderId, Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $currentFolderId = $request->request->getInt('folderId');

        if (! $this->isCsrfTokenValid('delete-personal-folder-' . $folderId, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Ungültiges CSRF-Token.');
        }

        $this->memberFileStorageService->deletePersonalFolder($user, $folderId);
        $this->addFlash('success', 'Ordner wurde gelöscht.');

        return $this->redirectToRoute('app_member_files_index', $this->buildFolderRouteParams($currentFolderId));
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/company-files/folders/{folderId}/delete', name: 'app_company_files_delete_folder', requirements: [
        'folderId' => '\\d+',
    ], methods: ['POST'])]
    public function companyDeleteFolderAction(int $folderId, Request $request): Response
    {
        $user = $this->resolveMemberUser();
        $currentFolderId = $request->request->getInt('folderId');

        if (! $this->isCsrfTokenValid('delete-company-folder-' . $folderId, (string) $request->request->get('_token'))) {
            throw new AccessDeniedHttpException('Ungültiges CSRF-Token.');
        }

        $this->memberFileStorageService->deleteCompanyFolder($user, $folderId);
        $this->addFlash('success', 'Ordner wurde gelöscht.');

        return $this->redirectToRoute('app_company_files_index', $this->buildFolderRouteParams($currentFolderId));
    }

    #[Route(path: '/my-files/{assetId}/download', name: 'app_member_files_download', requirements: [
        'assetId' => '\\d+',
    ], methods: ['GET'])]
    public function downloadAction(int $assetId): Response
    {
        $user = $this->resolveMemberUser();
        $asset = $this->memberFileStorageService->getOwnedAssetOrFail($assetId, $user);

        return $this->createDownloadResponse($asset);
    }

    /**
     * @throws DuplicateFullPathException
     */
    #[Route(path: '/company-files/{assetId}/download', name: 'app_company_files_download', requirements: [
        'assetId' => '\\d+',
    ], methods: ['GET'])]
    public function companyDownloadAction(int $assetId): Response
    {
        $user = $this->resolveMemberUser();
        $asset = $this->memberFileStorageService->getOwnedCompanyAssetOrFail($assetId, $user);

        return $this->createDownloadResponse($asset);
    }

    private function resolveMemberUser(): MembersUser
    {
        $user = $this->getUser();

        if (! $user instanceof MembersUser) {
            throw new AccessDeniedHttpException('Bitte melde dich zuerst an.');
        }

        return $user;
    }

    private function createDownloadResponse(Asset $asset): StreamedResponse
    {
        $response = new StreamedResponse(static function () use ($asset): void {
            fpassthru($asset->getStream());
        });

        $response->headers->set('Content-Type', $asset->getMimetype());
        $response->headers->set('Content-Length', (string) $asset->getFileSize());
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $asset->getFilename()
        ));

        return $response;
    }

    private function createPersonalUploadForm(): FormInterface
    {
        return $this->createForm(MemberFileUploadType::class, null, [
            'action' => $this->generateUrl('app_member_files_upload'),
            'method' => 'POST',
        ]);
    }

    private function createCompanyUploadForm(): FormInterface
    {
        return $this->createForm(MemberFileUploadType::class, null, [
            'action' => $this->generateUrl('app_company_files_upload'),
            'method' => 'POST',
        ]);
    }

    private function createPersonalFolderForm(): FormInterface
    {
        return $this->createForm(MemberFolderCreateType::class, null, [
            'action' => $this->generateUrl('app_member_files_create_folder'),
            'method' => 'POST',
        ]);
    }

    private function createCompanyFolderForm(): FormInterface
    {
        return $this->createForm(MemberFolderCreateType::class, null, [
            'action' => $this->generateUrl('app_company_files_create_folder'),
            'method' => 'POST',
        ]);
    }

    private function denyAccessUnlessUserHasCompany(MembersUser $user): void
    {
        if (! $this->memberFileStorageService->hasCompany($user)) {
            throw new AccessDeniedHttpException('Kein Firmenzugriff vorhanden.');
        }
    }

    /**
     * @throws DuplicateFullPathException
     */
    private function renderFilePage(
        MembersUser $user,
        string $activeTab,
        FormInterface $personalUploadForm,
        ?FormInterface $companyUploadForm,
        FormInterface $personalFolderForm,
        ?FormInterface $companyFolderForm,
        int $statusCode = Response::HTTP_OK,
        ?int $personalFolderId = null,
        ?int $companyFolderId = null
    ): Response {
        $canAccessCompanyFiles = $this->memberFileStorageService->hasCompany($user);
        $personalRootFolder = $this->memberFileStorageService->resolvePersonalFolder($user);
        $personalCurrentFolder = $this->memberFileStorageService->resolvePersonalFolder($user, $personalFolderId);
        $companyRootFolder = $canAccessCompanyFiles ? $this->memberFileStorageService->resolveCompanyFolder($user) : null;
        $companyCurrentFolder = $canAccessCompanyFiles ? $this->memberFileStorageService->resolveCompanyFolder($user, $companyFolderId) : null;

        $activeCurrentFolder = $activeTab === self::TAB_COMPANY ? $companyCurrentFolder : $personalCurrentFolder;
        $activeRootFolder = $activeTab === self::TAB_COMPANY ? $companyRootFolder : $personalRootFolder;
        $personalMoveTargetFolders = $this->buildMoveTargetOptions(
            $this->memberFileStorageService->getPersonalMoveTargetFolders($user),
            $personalRootFolder
        );
        $companyMoveTargetFolders = $canAccessCompanyFiles && $companyRootFolder instanceof Folder
            ? $this->buildMoveTargetOptions(
                $this->memberFileStorageService->getCompanyMoveTargetFolders($user),
                $companyRootFolder
            )
            : [];

        $currentBreadcrumbs = [];
        $parentFolderId = null;
        $currentFolderPath = $activeRootFolder->getFilename();

        if ($activeCurrentFolder instanceof Asset && $activeRootFolder instanceof Asset) {
            $cursor = $activeCurrentFolder;

            while ($cursor instanceof Asset) {
                $currentBreadcrumbs[] = [
                    'id' => $cursor->getId(),
                    'name' => $cursor->getFilename(),
                ];

                if ($cursor->getId() === $activeRootFolder->getId()) {
                    break;
                }

                $cursor = $cursor->getParent();;
            }

            $currentBreadcrumbs = array_reverse($currentBreadcrumbs);
            if ($currentBreadcrumbs != []) {
                $currentFolderPath = implode('/', array_map(
                    static fn (array $breadcrumb): string => $breadcrumb['name'],
                    $currentBreadcrumbs
                ));

                if (count($currentBreadcrumbs) > 1) {
                    $parentFolderId = $currentBreadcrumbs[count($currentBreadcrumbs) - 2]['id'];
                }
            }
        }

        return $this->render('member_files/index.html.twig', [
            'activeTab' => $activeTab,
            'canAccessCompanyFiles' => $canAccessCompanyFiles,
            'personalFiles' => $this->memberFileStorageService->getUserFiles($user, $personalFolderId),
            'personalFolders' => $this->memberFileStorageService->getUserFolders($user, $personalFolderId),
            'personalMoveTargetFolders' => $personalMoveTargetFolders,
            'personalUploadForm' => $personalUploadForm->createView(),
            'personalFolderForm' => $personalFolderForm->createView(),
            'companyFiles' => $canAccessCompanyFiles ? $this->memberFileStorageService->getCompanyFiles($user, $companyFolderId) : [],
            'companyFolders' => $canAccessCompanyFiles ? $this->memberFileStorageService->getCompanyFolders($user, $companyFolderId) : [],
            'companyMoveTargetFolders' => $companyMoveTargetFolders,
            'companyUploadForm' => $canAccessCompanyFiles && $companyUploadForm instanceof FormInterface
                ? $companyUploadForm->createView()
                : null,
            'companyFolderForm' => $canAccessCompanyFiles && $companyFolderForm instanceof FormInterface
                ? $companyFolderForm->createView()
                : null,
            'currentFolderId' => $activeCurrentFolder instanceof Asset ? $activeCurrentFolder->getId() : null,
            'currentFolderPath' => $currentFolderPath,
            'currentBreadcrumbs' => $currentBreadcrumbs,
            'currentFolderName' => $activeCurrentFolder instanceof Asset ? $activeCurrentFolder->getFilename() : '/',
            'parentFolderId' => $parentFolderId,
        ], new Response(status: $statusCode));
    }

    private function resolveFolderId(Request $request, string $key): ?int
    {
        $folderId = (int) $request->get($key, 0);

        return $folderId > 0 ? $folderId : null;
    }

    private function appendAltchaError(FormInterface $form, Request $request, string $context): void
    {
        $payload = (string) $request->request->get('altcha', '');

        if ($this->altchaService->isValidPayload($payload, $context)) {
            return;
        }

        $form->addError(new FormError('Bitte bestaetige zuerst den Spam-Schutz.'));
    }

    /**
     * @return array<string, int>
     */
    private function buildFolderRouteParams(?int $folderId): array
    {
        return $folderId !== null ? [
            'folder' => $folderId,
        ] : [];
    }

    /**
     * @param array<int, Folder> $folders
     *
     * @return array<int, array{id: int, label: string}>
     */
    private function buildMoveTargetOptions(array $folders, Folder $scopeRoot): array
    {
        $scopeRootPath = rtrim($scopeRoot->getFullPath(), '/');
        $scopeRootName = rawurldecode($scopeRoot->getFilename());
        $options = [];

        foreach ($folders as $folder) {
            if (! $folder instanceof Folder) {
                continue;
            }

            $folderPath = rtrim($folder->getFullPath(), '/');

            if ($folderPath === $scopeRootPath) {
                $label = $scopeRootName;
            } elseif (str_starts_with($folderPath, $scopeRootPath . '/')) {
                $relativePath = ltrim(substr($folderPath, strlen($scopeRootPath)), '/');
                $relativeSegments = $relativePath !== '' ? explode('/', $relativePath) : [];
                $decodedSegments = array_map(static fn (string $segment): string => rawurldecode($segment), $relativeSegments);
                $label = $decodedSegments !== []
                    ? sprintf('%s/%s', $scopeRootName, implode('/', $decodedSegments))
                    : $scopeRootName;
            } else {
                $label = rawurldecode($folder->getFilename());
            }

            $options[] = [
                'id' => $folder->getId(),
                'label' => $label,
            ];
        }

        return $options;
    }
}
