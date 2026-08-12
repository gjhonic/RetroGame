<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\UserApiController;
use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\User\Exceptions\EmailAlreadyRegisteredException;
use App\Service\User\ModeratorCreationService;
use App\Service\User\UserMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Мок UserRepository здесь и как стаб (готовые ответы findForAdminList/countForAdminList/find),
 * и как мок (проверка аргументов фильтров/сортировки/пагинации) — строгая проверка "мок без
 * expects()" отключена, как и в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class UserApiControllerTest extends TestCase
{
    private UserRepository&MockObject $userRepository;
    private ModeratorCreationService&MockObject $moderatorCreationService;
    private UserMapper $userMapper;
    private SerializerInterface $serializer;
    private UserApiController $controller;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->moderatorCreationService = $this->createMock(ModeratorCreationService::class);
        $this->userMapper = new UserMapper();
        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);

        $this->controller = new UserApiController();
        // AbstractController::json() проверяет container->has('serializer') — пустой
        // контейнер без сервисов заставляет его отдать обычный JsonResponse.
        $this->controller->setContainer(new Container());
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testListReturnsPageWithDefaultSortingAndPagination(): void
    {
        $user = (new User('player@retrogame.local', 'hash'))->setNickname('Player One');

        $this->userRepository->method('countForAdminList')->willReturn(1);
        $this->userRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'email', 'ASC', 25, 0)
            ->willReturn([$user]);

        $response = $this->controller->list(new Request(), $this->userRepository, $this->userMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
        self::assertSame([
            'id' => null,
            'email' => 'player@retrogame.local',
            'nickname' => 'Player One',
            'role' => 'ROLE_USER',
            'createdAt' => $user->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'lastLoginAt' => null,
        ], $data['items'][0]);
    }

    public function testListPassesFiltersAndSortingToRepository(): void
    {
        $this->userRepository->method('countForAdminList')->willReturn(0);
        $this->userRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(['email' => 'player', 'role' => 'ROLE_MODERATOR'], 'createdAt', 'DESC', 10, 0)
            ->willReturn([]);

        $request = new Request([
            'filters' => ['email' => ' player ', 'role' => ' ROLE_MODERATOR ', 'unknownField' => 'ignored'],
            'sortBy' => 'createdAt',
            'sortDir' => 'desc',
            'perPage' => '10',
        ]);
        $this->controller->list($request, $this->userRepository, $this->userMapper);
    }

    public function testListFallsBackToEmailSortingForUnknownSortByAndClampsPerPage(): void
    {
        $this->userRepository->method('countForAdminList')->willReturn(0);
        $this->userRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'email', 'ASC', 100, 0)
            ->willReturn([]);

        $request = new Request(['sortBy' => 'unknownField', 'perPage' => '9999']);
        $this->controller->list($request, $this->userRepository, $this->userMapper);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->userRepository->method('countForAdminList')->willReturn(1);
        $this->userRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], 'email', 'ASC', 25, 0)
            ->willReturn([]);

        $request = new Request(['page' => '999']);
        $response = $this->controller->list($request, $this->userRepository, $this->userMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
        self::assertSame(1, $data['totalPages']);
    }

    public function testShowReturnsFullDetail(): void
    {
        $user = (new User('moderator@retrogame.local', 'hash', UserRole::Moderator))->setNickname('Mod');

        $this->userRepository->expects($this->once())->method('find')->with(42)->willReturn($user);

        $response = $this->controller->show(42, $this->userRepository, $this->userMapper);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('moderator@retrogame.local', $data['email']);
        self::assertSame('ROLE_MODERATOR', $data['role']);
        self::assertNull($data['avatarUrl']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->userRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(999, $this->userRepository, $this->userMapper);
    }

    public function testCreateModeratorReturnsCreatedUserOnValidRequest(): void
    {
        $user = new User('moderator@retrogame.local', 'hashed-password', UserRole::Moderator);
        $this->moderatorCreationService->expects($this->once())
            ->method('create')
            ->willReturn($user);

        $request = new Request(
            content: json_encode([
                'email' => 'moderator@retrogame.local',
                'password' => 'secret123',
                'nickname' => 'Moderator One',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->controller->createModerator(
            $request,
            $this->serializer,
            $this->validator(),
            $this->moderatorCreationService,
            $this->userMapper,
        );

        self::assertSame(201, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertSame('moderator@retrogame.local', $data['email']);
        self::assertSame('ROLE_MODERATOR', $data['role']);
    }

    public function testCreateModeratorReturnsValidationErrorsForInvalidPayload(): void
    {
        $this->moderatorCreationService->expects($this->never())->method('create');

        $request = new Request(
            content: json_encode([
                'email' => 'not-an-email',
                'password' => 'short',
                'nickname' => '',
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->controller->createModerator(
            $request,
            $this->serializer,
            $this->validator(),
            $this->moderatorCreationService,
            $this->userMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('email', $data['errors']);
        self::assertArrayHasKey('password', $data['errors']);
        self::assertArrayHasKey('nickname', $data['errors']);
    }

    public function testCreateModeratorThrowsConflictWhenEmailAlreadyRegistered(): void
    {
        $this->moderatorCreationService->method('create')
            ->willThrowException(
                new EmailAlreadyRegisteredException('Пользователь с таким email уже зарегистрирован.'),
            );

        $request = new Request(
            content: json_encode([
                'email' => 'moderator@retrogame.local',
                'password' => 'secret123',
                'nickname' => 'Moderator One',
            ], \JSON_THROW_ON_ERROR),
        );

        $this->expectException(ConflictHttpException::class);

        $this->controller->createModerator(
            $request,
            $this->serializer,
            $this->validator(),
            $this->moderatorCreationService,
            $this->userMapper,
        );
    }
}
