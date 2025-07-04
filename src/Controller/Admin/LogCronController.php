<?php

namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/log-cron')]
class LogCronController extends AbstractController
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    #[Route('/', name: 'admin_log_cron_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $cronName = $request->query->get('cronName');
        $dateStart = $request->query->get('dateStart');
        $page = max(1, (int)$request->query->get('page', '1'));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(\App\Entity\LogCron::class, 'l');

        if ($cronName) {
            $qb->andWhere('l.cronName LIKE :cronName')
                ->setParameter('cronName', '%' . $cronName . '%');
        }
        if ($dateStart) {
            $qb->andWhere('l.datetimeStart >= :dateStart')
                ->setParameter('dateStart', new \DateTime($dateStart));
        }

        $qb->orderBy('l.datetimeStart', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        $logs = $qb->getQuery()->getResult();

        // Для пагинации считаем всего записей
        $countQb = $this->em->createQueryBuilder()
            ->select('COUNT(l.id)')
            ->from(\App\Entity\LogCron::class, 'l');
        if ($cronName) {
            $countQb->andWhere('l.cronName LIKE :cronName')
                ->setParameter('cronName', '%' . $cronName . '%');
        }
        if ($dateStart) {
            $countQb->andWhere('l.datetimeStart >= :dateStart')
                ->setParameter('dateStart', new \DateTime($dateStart));
        }
        $total = (int)$countQb->getQuery()->getSingleScalarResult();
        $pages = (int)ceil($total / $limit);

        $cronNames = [
            'steam-get-games' => 'Steam: Импорт игр',
            'steam-update-prices' => 'Steam: Обновление цен',
            'steampay-get-games' => 'Steampay: Импорт игр',
            'steampay-update-prices' => 'Steampay: Обновление цен',
            'steambuy-get-games' => 'Steambuy: Импорт игр',
            'steambuy-update-prices' => 'Steambuy: Обновление цен',
            'steamkey-get-games' => 'Steamkey: Импорт игр',
            'steamkey-update-prices' => 'Steamkey: Обновление цен',
        ];

        return $this->render('admin/log_cron/index.html.twig', [
            'logs' => $logs,
            'page' => $page,
            'pages' => $pages,
            'cronName' => $cronName,
            'dateStart' => $dateStart,
            'total' => $total,
            'cronNamesList' => $cronNames,
        ]);
    }

    #[Route('/report', name: 'admin_log_cron_report', methods: ['GET'])]
    public function report(Request $request): Response
    {
        $dateFrom = $request->query->get('dateFrom');
        $dateTo = $request->query->get('dateTo');

        $qb = $this->em->createQueryBuilder()
            ->select('l')
            ->from(\App\Entity\LogCron::class, 'l');

        if ($dateFrom) {
            $qb->andWhere('l.datetimeStart >= :dateFrom')
                ->setParameter('dateFrom', new \DateTime($dateFrom));
        }
        if ($dateTo) {
            $qb->andWhere('l.datetimeStart <= :dateTo')
                ->setParameter('dateTo', (new \DateTime($dateTo))->setTime(23, 59, 59));
        }
        $qb->orderBy('l.datetimeStart', 'ASC');
        $logs = $qb->getQuery()->getResult();

        // Группируем по имени крона для графика
        $chartData = [];
        foreach ($logs as $log) {
            $chartData[$log->getCronName()][] = [
                'id' => $log->getId(),
                'start' => $log->getDatetimeStart() ? $log->getDatetimeStart()->format('c') : null,
                'end' => $log->getDatetimeEnd() ? $log->getDatetimeEnd()->format('c') : null,
            ];
        }

        return $this->render('admin/log_cron/report.html.twig', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'chartData' => $chartData,
        ]);
    }

    #[Route('/{id}', name: 'admin_log_cron_show', methods: ['GET'])]
    public function show(\App\Entity\LogCron $logCron): Response
    {
        return $this->render('admin/log_cron/show.html.twig', [
            'log' => $logCron,
        ]);
    }

    #[Route('/{id}/download-log', name: 'admin_log_cron_download_log', methods: ['GET'])]
    public function downloadLog(\App\Entity\LogCron $logCron): Response
    {
        if (!$logCron->getDatetimeStart()) {
            throw $this->createNotFoundException('Лог не содержит дату старта');
        }

        $date = $logCron->getDatetimeStart()->format('Y-m-d');
        $time = $logCron->getDatetimeStart()->format('H-i-s');
        $cronName = $logCron->getCronName();

        // Формируем базовое имя файла
        $logDir = __DIR__ . '/../../../var/log/' . $date;

        // Ищем файл с возможным смещением в несколько секунд
        $foundFile = null;
        $timeObj = $logCron->getDatetimeStart();

        // Проверяем файлы в диапазоне ±20 секунд
        for ($offset = -20; $offset <= 20; $offset++) {
            $checkTime = (new \DateTime())->setTimestamp($timeObj->getTimestamp() + $offset);
            $checkFileName = "{$cronName}-{$checkTime->format('H-i-s')}.log";

            $checkPath = "{$logDir}/{$checkFileName}";

            if (file_exists($checkPath)) {
                $foundFile = $checkPath;
                break;
            }
        }

        if (!$foundFile) {
            throw $this->createNotFoundException('Лог-файл не найден');
        }

        // Читаем содержимое файла
        $content = file_get_contents($foundFile);
        if ($content === false) {
            throw $this->createNotFoundException('Не удалось прочитать лог-файл');
        }

        // Возвращаем файл для скачивания
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain');
        $response->headers->set('Content-Disposition', 'attachment; filename="' . basename($foundFile) . '"');

        return $response;
    }
}
