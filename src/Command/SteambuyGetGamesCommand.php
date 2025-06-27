<?php

namespace App\Command;

use App\Entity\Game;
use App\Entity\GameShop;
use App\Entity\Shop;
use App\Entity\SteambuyApp;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(
    name: 'app:steambuy-get-games',
    description: 'Links games with SteamBuy if a valid page exists.',
)]
class SteambuyGetGamesCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $games = $this->entityManager->getRepository(Game::class)->findAll();
        $shop = $this->entityManager->getRepository(Shop::class)->findOneBy(['id' => 2]);

        if (!$shop) {
            $output->writeln('<error>Shop with ID 2 not found</error>');
            return Command::FAILURE;
        }

        $output->writeln(sprintf('Starting import for %d games...', count($games)));

        $imported = 0;
        $skippedExistingShop = 0;
        $skippedNotFound = 0;
        $errorsCount = 0;

        foreach ($games as $game) {
            if ($imported >= 100) {
                $output->writeln('Reached limit of 100 imported games. Stopping.');
                break;
            }

            $slug = $this->slugify((string)$game->getName()) . '-russia';
            $url = "https://steambuy.com/steam/{$slug}/";

            $output->writeln("Processing game: '{$game->getName()}', slug: $slug");

            // Проверка существования GameShop
            $existingShop = $this->entityManager->getRepository(GameShop::class)->findOneBy([
                'game' => $game,
                'shop' => $shop,
            ]);

            if ($existingShop) {
                $output->writeln(" - GameShop already exists for this game and shop. Skipping.");
                $skippedExistingShop++;
                continue;
            }

            // Проверка SteambuyApp
            $steambuyApp = $this->entityManager->getRepository(SteambuyApp::class)->findOneBy([
                'slug' => $slug,
            ]);

            if ($steambuyApp && $steambuyApp->isNotFound()) {
                $output->writeln(" - Previously checked as 404 (not found). Skipping.");
                $skippedNotFound++;
                continue;
            }

            try {
                $output->writeln(" - Fetching URL: $url");
                $response = $this->httpClient->request('GET', $url);
                $content = $response->getContent(false);

                if (!$steambuyApp) {
                    $steambuyApp = new SteambuyApp();
                    $steambuyApp->setSlug($slug);
                    $output->writeln(" - Created new SteambuyApp record for slug.");
                } else {
                    $output->writeln(" - Found existing SteambuyApp record, updating.");
                }

                $steambuyApp->setCheckedAt(new \DateTimeImmutable());

                if (str_contains($content, '<div class="review-heaing__title">Ошибка 404</div>')) {
                    $steambuyApp->setNotFound(true);
                    $steambuyApp->setRawHtml(null);
                    $output->writeln(" - Page returned 404. Marking as not found.");
                } else {
                    $steambuyApp->setNotFound(false);
                    $steambuyApp->setRawHtml(null);

                    $gameShop = new GameShop();
                    $gameShop->setGame($game);
                    $gameShop->setShop($shop);
                    $gameShop->setName((string)$game->getName());
                    $gameShop->setLink($url);
                    $gameShop->setShouldImportPrice(true);
                    $gameShop->setExternalKey($slug);
                    $gameShop->setLinkGameId(0);

                    $this->entityManager->persist($gameShop);

                    $output->writeln(" - Linked GameShop created for game '{$game->getName()}'.");
                    $imported++;
                }

                $this->entityManager->persist($steambuyApp);
                $this->entityManager->flush();
            } catch (\Throwable $e) {
                $errorsCount++;
                $output->writeln("<error> - Error fetching $slug: {$e->getMessage()}</error>");
            }

            usleep(1000000); // 1 секунда
        }

        $output->writeln('');
        $output->writeln('Import summary:');
        $output->writeln(" - Total games processed: " . count($games));
        $output->writeln(" - Games linked/imported: $imported");
        $output->writeln(" - Games skipped (already linked): $skippedExistingShop");
        $output->writeln(" - Games skipped (404 previously): $skippedNotFound");
        $output->writeln(" - Errors occurred: $errorsCount");

        return Command::SUCCESS;
    }

    private function slugify(string $text): string
    {
        $text = mb_strtolower($text, 'UTF-8');

        $translit = [
            'а' => 'a','б' => 'b','в' => 'v','г' => 'g','д' => 'd','е' => 'e','ё' => 'e','ж' => 'zh','з' => 'z',
            'и' => 'i','й' => 'y', 'к' => 'k','л' => 'l','м' => 'm','н' => 'n','о' => 'o','п' => 'p','р' => 'r',
            'с' => 's','т' => 't','у' => 'u','ф' => 'f', 'х' => 'kh','ц' => 'ts','ч' => 'ch','ш' => 'sh','щ' => 'shch',
            'ъ' => '','ы' => 'y','ь' => '','э' => 'e','ю' => 'yu','я' => 'ya', ' ' => '-',',' => '',':' => '',
            ';' => '','.' => '','/' => '-','–' => '-', '—' => '-', '\'' => '','"' => '',
        ];

        $text = strtr($text, $translit);
        $text = preg_replace('~[^a-z0-9\-]+~u', '', $text);
        $text = preg_replace('~-+~', '-', (string)$text);
        return trim((string)$text, '-');
    }
}
