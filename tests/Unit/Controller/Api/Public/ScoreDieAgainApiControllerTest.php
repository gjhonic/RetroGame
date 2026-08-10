<?php

namespace App\Tests\Unit\Controller\Api\Public;

use App\Controller\Api\Public\ScoreDieAgainApiController;
use App\Entity\ScoreDieAgain;
use App\Repository\ScoreDieAgainRepository;
use App\Service\ScoreDieAgain\CreateScoreDieAgainService;
use App\Service\ScoreDieAgain\ScoreDieAgainMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Мок ScoreDieAgainRepository/CreateScoreDieAgainService здесь и как стаб (готовые
 * ответы), и как мок (проверка аргументов) — строгая проверка "мок без expects()"
 * отключена, как и в TakeApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class ScoreDieAgainApiControllerTest extends TestCase
{
    private ScoreDieAgainRepository&MockObject $scoreDieAgainRepository;
    private CreateScoreDieAgainService&MockObject $createScoreDieAgainService;
    private ScoreDieAgainMapper $scoreDieAgainMapper;
    private ScoreDieAgainApiController $controller;
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->scoreDieAgainRepository = $this->createMock(ScoreDieAgainRepository::class);
        $this->createScoreDieAgainService = $this->createMock(CreateScoreDieAgainService::class);
        $this->scoreDieAgainMapper = new ScoreDieAgainMapper();

        $this->controller = new ScoreDieAgainApiController();
        $this->controller->setContainer(new Container());

        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testListReturnsItemsWithPaginationAndDefaultSort(): void
    {
        $score = new ScoreDieAgain('Player1', 3, 120, 5);

        $this->scoreDieAgainRepository->method('countAll')->willReturn(1);
        $this->scoreDieAgainRepository->expects($this->once())
            ->method('findForLeaderboard')
            ->with('kills', 'DESC', 20, 0)
            ->willReturn([$score]);

        $response = $this->controller->list(
            new Request(),
            $this->scoreDieAgainRepository,
            $this->scoreDieAgainMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame(1, $data['page']);
        self::assertSame('Player1', $data['items'][0]['nickname']);
        self::assertSame(5, $data['items'][0]['kills']);
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->scoreDieAgainRepository->method('countAll')->willReturn(1);
        $this->scoreDieAgainRepository->expects($this->once())
            ->method('findForLeaderboard')
            ->with('kills', 'DESC', 20, 0)
            ->willReturn([]);

        $response = $this->controller->list(
            new Request(query: ['page' => '99']),
            $this->scoreDieAgainRepository,
            $this->scoreDieAgainMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
    }

    public function testListPassesCustomSortToRepository(): void
    {
        $this->scoreDieAgainRepository->method('countAll')->willReturn(0);
        $this->scoreDieAgainRepository->expects($this->once())
            ->method('findForLeaderboard')
            ->with('survivedSeconds', 'ASC', 20, 0)
            ->willReturn([]);

        $this->controller->list(
            new Request(query: ['sortBy' => 'survivedSeconds', 'sortDir' => 'asc']),
            $this->scoreDieAgainRepository,
            $this->scoreDieAgainMapper,
        );
    }

    public function testCreateReturnsCreatedScoreOnValidRequest(): void
    {
        $this->createScoreDieAgainService->expects($this->once())
            ->method('create')
            ->willReturn(new ScoreDieAgain('Player1', 3, 120, 5));

        $request = new Request(content: json_encode([
            'nickname' => 'Player1',
            'level' => 3,
            'survivedSeconds' => 120,
            'kills' => 5,
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createScoreDieAgainService,
            $this->scoreDieAgainMapper,
        );

        self::assertSame(201, $response->getStatusCode());
    }

    public function testCreateReturnsValidationErrorForBlankNickname(): void
    {
        $this->createScoreDieAgainService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'nickname' => '',
            'level' => 1,
            'survivedSeconds' => 10,
            'kills' => 0,
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createScoreDieAgainService,
            $this->scoreDieAgainMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('nickname', $data['errors']);
    }

    public function testCreateReturnsValidationErrorForHtmlInNickname(): void
    {
        $this->createScoreDieAgainService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'nickname' => 'Nice <script>alert(1)</script>',
            'level' => 1,
            'survivedSeconds' => 10,
            'kills' => 0,
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createScoreDieAgainService,
            $this->scoreDieAgainMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('nickname', $data['errors']);
    }

    public function testCreateReturnsValidationErrorForNegativeKills(): void
    {
        $this->createScoreDieAgainService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'nickname' => 'Player1',
            'level' => 1,
            'survivedSeconds' => 10,
            'kills' => -1,
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createScoreDieAgainService,
            $this->scoreDieAgainMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('kills', $data['errors']);
    }

    public function testCreateReturnsBadRequestForInvalidJsonBody(): void
    {
        $this->createScoreDieAgainService->expects($this->never())->method('create');

        $request = new Request(content: 'not-json');

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createScoreDieAgainService,
            $this->scoreDieAgainMapper,
        );

        self::assertSame(400, $response->getStatusCode());
    }
}
