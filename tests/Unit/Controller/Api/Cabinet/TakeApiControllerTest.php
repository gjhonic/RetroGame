<?php

namespace App\Tests\Unit\Controller\Api\Cabinet;

use App\Controller\Api\Cabinet\TakeApiController;
use App\Entity\Enum\TakeReactionType;
use App\Entity\Game;
use App\Entity\Take;
use App\Entity\TakeComment;
use App\Entity\TakeReaction;
use App\Entity\User;
use App\Repository\TakeCommentRepository;
use App\Repository\TakeReactionRepository;
use App\Repository\TakeRepository;
use App\Service\Take\CreateTakeCommentService;
use App\Service\Take\CreateTakeService;
use App\Service\Take\Exceptions\GameNotFoundException;
use App\Service\Take\TakeMapper;
use App\Service\Take\TakeReactionService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Мок сервисов здесь и как стаб (готовые ответы/исключения), и как мок
 * (проверка вызовов) — строгая проверка "мок без expects()" отключена, как
 * и в ProfileApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class TakeApiControllerTest extends TestCase
{
    private CreateTakeService&MockObject $createTakeService;
    private CreateTakeCommentService&MockObject $createTakeCommentService;
    private TakeReactionService&MockObject $takeReactionService;
    private TakeReactionRepository&MockObject $takeReactionRepository;
    private TakeRepository&MockObject $takeRepository;
    private TakeCommentRepository&MockObject $takeCommentRepository;
    private TakeMapper $takeMapper;
    private TakeApiController $controller;
    private SerializerInterface $serializer;
    private User $user;
    private Take $take;

    protected function setUp(): void
    {
        $this->createTakeService = $this->createMock(CreateTakeService::class);
        $this->createTakeCommentService = $this->createMock(CreateTakeCommentService::class);
        $this->takeReactionService = $this->createMock(TakeReactionService::class);
        $this->takeReactionRepository = $this->createMock(TakeReactionRepository::class);
        $this->takeRepository = $this->createMock(TakeRepository::class);
        $this->takeCommentRepository = $this->createMock(TakeCommentRepository::class);
        $this->takeMapper = new TakeMapper();

        $this->controller = new TakeApiController();
        $this->controller->setContainer(new Container());

        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $this->user = new User('player@retrogame.local', 'hash');
        $this->take = new Take($this->user, new Game('Half-Life', 'half-life'), 'Existing take');
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testListReturnsAuthorTakesWithReactionAndCommentCounts(): void
    {
        $this->takeRepository->method('countForAuthor')->willReturn(1);
        $this->takeRepository->expects($this->once())
            ->method('findForAuthor')
            ->with($this->user, null, 20, 0)
            ->willReturn([$this->take]);
        $this->takeReactionRepository->method('countByTypeForTakes')->willReturn([0 => ['like' => 2, 'dislike' => 0]]);
        $this->takeReactionRepository->method('findTypesForTakesAndUser')->willReturn([0 => 'like']);
        $this->takeCommentRepository->method('countForTake')->willReturn(1);

        $response = $this->controller->list(
            new Request(),
            $this->takeRepository,
            $this->takeReactionRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(2, $data['items'][0]['likeCount']);
        self::assertSame(1, $data['items'][0]['commentCount']);
        self::assertSame('like', $data['items'][0]['myReaction']);
    }

    public function testListPassesSinceFilterAsDateTimeToRepository(): void
    {
        $this->takeRepository->method('countForAuthor')->willReturn(0);
        $this->takeRepository->expects($this->once())
            ->method('findForAuthor')
            ->with(
                $this->user,
                self::callback(static fn ($since) => $since instanceof \DateTimeImmutable
                    && $since->format('Y-m-d') === '2026-08-05'),
                20,
                0,
            )
            ->willReturn([]);
        $this->takeReactionRepository->method('countByTypeForTakes')->willReturn([]);
        $this->takeReactionRepository->method('findTypesForTakesAndUser')->willReturn([]);

        $request = new Request(['since' => '2026-08-05T00:00:00+00:00']);
        $this->controller->list(
            $request,
            $this->takeRepository,
            $this->takeReactionRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
            $this->user,
        );
    }

    public function testListIgnoresInvalidSinceFilter(): void
    {
        $this->takeRepository->method('countForAuthor')->willReturn(0);
        $this->takeRepository->expects($this->once())
            ->method('findForAuthor')
            ->with($this->user, null, 20, 0)
            ->willReturn([]);
        $this->takeReactionRepository->method('countByTypeForTakes')->willReturn([]);
        $this->takeReactionRepository->method('findTypesForTakesAndUser')->willReturn([]);

        $request = new Request(['since' => 'not-a-date']);
        $this->controller->list(
            $request,
            $this->takeRepository,
            $this->takeReactionRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
            $this->user,
        );
    }

    public function testCreateReturnsCreatedTakeOnValidRequest(): void
    {
        $this->createTakeService->expects($this->once())
            ->method('create')
            ->willReturn(new Take($this->user, new Game('Half-Life', 'half-life'), 'Great game.'));

        $request = new Request(content: json_encode(['gameId' => 1, 'text' => 'Great game.'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createTakeService,
            $this->takeMapper,
            $this->user,
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testCreateReturnsValidationErrorForTooLongText(): void
    {
        $this->createTakeService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'gameId' => 1,
            'text' => str_repeat('a', 1001),
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createTakeService,
            $this->takeMapper,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('text', $data['errors']);
    }

    public function testCreateReturnsValidationErrorForHtmlInText(): void
    {
        $this->createTakeService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'gameId' => 1,
            'text' => 'Nice <script>alert(1)</script>',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createTakeService,
            $this->takeMapper,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('text', $data['errors']);
    }

    public function testCreateReturnsValidationErrorWhenGameNotFound(): void
    {
        $this->createTakeService->method('create')
            ->willThrowException(new GameNotFoundException('Игра не найдена.'));

        $request = new Request(content: json_encode(['gameId' => 999, 'text' => 'Great game.'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createTakeService,
            $this->takeMapper,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('gameId', $data['errors']);
    }

    public function testCreateCommentReturnsCreatedCommentOnValidRequest(): void
    {
        $this->takeRepository->method('find')->willReturn($this->take);
        $this->createTakeCommentService->expects($this->once())
            ->method('create')
            ->willReturn(new TakeComment($this->take, $this->user, 'Totally agree!'));

        $request = new Request(content: json_encode(['text' => 'Totally agree!'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->createComment(
            1,
            $request,
            $this->serializer,
            $this->validator(),
            $this->createTakeCommentService,
            $this->takeRepository,
            $this->takeMapper,
            $this->user,
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testCreateCommentThrowsNotFoundExceptionForUnknownTake(): void
    {
        $this->takeRepository->method('find')->willReturn(null);
        $this->createTakeCommentService->expects($this->never())->method('create');

        $request = new Request(content: json_encode(['text' => 'Totally agree!'], \JSON_THROW_ON_ERROR));

        $this->expectException(NotFoundHttpException::class);

        $this->controller->createComment(
            999,
            $request,
            $this->serializer,
            $this->validator(),
            $this->createTakeCommentService,
            $this->takeRepository,
            $this->takeMapper,
            $this->user,
        );
    }

    public function testSetReactionReturnsUpdatedCounts(): void
    {
        $this->takeRepository->method('find')->willReturn($this->take);
        $this->takeReactionService->expects($this->once())
            ->method('setReaction')
            ->with($this->take, $this->user, TakeReactionType::Like)
            ->willReturn(new TakeReaction($this->take, $this->user, TakeReactionType::Like));
        $this->takeReactionRepository->method('countByTypeForTake')->willReturn(['like' => 1, 'dislike' => 0]);

        $request = new Request(content: json_encode(['type' => 'like'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->setReaction(
            1,
            $request,
            $this->serializer,
            $this->validator(),
            $this->takeReactionService,
            $this->takeReactionRepository,
            $this->takeRepository,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('like', $data['type']);
        self::assertSame(1, $data['likeCount']);
    }

    public function testSetReactionReturnsValidationErrorForInvalidType(): void
    {
        $this->takeRepository->method('find')->willReturn($this->take);
        $this->takeReactionService->expects($this->never())->method('setReaction');

        $request = new Request(content: json_encode(['type' => 'love'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->setReaction(
            1,
            $request,
            $this->serializer,
            $this->validator(),
            $this->takeReactionService,
            $this->takeReactionRepository,
            $this->takeRepository,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testSetReactionThrowsNotFoundExceptionForUnknownTake(): void
    {
        $this->takeRepository->method('find')->willReturn(null);

        $request = new Request(content: json_encode(['type' => 'like'], \JSON_THROW_ON_ERROR));

        $this->expectException(NotFoundHttpException::class);

        $this->controller->setReaction(
            999,
            $request,
            $this->serializer,
            $this->validator(),
            $this->takeReactionService,
            $this->takeReactionRepository,
            $this->takeRepository,
            $this->user,
        );
    }

    public function testRemoveReactionReturnsUpdatedCounts(): void
    {
        $this->takeRepository->method('find')->willReturn($this->take);
        $this->takeReactionService->expects($this->once())->method('removeReaction')->with($this->take, $this->user);
        $this->takeReactionRepository->method('countByTypeForTake')->willReturn(['like' => 0, 'dislike' => 0]);

        $response = $this->controller->removeReaction(
            1,
            $this->takeReactionService,
            $this->takeReactionRepository,
            $this->takeRepository,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertNull($data['type']);
        self::assertSame(0, $data['likeCount']);
    }

    public function testRemoveReactionThrowsNotFoundExceptionForUnknownTake(): void
    {
        $this->takeRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->removeReaction(
            999,
            $this->takeReactionService,
            $this->takeReactionRepository,
            $this->takeRepository,
            $this->user,
        );
    }
}
