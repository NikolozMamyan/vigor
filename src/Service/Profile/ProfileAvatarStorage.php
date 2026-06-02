<?php

namespace App\Service\Profile;

use App\Entity\UserProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;

final class ProfileAvatarStorage
{
    private const MAX_SIZE = 5_242_880;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $uploadDir,
        private readonly string $publicPath,
    ) {
    }

    public function store(UserProfile $profile, UploadedFile $file, Request $request): string
    {
        if (!$file->isValid()) {
            throw new \InvalidArgumentException('Image avatar invalide.');
        }

        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('Image avatar trop lourde. Maximum 5 Mo.');
        }

        $mime = (string) $file->getMimeType();
        $extension = self::MIME_EXTENSIONS[$mime] ?? null;

        if (null === $extension) {
            throw new \InvalidArgumentException('Format avatar invalide. Utilise JPG, PNG ou WEBP.');
        }

        if (!is_dir($this->uploadDir) && !mkdir($concurrentDirectory = $this->uploadDir, 0775, true) && !is_dir($concurrentDirectory)) {
            throw new \RuntimeException('Impossible de creer le dossier avatars.');
        }

        $previousUrl = $profile->getAvatarUrl();
        $fileName = sprintf('avatar-%s-%s.%s', $profile->getId() ?? 'new', bin2hex(random_bytes(10)), $extension);
        $file->move($this->uploadDir, $fileName);

        $avatarUrl = $this->absoluteUrl($request, rtrim($this->publicPath, '/').'/'.$fileName);
        $profile->setAvatarUrl($avatarUrl);
        $this->entityManager->flush();
        $this->deletePreviousLocalAvatar($previousUrl, $request);

        return $avatarUrl;
    }

    private function absoluteUrl(Request $request, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }

    private function deletePreviousLocalAvatar(?string $avatarUrl, Request $request): void
    {
        if (null === $avatarUrl || '' === $avatarUrl) {
            return;
        }

        $host = $request->getSchemeAndHttpHost();
        $path = str_starts_with($avatarUrl, $host)
            ? substr($avatarUrl, strlen($host))
            : parse_url($avatarUrl, \PHP_URL_PATH);

        if (!is_string($path) || !str_starts_with($path, rtrim($this->publicPath, '/').'/')) {
            return;
        }

        $fileName = basename($path);
        $target = $this->uploadDir.\DIRECTORY_SEPARATOR.$fileName;

        if (is_file($target)) {
            @unlink($target);
        }
    }
}
