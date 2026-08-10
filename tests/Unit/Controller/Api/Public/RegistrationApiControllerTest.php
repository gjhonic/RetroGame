<?php

namespace App\Tests\Unit\Controller\Api\Public;

use App\Controller\Api\Public\RegistrationApiController;
use App\Entity\User;
use App\Service\User\Exceptions\EmailAlreadyRegisteredException;
use App\Service\User\UserMapper;
use App\Service\User\UserRegistrationService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Мок UserRegistrationService здесь и как стаб (готовый ответ/исключение в
 * testRegisterReturnsCreatedUserOnValidRequest и testRegisterThrowsConflictWhenEmailAlreadyRegistered),
 * и как мок (проверка вызовов в остальных тестах) — строгая проверка "мок без expects()" отключена,
 * как и в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class RegistrationApiControllerTest extends TestCase
{
    private UserRegistrationService&MockObject $userRegistrationService;
    private RegistrationApiController $controller;
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->userRegistrationService = $this->createMock(UserRegistrationService::class);

        $this->controller = new RegistrationApiController();
        $this->controller->setContainer(new Container());

        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testRegisterReturnsCreatedUserOnValidRequest(): void
    {
        $user = (new User('player@retrogame.local', 'hashed-password'))->setNickname('Player One');
        $this->userRegistrationService->expects($this->once())
            ->method('register')
            ->willReturn($user);

        $request = new Request(
            content: json_encode([
                'email' => 'player@retrogame.local',
                'password' => 'secret123',
                'nickname' => 'Player One',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->controller->register(
            $request,
            $this->serializer,
            $this->validator(),
            $this->userRegistrationService,
            new UserMapper(),
        );

        self::assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('player@retrogame.local', $data['email']);
    }

    public function testRegisterReturnsValidationErrorsForInvalidPayload(): void
    {
        $this->userRegistrationService->expects($this->never())->method('register');

        $request = new Request(
            content: json_encode([
                'email' => 'not-an-email',
                'password' => 'short',
                'nickname' => '',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->controller->register(
            $request,
            $this->serializer,
            $this->validator(),
            $this->userRegistrationService,
            new UserMapper(),
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('email', $data['errors']);
        self::assertArrayHasKey('password', $data['errors']);
        self::assertArrayHasKey('nickname', $data['errors']);
    }

    public function testRegisterThrowsConflictWhenEmailAlreadyRegistered(): void
    {
        $this->userRegistrationService->method('register')
            ->willThrowException(
                new EmailAlreadyRegisteredException('Пользователь с таким email уже зарегистрирован.'),
            );

        $request = new Request(
            content: json_encode([
                'email' => 'player@retrogame.local',
                'password' => 'secret123',
                'nickname' => 'Player One',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(ConflictHttpException::class);

        $this->controller->register(
            $request,
            $this->serializer,
            $this->validator(),
            $this->userRegistrationService,
            new UserMapper(),
        );
    }
}
