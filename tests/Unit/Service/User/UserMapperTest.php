<?php

namespace App\Tests\Unit\Service\User;

use App\Entity\Enum\UserRole;
use App\Entity\User;
use App\Service\User\UserMapper;
use PHPUnit\Framework\TestCase;

class UserMapperTest extends TestCase
{
    public function testToPublicMapsFieldsWithoutPassword(): void
    {
        $user = (new User('player@retrogame.local', 'hashed-password', UserRole::User))
            ->setNickname('Player One')
            ->setAvatarUrl('https://example.test/avatar.png');

        $data = (new UserMapper())->toPublic($user);

        self::assertSame('player@retrogame.local', $data['email']);
        self::assertSame('Player One', $data['nickname']);
        self::assertSame('https://example.test/avatar.png', $data['avatarUrl']);
        self::assertSame('ROLE_USER', $data['role']);
        self::assertArrayNotHasKey('password', $data);
    }
}
