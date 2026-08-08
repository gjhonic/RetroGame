<?php

namespace App\Command;

use App\Service\User\CreateAdminUserService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Заводит/обновляет администратора по умолчанию. Вся логика — в
 * CreateAdminUserService, команда только читает опции/env и печатает результат.
 * Email/пароль — из ADMIN_EMAIL/ADMIN_PASSWORD (пароль задаётся в .env.local,
 * не коммитится) либо явно через опции.
 */
#[AsCommand(
    name: 'app:user:create-admin',
    description: 'Создаёт или обновляет пользователя-администратора по умолчанию',
)]
class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly CreateAdminUserService $createAdminUserService,
        #[Autowire(env: 'ADMIN_EMAIL')]
        private readonly string $defaultEmail = '',
        #[Autowire(env: 'ADMIN_PASSWORD')]
        private readonly string $defaultPassword = '',
    ) {
        parent::__construct();
    }

    /** Описывает опции командной строки. */
    protected function configure(): void
    {
        $this
            ->addOption(
                'email',
                null,
                InputOption::VALUE_REQUIRED,
                'Email администратора (по умолчанию — ADMIN_EMAIL из .env)',
            )
            ->addOption(
                'password',
                null,
                InputOption::VALUE_REQUIRED,
                'Пароль администратора (по умолчанию — ADMIN_PASSWORD из .env.local)',
            );
    }

    /** Читает email/пароль, вызывает сервис и печатает итог. */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email') ?: $this->defaultEmail;
        $password = $input->getOption('password') ?: $this->defaultPassword;

        if ($email === '') {
            $io->error('Не задан email: передайте --email или пропишите ADMIN_EMAIL в .env/.env.local.');

            return Command::FAILURE;
        }

        if ($password === '') {
            $io->error('Не задан пароль: передайте --password или пропишите ADMIN_PASSWORD в .env.local.');

            return Command::FAILURE;
        }

        $user = $this->createAdminUserService->createOrUpdate($email, $password);

        $io->success(sprintf('Готово: %s — администратор.', $user->getEmail()));

        return Command::SUCCESS;
    }
}
