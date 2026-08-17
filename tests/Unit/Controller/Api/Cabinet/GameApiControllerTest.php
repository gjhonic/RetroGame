<?php

namespace App\Tests\Unit\Controller\Api\Cabinet;

use App\Controller\Api\Cabinet\GameApiController;
use App\Entity\Enum\GamePlaythroughStatus;
use App\Entity\Enum\GameReactionType;
use App\Entity\Game;
use App\Entity\GameReaction;
use App\Entity\GameStatus;
use App\Entity\User;
use App\Repository\GameReactionRepository;
use App\Repository\GameRepository;
use App\Service\Game\GameFavoriteService;
use App\Service\Game\GameReactionService;
use App\Service\Game\GameStatusService;
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
 * Мок сервисов/репозиториев здесь и как стаб (готовые ответы), и как мок
 * (проверка вызовов) — строгая проверка "мок без expects()" отключена, как
 * и в TakeApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class GameApiControllerTest extends TestCase
{
    private GameReactionService&MockObject $gameReactionService;
    private GameFavoriteService&MockObject $gameFavoriteService;
    private GameStatusService&MockObject $gameStatusService;
    private GameReactionRepository&MockObject $gameReactionRepository;
    private GameRepository&MockObject $gameRepository;
    private GameApiController $controller;
    private SerializerInterface $serializer;
    private User $user;
    private Game $game;

    protected function setUp(): void
    {
        $this->gameReactionService = $this->createMock(GameReactionService::class);
        $this->gameFavoriteService = $this->createMock(GameFavoriteService::class);
        $this->gameStatusService = $this->createMock(GameStatusService::class);
        $this->gameReactionRepository = $this->createMock(GameReactionRepository::class);
        $this->gameRepository = $this->createMock(GameRepository::class);

        $this->controller = new GameApiController();
        $this->controller->setContainer(new Container());

        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
        $this->user = new User('player@retrogame.local', 'hash');
        $this->game = new Game('Half-Life', 'half-life');
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testSetReactionReturnsUpdatedCounts(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn($this->game);
        $this->gameReactionService->expects($this->once())
            ->method('setReaction')
            ->with($this->game, $this->user, GameReactionType::Like)
            ->willReturn(new GameReaction($this->game, $this->user, GameReactionType::Like));
        $this->gameReactionRepository->method('countByTypeForGame')->willReturn(['like' => 1, 'dislike' => 0]);

        $request = new Request(content: json_encode(['type' => 'like'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->setReaction(
            'half-life',
            $request,
            $this->serializer,
            $this->validator(),
            $this->gameReactionService,
            $this->gameReactionRepository,
            $this->gameRepository,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('like', $data['type']);
        self::assertSame(1, $data['likeCount']);
    }

    public function testSetReactionReturnsValidationErrorForInvalidType(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn($this->game);
        $this->gameReactionService->expects($this->never())->method('setReaction');

        $request = new Request(content: json_encode(['type' => 'love'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->setReaction(
            'half-life',
            $request,
            $this->serializer,
            $this->validator(),
            $this->gameReactionService,
            $this->gameReactionRepository,
            $this->gameRepository,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testSetReactionThrowsNotFoundExceptionForUnknownSlug(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $request = new Request(content: json_encode(['type' => 'like'], \JSON_THROW_ON_ERROR));

        $this->expectException(NotFoundHttpException::class);

        $this->controller->setReaction(
            'unknown-slug',
            $request,
            $this->serializer,
            $this->validator(),
            $this->gameReactionService,
            $this->gameReactionRepository,
            $this->gameRepository,
            $this->user,
        );
    }

    public function testRemoveReactionReturnsUpdatedCounts(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn($this->game);
        $this->gameReactionService->expects($this->once())
            ->method('removeReaction')
            ->with($this->game, $this->user);
        $this->gameReactionRepository->method('countByTypeForGame')->willReturn(['like' => 0, 'dislike' => 0]);

        $response = $this->controller->removeReaction(
            'half-life',
            $this->gameReactionService,
            $this->gameReactionRepository,
            $this->gameRepository,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertNull($data['type']);
        self::assertSame(0, $data['likeCount']);
    }

    public function testRemoveReactionThrowsNotFoundExceptionForUnknownSlug(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->removeReaction(
            'unknown-slug',
            $this->gameReactionService,
            $this->gameReactionRepository,
            $this->gameRepository,
            $this->user,
        );
    }

    public function testAddFavoriteReturnsTrue(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn($this->game);
        $this->gameFavoriteService->expects($this->once())->method('addFavorite')->with($this->game, $this->user);

        $response = $this->controller->addFavorite(
            'half-life',
            $this->gameFavoriteService,
            $this->gameRepository,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertTrue($data['favorite']);
    }

    public function testAddFavoriteThrowsNotFoundExceptionForUnknownSlug(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->addFavorite('unknown-slug', $this->gameFavoriteService, $this->gameRepository, $this->user);
    }

    public function testRemoveFavoriteReturnsFalse(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn($this->game);
        $this->gameFavoriteService->expects($this->once())->method('removeFavorite')->with($this->game, $this->user);

        $response = $this->controller->removeFavorite(
            'half-life',
            $this->gameFavoriteService,
            $this->gameRepository,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertFalse($data['favorite']);
    }

    public function testRemoveFavoriteThrowsNotFoundExceptionForUnknownSlug(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->removeFavorite(
            'unknown-slug',
            $this->gameFavoriteService,
            $this->gameRepository,
            $this->user,
        );
    }

    public function testSetStatusReturnsSavedStatus(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn($this->game);
        $this->gameStatusService->expects($this->once())
            ->method('setStatus')
            ->with($this->game, $this->user, GamePlaythroughStatus::Completed)
            ->willReturn(new GameStatus($this->game, $this->user, GamePlaythroughStatus::Completed));

        $request = new Request(content: json_encode(['status' => 'completed'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->setStatus(
            'half-life',
            $request,
            $this->serializer,
            $this->validator(),
            $this->gameStatusService,
            $this->gameRepository,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame('completed', $data['status']);
    }

    public function testSetStatusReturnsValidationErrorForInvalidStatus(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn($this->game);
        $this->gameStatusService->expects($this->never())->method('setStatus');

        $request = new Request(content: json_encode(['status' => 'unknown'], \JSON_THROW_ON_ERROR));

        $response = $this->controller->setStatus(
            'half-life',
            $request,
            $this->serializer,
            $this->validator(),
            $this->gameStatusService,
            $this->gameRepository,
            $this->user,
        );

        self::assertSame(422, $response->getStatusCode());
    }

    public function testSetStatusThrowsNotFoundExceptionForUnknownSlug(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $request = new Request(content: json_encode(['status' => 'completed'], \JSON_THROW_ON_ERROR));

        $this->expectException(NotFoundHttpException::class);

        $this->controller->setStatus(
            'unknown-slug',
            $request,
            $this->serializer,
            $this->validator(),
            $this->gameStatusService,
            $this->gameRepository,
            $this->user,
        );
    }

    public function testRemoveStatusReturnsNull(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn($this->game);
        $this->gameStatusService->expects($this->once())->method('removeStatus')->with($this->game, $this->user);

        $response = $this->controller->removeStatus(
            'half-life',
            $this->gameStatusService,
            $this->gameRepository,
            $this->user,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertNull($data['status']);
    }

    public function testRemoveStatusThrowsNotFoundExceptionForUnknownSlug(): void
    {
        $this->gameRepository->method('findOneBy')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->removeStatus('unknown-slug', $this->gameStatusService, $this->gameRepository, $this->user);
    }
}
