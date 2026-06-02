<?php

namespace App\Controller\Api;

use App\Service\Auth\CurrentUserProfileProvider;
use App\Service\Profile\ProfileAvatarStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ProfileAvatarController extends AbstractController
{
    #[Route('/api/profile/avatar', name: 'api_profile_avatar_upload', methods: ['POST'])]
    public function uploadApi(
        Request $request,
        CurrentUserProfileProvider $currentUser,
        ProfileAvatarStorage $avatarStorage,
    ): JsonResponse {
        try {
            $profile = $currentUser->requireProfile();
            $file = $request->files->get('avatar');

            if (!$file instanceof UploadedFile) {
                return $this->json(['error' => 'Image avatar manquante.'], JsonResponse::HTTP_BAD_REQUEST);
            }

            $avatarUrl = $avatarStorage->store($profile, $file, $request);

            return $this->json([
                'avatarUrl' => $avatarUrl,
                'profile' => [
                    'id' => $profile->getId(),
                    'displayName' => $profile->getDisplayName(),
                    'username' => $profile->getUsername(),
                    'email' => $profile->getEmail(),
                    'avatarUrl' => $avatarUrl,
                ],
            ]);
        } catch (\Throwable $exception) {
            return $this->json(['error' => $exception->getMessage()], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    #[Route('/app/profile/avatar', name: 'profile_avatar_upload', methods: ['POST'])]
    public function uploadWeb(
        Request $request,
        CurrentUserProfileProvider $currentUser,
        ProfileAvatarStorage $avatarStorage,
    ): Response {
        try {
            $profile = $currentUser->requireProfile();
            $file = $request->files->get('avatar');

            if (!$file instanceof UploadedFile) {
                throw new \InvalidArgumentException('Image avatar manquante.');
            }

            $avatarStorage->store($profile, $file, $request);
        } catch (\Throwable $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return new RedirectResponse($this->generateUrl('vigor_app', ['view' => 'profile']));
    }
}
