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
        self::assertFalse($data['isProfilePublic']);
        self::assertArrayNotHasKey('password', $data);
    }

    public function testToPublicIncludesProfileVisibility(): void
    {
        $user = (new User('player@retrogame.local', 'hashed-password'))->setIsProfilePublic(true);

        $data = (new UserMapper())->toPublic($user);

        self::assertTrue($data['isProfilePublic']);
    }

    public function testToPublicProfileMapsNicknameAvatarAndCreatedAtWithoutEmail(): void
    {
        $user = (new User('player@retrogame.local', 'hashed-password'))
            ->setNickname('Player One')
            ->setAvatarUrl('https://example.test/avatar.png');

        $data = (new UserMapper())->toPublicProfile($user, 3, 7, false, true);

        self::assertSame('Player One', $data['nickname']);
        self::assertSame('https://example.test/avatar.png', $data['avatarUrl']);
        self::assertSame(3, $data['followersCount']);
        self::assertSame(7, $data['followingCount']);
        self::assertFalse($data['isOwnProfile']);
        self::assertTrue($data['isFollowing']);
        self::assertArrayNotHasKey('email', $data);
        self::assertArrayNotHasKey('id', $data);
    }

    public function testToProfileSummaryMapsNicknameAndAvatarOnly(): void
    {
        $user = (new User('follower@retrogame.local', 'hashed-password'))
            ->setNickname('Follower One')
            ->setAvatarUrl('https://example.test/avatar.png');

        $data = (new UserMapper())->toProfileSummary($user);

        self::assertSame([
            'nickname' => 'Follower One',
            'avatarUrl' => 'https://example.test/avatar.png',
        ], $data);
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
