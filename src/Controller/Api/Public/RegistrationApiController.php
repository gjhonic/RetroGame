<?php

namespace App\Controller\Api\Public;

use App\Dto\User\RegisterUserRequest;
use App\Entity\Enum\AuditLogStatus;
use App\Service\AuditLog\AuditLogger;
use App\Service\User\Exceptions\EmailAlreadyRegisteredException;
use App\Service\User\Exceptions\NicknameAlreadyTakenException;
use App\Service\User\UserMapper;
use App\Service\User\UserRegistrationService;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** JSON API регистрации — используется мобильным приложением. */
#[Route('/api/register')]
#[OA\Tag(name: 'Registration')]
class RegistrationApiController extends AbstractController
{
    /** Регистрирует нового пользователя. */
    #[Route('', name: 'app_api_register', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'email', type: 'string'),
            new OA\Property(property: 'password', type: 'string'),
            new OA\Property(property: 'nickname', type: 'string'),
        ],
        type: 'object',
    ))]
    #[OA\Response(response: 201, description: 'Пользователь зарегистрирован')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    #[OA\Response(response: 409, description: 'Email уже занят')]
    public function register(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserRegistrationService $userRegistrationService,
        UserMapper $userMapper,
        AuditLogger $auditLogger,
    ): JsonResponse {
        try {
            $dto = $serializer->deserialize($request->getContent(), RegisterUserRequest::class, 'json');
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
            $user = $userRegistrationService->register($dto);
        } catch (EmailAlreadyRegisteredException | NicknameAlreadyTakenException $exception) {
            $auditLogger->log(null, 'user.register', AuditLogStatus::Failure, ['email' => $dto->email]);

            throw new ConflictHttpException($exception->getMessage());
        }

        $auditLogger->log($user, 'user.register', AuditLogStatus::Success, ['email' => $dto->email]);

        return $this->json($userMapper->toPublic($user), 201);
    }
}
