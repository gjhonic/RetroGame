<?php

namespace App\Tests\Unit\EventListener;

use App\Entity\Enum\AuditLogStatus;
use App\EventListener\LoginFailureListener;
use App\Service\AuditLog\AuditLogger;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

/** Мок AuthenticatorInterface здесь используется только как заглушка конструктора события, без expects(). */
#[AllowMockObjectsWithoutExpectations]
class LoginFailureListenerTest extends TestCase
{
    private AuditLogger&MockObject $auditLogger;
    private LoginFailureListener $listener;

    protected function setUp(): void
    {
        $this->auditLogger = $this->createMock(AuditLogger::class);
        $this->listener = new LoginFailureListener($this->auditLogger);
    }

    private function makeEvent(Request $request): LoginFailureEvent
    {
        return new LoginFailureEvent(
            new BadCredentialsException('Invalid credentials.'),
            $this->createMock(AuthenticatorInterface::class),
            $request,
            null,
            'main',
        );
    }

    public function testLogsFailureWithEmailFromFormRequest(): void
    {
        $request = new Request(request: ['email' => 'player@retrogame.local']);

        $this->auditLogger->expects($this->once())
            ->method('log')
            ->with(null, 'user.login', AuditLogStatus::Failure, [
                'email' => 'player@retrogame.local',
                'reason' => 'Invalid credentials.',
            ]);

        $this->listener->__invoke($this->makeEvent($request));
    }

    public function testLogsFailureWithEmailFromJsonRequest(): void
    {
        $request = new Request(
            content: json_encode(['email' => 'mobile@retrogame.local', 'password' => 'x'], \JSON_THROW_ON_ERROR),
        );

        $this->auditLogger->expects($this->once())
            ->method('log')
            ->with(null, 'user.login', AuditLogStatus::Failure, [
                'email' => 'mobile@retrogame.local',
                'reason' => 'Invalid credentials.',
            ]);

        $this->listener->__invoke($this->makeEvent($request));
    }

    public function testLogsNullEmailWhenItCannotBeExtracted(): void
    {
        $request = new Request(content: 'not-json');

        $this->auditLogger->expects($this->once())
            ->method('log')
            ->with(null, 'user.login', AuditLogStatus::Failure, [
                'email' => null,
                'reason' => 'Invalid credentials.',
            ]);

        $this->listener->__invoke($this->makeEvent($request));
    }
}
