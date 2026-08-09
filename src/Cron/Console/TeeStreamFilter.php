<?php

namespace App\Cron\Console;

/**
 * PHP stream-фильтр: пропускает поток без изменений, но параллельно
 * дублирует каждый кусок данных в файловый хендл из параметров фильтра.
 * Навешивается на поток консольного вывода (StreamOutput::getStream()),
 * чтобы записать в лог-файл ровно то, что видел пользователь в консоли —
 * без переписывания команд под кастомный OutputInterface.
 */
final class TeeStreamFilter extends \php_user_filter
{
    public const string NAME = 'app.cron_tee';

    /** @var resource */
    private $logHandle;

    public function onCreate(): bool
    {
        $this->logHandle = $this->params['handle'];

        return true;
    }

    /**
     * @param resource $in
     * @param resource $out
     */
    public function filter($in, $out, &$consumed, bool $closing): int
    {
        while (($bucket = stream_bucket_make_writeable($in)) !== null) {
            fwrite($this->logHandle, $bucket->data);
            $consumed += $bucket->datalen;
            stream_bucket_append($out, $bucket);
        }

        return PSFS_PASS_ON;
    }

    public static function register(): void
    {
        if (!\in_array(self::NAME, stream_get_filters(), true)) {
            stream_filter_register(self::NAME, self::class);
        }
    }
}
