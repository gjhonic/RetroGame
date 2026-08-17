<?php

namespace App\Tests\Unit\Controller\Api\Admin;

use App\Controller\Api\Admin\AuditLogApiController;
use App\Entity\AuditLog;
use App\Entity\Enum\AuditLogStatus;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use App\Service\AuditLog\AuditLogMapper;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Мок AuditLogRepository здесь и как стаб (готовые ответы), и как мок
 * (проверка аргументов) — строгая проверка "мок без expects()" отключена,
 * как и в TakeApiControllerTest.
 */
#[AllowMockObjectsWithoutExpectations]
class AuditLogApiControllerTest extends TestCase
{
    private AuditLogRepository&MockObject $auditLogRepository;
    private AuditLogApiController $controller;

    protected function setUp(): void
    {
        $this->auditLogRepository = $this->createMock(AuditLogRepository::class);

        $this->controller = new AuditLogApiController();
        $this->controller->setContainer(new Container());
    }

    private function makeLog(): AuditLog
    {
        $user = new User('admin@retrogame.local', 'hash');

        return new AuditLog($user, 'user.login', AuditLogStatus::Success, ['ip' => '127.0.0.1']);
    }

    public function testListReturnsItemsWithDefaultPaginationAndSort(): void
    {
        $log = $this->makeLog();

        $this->auditLogRepository->method('countForAdminList')->willReturn(1);
        $this->auditLogRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], null, null, 'createdAt', 'DESC', 25, 0)
            ->willReturn([$log]);

        $response = $this->controller->list(new Request(), $this->auditLogRepository, new AuditLogMapper());
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['total']);
        self::assertSame('user.login', $data['items'][0]['action']);
        self::assertSame('success', $data['items'][0]['status']);
        self::assertArrayNotHasKey('details', $data['items'][0]);
    }

    public function testListPassesFiltersAndDateRangeToRepository(): void
    {
        $this->auditLogRepository->method('countForAdminList')->willReturn(0);
        $this->auditLogRepository->expects($this->once())
            ->method('findForAdminList')
            ->with(
                ['action' => 'user.login', 'status' => 'failure', 'user' => '5'],
                self::isInstanceOf(\DateTimeImmutable::class),
                self::isInstanceOf(\DateTimeImmutable::class),
                'action',
                'ASC',
                25,
                0,
            )
            ->willReturn([]);

        $request = new Request([
            'filters' => ['action' => 'user.login', 'status' => 'failure', 'user' => '5'],
            'dateFrom' => '2026-08-01T00:00:00+00:00',
            'dateTo' => '2026-08-13T00:00:00+00:00',
            'sortBy' => 'action',
            'sortDir' => 'asc',
        ]);
        $this->controller->list($request, $this->auditLogRepository, new AuditLogMapper());
    }

    public function testListClampsRequestedPageToTotalPages(): void
    {
        $this->auditLogRepository->method('countForAdminList')->willReturn(1);
        $this->auditLogRepository->expects($this->once())
            ->method('findForAdminList')
            ->with([], null, null, 'createdAt', 'DESC', 25, 0)
            ->willReturn([]);

        $response = $this->controller->list(
            new Request(['page' => '99']),
            $this->auditLogRepository,
            new AuditLogMapper(),
        );
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(1, $data['page']);
    }

    public function testActionsReturnsDistinctActions(): void
    {
        $this->auditLogRepository->method('findDistinctActions')->willReturn(['user.login', 'user.register']);

        $response = $this->controller->actions($this->auditLogRepository);
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(['user.login', 'user.register'], $data['actions']);
    }

    public function testShowReturnsDetailWithJsonDetails(): void
    {
        $log = $this->makeLog();
        $this->auditLogRepository->method('find')->willReturn($log);

        $response = $this->controller->show(1, $this->auditLogRepository, new AuditLogMapper());
        $data = json_decode((string) $response->getContent(), true);

        self::assertSame(['ip' => '127.0.0.1'], $data['details']);
    }

    public function testShowThrowsNotFoundExceptionForUnknownId(): void
    {
        $this->auditLogRepository->method('find')->willReturn(null);

        $this->expectException(NotFoundHttpException::class);

        $this->controller->show(999, $this->auditLogRepository, new AuditLogMapper());
    }
}
