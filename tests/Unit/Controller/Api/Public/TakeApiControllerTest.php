<?php

namespace App\Tests\Unit\Controller\Api\Public;

use App\Controller\Api\Public\TakeApiController;
use App\Entity\Game;
use App\Entity\Take;
use App\Entity\TakeComment;
use App\Entity\User;
use App\Repository\TakeCommentRepository;
use App\Repository\TakeReactionRepository;
use App\Repository\TakeRepository;
use App\Service\Take\TakeMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок TakeRepository здесь и как стаб (готовые ответы findForPublicList/find/countForPublicList),
 * и как мок (проверка аргументов фильтров/пагинации) — строгая проверка "мок без
 * expects()" отключена, как и в GameApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class TakeApiControllerTest extends TestCase
{
    private TakeRepository&MockObject $takeRepository;
    private TakeReactionRepository&MockObject $takeReactionRepository;
    private TakeCommentRepository&MockObject $takeCommentRepository;
    private TakeMapper $takeMapper;
    private TakeApiController $controller;

    protected function setUp(): void
    {
        $this->takeRepository = $this->createMock(TakeRepository::class);
        $this->takeReactionRepository = $this->createMock(TakeReactionRepository::class);
        $this->takeCommentRepository = $this->createMock(TakeCommentRepository::class);
        $this->takeMapper = new TakeMapper();

        $this->controller = new TakeApiController();
        $this->controller->setContainer(new Container());
    }

    private function makeTake(): Take
    {
        return new Take(new User('author@retrogame.local', 'hash'), new Game('Half-Life', 'half-life'), 'Take text');
    }

    public function testListReturnsItemsWithReactionAndCommentCounts(): void
    {
        $take = $this->makeTake();

        $this->takeRepository->method('countForPublicList')->willReturn(1);
        $this->takeRepository->expects($this->once())
            ->method('findForPublicList')
            ->with([], 'createdAt', 'DESC', 20, 0)
            ->willReturn([$take]);
        $this->takeReactionRepository->method('countByTypeForTakes')->willReturn([0 => ['like' => 3, 'dislike' => 1]]);
        $this->takeCommentRepository->method('countForTake')->willReturn(2);

        $response = $this->controller->list(
            new Request(),
            $this->takeRepository,
            $this->takeReactionRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(3, $data['items'][0]['likeCount']);
        self::assertSame(1, $data['items'][0]['dislikeCount']);
        self::assertSame(2, $data['items'][0]['commentCount']);
    }

    public function testListPassesGameFilterToRepository(): void
    {
        $this->takeRepository->method('countForPublicList')->willReturn(0);
        $this->takeRepository->expects($this->once())
            ->method('findForPublicList')
            ->with(['game' => '42'], 'createdAt', 'DESC', 20, 0)
            ->willReturn([]);
        $this->takeReactionRepository->method('countByTypeForTakes')->willReturn([]);

        $request = new Request(['filters' => ['game' => ' 42 ']]);
        $this->controller->list(
            $request,
            $this->takeRepository,
            $this->takeReactionRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
        );
    }

    public function testShowReturnsDetailWithComments(): void
    {
        $take = $this->makeTake();
        $comment = new TakeComment($take, new User('commenter@retrogame.local', 'hash'), 'Totally agree!');

        $this->takeRepository->method('find')->willReturn($take);
        $this->takeReactionRepository->method('countByTypeForTake')->willReturn(['like' => 5, 'dislike' => 0]);
        $this->takeCommentRepository->method('findForTake')->willReturn([$comment]);

        $response = $this->controller->show(
            1,
            $this->takeRepository,
            $this->takeReactionRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(5, $data['likeCount']);
        self::assertCount(1, $data['comments']);
        self::assertSame('Totally agree!', $data['comments'][0]['text']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->takeRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(
            999,
            $this->takeRepository,
            $this->takeReactionRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
        );
    }

    public function testCommentsReturnsPaginatedList(): void
    {
        $take = $this->makeTake();
        $comment = new TakeComment($take, new User('commenter@retrogame.local', 'hash'), 'Totally agree!');

        $this->takeRepository->method('find')->willReturn($take);
        $this->takeCommentRepository->method('countForTake')->willReturn(1);
        $this->takeCommentRepository->expects($this->once())
            ->method('findForTake')
            ->with($take, 20, 0)
            ->willReturn([$comment]);

        $response = $this->controller->comments(
            1,
            new Request(),
            $this->takeRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame('Totally agree!', $data['items'][0]['text']);
    }

    public function testCommentsThrowsNotFoundExceptionForUnknownTake(): void
    {
        $this->takeRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->comments(
            999,
            new Request(),
            $this->takeRepository,
            $this->takeCommentRepository,
            $this->takeMapper,
        );
    }
}
