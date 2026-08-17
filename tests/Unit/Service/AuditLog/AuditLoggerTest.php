<?php

namespace App\Tests\Unit\Service\AuditLog;

use App\Entity\AuditLog;
use App\Entity\Enum\AuditLogStatus;
use App\Entity\User;
use App\Service\AuditLog\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/** Мок EntityManagerInterface здесь и как стаб (перехват persist), и как мок (once/never) — expects() отключён для стаб-вызовов. */
#[AllowMockObjectsWithoutExpectations]
class AuditLoggerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private AuditLogger $auditLogger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->auditLogger = new AuditLogger($this->entityManager);
    }

    public function testLogPersistsAndFlushesAuditLogWithUserAndDetails(): void
    {
        $user = new User('player@retrogame.local', 'hash');
        $persisted = null;
        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with(self::isInstanceOf(AuditLog::class))
            ->willReturnCallback(function (AuditLog $auditLog) use (&$persisted): void {
                $persisted = $auditLog;
            });
        $this->entityManager->expects($this->once())->method('flush');

        $this->auditLogger->log($user, 'user.login', AuditLogStatus::Success, ['ip' => '127.0.0.1']);

        self::assertInstanceOf(AuditLog::class, $persisted);
        self::assertSame($user, $persisted->getUser());
        self::assertSame('user.login', $persisted->getAction());
        self::assertSame(AuditLogStatus::Success, $persisted->getStatus());
        self::assertSame(['ip' => '127.0.0.1'], $persisted->getDetails());
    }

    public function testLogAllowsNullUserAndNullDetails(): void
    {
        $persisted = null;
        $this->entityManager->method('persist')
            ->willReturnCallback(function (AuditLog $auditLog) use (&$persisted): void {
                $persisted = $auditLog;
            });

        $this->auditLogger->log(null, 'user.login', AuditLogStatus::Failure);

        self::assertInstanceOf(AuditLog::class, $persisted);
        self::assertNull($persisted->getUser());
        self::assertNull($persisted->getDetails());
    }
}
