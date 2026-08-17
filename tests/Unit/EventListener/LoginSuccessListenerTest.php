<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Enum\AuditLogStatus;
use App\Entity\User;
use App\EventListener\LoginSuccessListener;
use App\Service\AuditLog\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/** Мок Passport здесь используется только как стаб (getUser), без expects(). */
#[AllowMockObjectsWithoutExpectations]
class LoginSuccessListenerTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private AuditLogger&MockObject $auditLogger;
    private LoginSuccessListener $listener;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->listener = new LoginSuccessListener($this->entityManager, $this->auditLogger);
    }

    private function makeEvent(UserInterface $user): LoginSuccessEvent
    {
        $passport = $this->createMock(Passport::class);
        $passport->method('getUser')->willReturn($user);

        return new LoginSuccessEvent(
            $this->createMock(AuthenticatorInterface::class),
            $passport,
            $this->createMock(TokenInterface::class),
            new Request(),
            null,
            'main',
        );
    }

    public function testUpdatesLastLoginAndLogsSuccess(): void
    {
        $user = new User('player@retrogame.local', 'hash');
        $this->entityManager->expects($this->once())->method('flush');
        $this->auditLogger->expects($this->once())
            ->method('log')
            ->with($user, 'user.login', AuditLogStatus::Success);

        $this->listener->__invoke($this->makeEvent($user));

        self::assertNotNull($user->getLastLoginAt());
    }

    public function testDoesNothingForNonAppUser(): void
    {
        $user = $this->createMock(UserInterface::class);
        $this->entityManager->expects($this->never())->method('flush');
        $this->auditLogger->expects($this->never())->method('log');

        $this->listener->__invoke($this->makeEvent($user));
    }
}
