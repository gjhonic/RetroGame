<?php

namespace App\Service\UserReport;

use App\Entity\UserReport;

/** Маппинг сущности UserReport в массив для JSON API. */
class UserReportMapper
{
    /** @return array<string, mixed> */
    public function toListItem(UserReport $report): array
    {
        return [
            'id' => $report->getId(),
            'type' => $report->getType()->value,
            'typeLabel' => $report->getType()->label(),
            'comment' => $report->getComment(),
            'createdAt' => $report->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ];
    }
}
