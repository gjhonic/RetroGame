<?php

namespace App\Entity\Enum;

enum DownloadPlatform: string
{
    case Windows = 'windows';
    case MacOS = 'macos';
    case Linux = 'linux';
    case Android = 'android';
    case Web = 'web';
}
