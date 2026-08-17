<?php

namespace App\Controller\Api\Public;

use App\Dto\UserReport\CreateUserReportRequest;
use App\Service\UserReport\CreateUserReportService;
use App\Service\UserReport\UserReportMapper;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/** JSON API отчётов пользователей о проблемах — доступен всем без авторизации. */
#[Route('/api/user-reports')]
#[OA\Tag(name: 'UserReports')]
class UserReportApiController extends AbstractController
{
    /** Отправить отчёт о проблеме на сайте, в мобильном приложении или в игре DIE//AGAIN. */
    #[Route('', name: 'app_api_user_report_create', methods: ['POST'])]
    #[OA\RequestBody(content: new OA\JsonContent(
        properties: [
            new OA\Property(
                property: 'type',
                type: 'integer',
                description: '1 — сайт, 2 — мобильное приложение, 3 — игра DIE//AGAIN',
            ),
            new OA\Property(property: 'comment', type: 'string'),
        ],
        type: 'object',
    ))]
    #[OA\Response(response: 201, description: 'Отчёт сохранён')]
    #[OA\Response(response: 400, description: 'Некорректное тело запроса')]
    #[OA\Response(response: 422, description: 'Ошибки валидации')]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        CreateUserReportService $createUserReportService,
        UserReportMapper $userReportMapper,
    ): JsonResponse {
        try {
            $dto = $serializer->deserialize($request->getContent(), CreateUserReportRequest::class, 'json');
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

        $report = $createUserReportService->create($dto);

        return $this->json($userReportMapper->toListItem($report), 201);
    }
}
