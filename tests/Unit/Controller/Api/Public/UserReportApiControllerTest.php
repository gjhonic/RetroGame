<?php

namespace App\Tests\Unit\Controller\Api\Public;

use App\Controller\Api\Public\UserReportApiController;
use App\Entity\Enum\UserReportType;
use App\Entity\UserReport;
use App\Service\UserReport\CreateUserReportService;
use App\Service\UserReport\UserReportMapper;
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
 * Мок CreateUserReportService здесь и как стаб (готовый ответ), и как мок
 * (проверка вызова) — строгая проверка "мок без expects()" отключена, как и
 * в ScoreDieAgainApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class UserReportApiControllerTest extends TestCase
{
    private CreateUserReportService&MockObject $createUserReportService;
    private UserReportMapper $userReportMapper;
    private UserReportApiController $controller;
    private SerializerInterface $serializer;

    protected function setUp(): void
    {
        $this->createUserReportService = $this->createMock(CreateUserReportService::class);
        $this->userReportMapper = new UserReportMapper();

        $this->controller = new UserReportApiController();
        $this->controller->setContainer(new Container());

        $this->serializer = new Serializer([new ObjectNormalizer()], [new JsonEncoder()]);
    }

    private function validator(): ValidatorInterface
    {
        return Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
    }

    public function testCreateReturnsCreatedReportOnValidRequest(): void
    {
        $this->createUserReportService->expects($this->once())
            ->method('create')
            ->willReturn(new UserReport(UserReportType::Site, 'Не открывается страница игры.'));

        $request = new Request(content: json_encode([
            'type' => 1,
            'comment' => 'Не открывается страница игры.',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createUserReportService,
            $this->userReportMapper,
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(201, $response->getStatusCode());
        self::assertSame(1, $data['type']);
        self::assertSame('Сайт', $data['typeLabel']);
    }

    public function testCreateReturnsValidationErrorForBlankComment(): void
    {
        $this->createUserReportService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'type' => 3,
            'comment' => '',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createUserReportService,
            $this->userReportMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('comment', $data['errors']);
    }

    public function testCreateReturnsValidationErrorForInvalidType(): void
    {
        $this->createUserReportService->expects($this->never())->method('create');

        $request = new Request(content: json_encode([
            'type' => 99,
            'comment' => 'Что-то не так.',
        ], \JSON_THROW_ON_ERROR));

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createUserReportService,
            $this->userReportMapper,
        );

        self::assertSame(422, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        self::assertArrayHasKey('type', $data['errors']);
    }

    public function testCreateReturnsBadRequestForInvalidJsonBody(): void
    {
        $this->createUserReportService->expects($this->never())->method('create');

        $request = new Request(content: 'not-json');

        $response = $this->controller->create(
            $request,
            $this->serializer,
            $this->validator(),
            $this->createUserReportService,
            $this->userReportMapper,
        );

        self::assertSame(400, $response->getStatusCode());
    }
}
