<?php

namespace App\Controller\Cabinet;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/** Профиль пользователя — тонкая Twig-обёртка, без данных из БД. */
class ProfileController extends AbstractController
{
    /**
     * Профиль пользователя по нику — доступен без авторизации, но виден только
     * если владелец открыл его в настройках (см. Api\Public\ProfileApiController)
     * или это его собственный профиль (виден себе всегда, даже закрытый), иначе
     * Vue-компонент покажет "профиль не найден" (страница всё равно отвечает 200,
     * как и другие публичные Vue-страницы — см. .claude/rules/frontend.md).
     */
    #[Route('/profile/{nickname}', name: 'public_profile_show', methods: ['GET'])]
    public function show(string $nickname): Response
    {
        return $this->render('cabinet/profile/show.html.twig', [
            'nickname' => $nickname,
        ]);
    }
}
