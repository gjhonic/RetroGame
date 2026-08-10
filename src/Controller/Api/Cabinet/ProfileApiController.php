<?php

namespace App\Controller\Api\Cabinet;

use App\Dto\User\ChangePasswordRequest;
use App\Dto\User\UploadAvatarRequest;
use App\Entity\User;
use App\Service\User\AvatarUploadService;
use App\Service\User\ChangePasswordService;
use App\Service\User\Exceptions\InvalidCurrentPasswordException;
use App\Service\User\UserMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** JSON API профиля текущего пользователя — используется Vue-компонентами Cabinet/*. */
#[Route('/api/cabinet/profile')]
#[IsGranted('ROLE_USER')]
#[OA\Tag(name: 'Cabinet/Profile')]
class ProfileApiController extends AbstractController
{
    /** Данные текущего пользователя. */
    #[Route('', name: 'app_api_cabinet_profile_show', methods: ['GET'])]
    #[OA\Response(response: 200, description: 'Текущий пользователь')]
    public function show(#[CurrentUser] User $user, UserMapper $userMapper): JsonResponse
    {
        return $this->json($userMapper->toPublic($user));
    }

    /** Смена пароля: требует текущий пароль. */
    #[Route('/password', name: 'app_api_cabinet_profile_change_password', methods: ['PATCH'])]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'currentPassword', type: 'string'),
            new OA\Property(property: 'newPassword', type: 'string'),
        ],
        type: 'object',
    ))]
    #[OA\Response(response: 200, description: 'Пароль изменён')]
    #[OA\Response(response: 400, description: 'Некорректное тело запроса')]
    #[OA\Response(response: 422, description: 'Ошибки валидации или неверный текущий пароль')]
    public function changePassword(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        ChangePasswordService $changePasswordService,
        #[CurrentUser] User $user,
    ): JsonResponse {
        try {
            $dto = $serializer->deserialize($request->getContent(), ChangePasswordRequest::class, 'json');
        } catch (SerializerExceptionInterface) {
            return $this->json(['message' => 'Некорректное тело запроса.'], 400);
        }

        $violations = $validator->validate($dto);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], 422);
        }

        try {
            $changePasswordService->changePassword($user, $dto);
        } catch (InvalidCurrentPasswordException $exception) {
            return $this->json(['errors' => ['currentPassword' => [$exception->getMessage()]]], 422);
        }

        return $this->json(['message' => 'Пароль изменён.']);
    }

    /** Загрузка аватара: PNG/JPG, не больше 400×400px и 2 МБ. */
    #[Route('/avatar', name: 'app_api_cabinet_profile_upload_avatar', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(properties: [
            new OA\Property(property: 'avatar', type: 'string', format: 'binary'),
        ], type: 'object'),
    ))]
    #[OA\Response(response: 200, description: 'Аватар обновлён')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function uploadAvatar(
        Request $request,
        ValidatorInterface $validator,
        AvatarUploadService $avatarUploadService,
        UserMapper $userMapper,
        #[CurrentUser] User $user,
    ): JsonResponse {
        $dto = new UploadAvatarRequest();
        $dto->file = $request->files->get('avatar');

        $violations = $validator->validate($dto);
        if (\count($violations) > 0) {
            $errors = [];
            foreach ($violations as $violation) {
                $errors[$violation->getPropertyPath()][] = $violation->getMessage();
            }

            return $this->json(['errors' => $errors], 422);
        }

        \assert($dto->file !== null);
        $avatarUploadService->upload($user, $dto->file);

        return $this->json($userMapper->toPublic($user));
    }
}
