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

    public function testToAdminListItemMapsFieldsWithoutPassword(): void
    {
        $user = (new User('moderator@retrogame.local', 'hashed-password', UserRole::Moderator))
            ->setNickname('Mod')
            ->touchLastLogin();

        $data = (new UserMapper())->toAdminListItem($user);

        self::assertSame('moderator@retrogame.local', $data['email']);
        self::assertSame('Mod', $data['nickname']);
        self::assertSame('ROLE_MODERATOR', $data['role']);
        self::assertNotNull($data['lastLoginAt']);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testToDetailExtendsAdminListItemWithAvatarAndUpdatedAt(): void
    {
        $user = (new User('player@retrogame.local', 'hashed-password'))
            ->setAvatarUrl('https://example.test/avatar.png');

        $data = (new UserMapper())->toDetail($user);

        self::assertSame('https://example.test/avatar.png', $data['avatarUrl']);
        self::assertSame($user->getUpdatedAt()->format(\DateTimeInterface::ATOM), $data['updatedAt']);
        self::assertArrayNotHasKey('password', $data);
    }
}
