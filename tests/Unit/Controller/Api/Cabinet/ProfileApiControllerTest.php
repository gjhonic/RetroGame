<?php

namespace App\Tests\Unit\Controller\Api\Cabinet;

use App\Controller\Api\Cabinet\ProfileApiController;
use App\Entity\User;
use App\Service\User\AvatarUploadService;
use App\Service\User\ChangePasswordService;
use App\Service\User\Exceptions\InvalidCurrentPasswordException;
use App\Service\User\UserMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Мок ChangePasswordService/AvatarUploadService здесь и как стаб (готовое
 * исключение в testUploadAvatar*), и как мок (проверка вызовов в остальных
 * тестах) — строгая проверка "мок без expects()" отключена, как и в
 * RegistrationApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class ProfileApiControllerTest extends TestCase
{
    private ChangePasswordService&MockObject $changePasswordService;
    private AvatarUploadService&MockObject $avatarUploadService;
    private ProfileApiController $controller;
    private SerializerInterface $serializer;
    private User $user;

    protected function setUp(): void
    {
        $this->changePasswordService = $this->createMock(ChangePasswordService::class);
        $this->avatarUploadService = $this->createMock(AvatarUploadService::class);
        $this->user = new User('player@retrogame.local', 'hash');

        $this->controller = new ProfileApiController();
        $this->controller->setContainer(new Container());

        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testShowReturnsCurrentUser(): void
    {
        $response = $this->controller->show($this->user, new UserMapper());

        self::assertSame(200, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('player@retrogame.local', $data['email']);
    }

    public function testChangePasswordReturnsSuccessOnValidRequest(): void
    {
        $this->changePasswordService->expects($this->once())->method('changePassword');

        $request = new Request(content: json_encode([
            'currentPassword' => 'old-secret',
            'newPassword' => 'new-secret123',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->changePassword(
            $request,
            $this->serializer,
            $this->validator(),
            $this->changePasswordService,
            $this->user,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testChangePasswordReturnsValidationErrorsForShortPassword(): void
    {
        $this->changePasswordService->expects($this->never())->method('changePassword');

        $request = new Request(content: json_encode([
            'currentPassword' => 'old-secret',
            'newPassword' => 'short',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->changePassword(
            $request,
            $this->serializer,
            $this->validator(),
            $this->changePasswordService,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('newPassword', $data['errors']);
    }

    public function testChangePasswordReturnsValidationErrorWhenCurrentPasswordIsInvalid(): void
    {
        $this->changePasswordService->method('changePassword')
            ->willThrowException(new InvalidCurrentPasswordException('Неверный текущий пароль.'));

        $request = new Request(content: json_encode([
            'currentPassword' => 'wrong-secret',
            'newPassword' => 'new-secret123',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->changePassword(
            $request,
            $this->serializer,
            $this->validator(),
            $this->changePasswordService,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('currentPassword', $data['errors']);
    }

    public function testUploadAvatarReturnsUpdatedUserOnValidFile(): void
    {
        $this->avatarUploadService->expects($this->once())
            ->method('upload')
            ->willReturn($this->user);

        $request = $this->requestWithAvatar($this->makeImage(100, 100));

        $response = $this->controller->uploadAvatar(
            $request,
            $this->validator(),
            $this->avatarUploadService,
            new UserMapper(),
            $this->user,
        );

        self::assertSame(200, $response->getStatusCode());
    }

    public function testUploadAvatarReturnsValidationErrorWhenImageTooLarge(): void
    {
        $this->avatarUploadService->expects($this->never())->method('upload');

        $request = $this->requestWithAvatar($this->makeImage(500, 500));

        $response = $this->controller->uploadAvatar(
            $request,
            $this->validator(),
            $this->avatarUploadService,
            new UserMapper(),
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('file', $data['errors']);
    }

    /**
     * @param positive-int $width
     * @param positive-int $height
     */
    private function makeImage(int $width, int $height): UploadedFile
    {
        $path = sys_get_temp_dir() . '/retrogame-avatar-test-' . uniqid() . '.jpg';
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path);
        imagedestroy($image);

        return new UploadedFile($path, 'avatar.jpg', 'image/jpeg', null, true);
    }

    private function requestWithAvatar(UploadedFile $file): Request
    {
        return new Request(files: ['avatar' => $file]);
    }
}
