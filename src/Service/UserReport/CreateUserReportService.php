<?php

namespace App\Service\UserReport;

use App\Dto\UserReport\CreateUserReportRequest;
use App\Entity\Enum\UserReportType;
use App\Entity\UserReport;
use Doctrine\ORM\EntityManagerInterface;

/** Сохраняет отчёт пользователя о проблеме на сайте, в приложении или в игре DIE//AGAIN. */
class CreateUserReportService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function create(CreateUserReportRequest $request): UserReport
    {
        $report = new UserReport(UserReportType::from((int) $request->type), trim($request->comment));
        $this->entityManager->persist($report);
        $this->entityManager->flush();

        return $report;
    }
}
