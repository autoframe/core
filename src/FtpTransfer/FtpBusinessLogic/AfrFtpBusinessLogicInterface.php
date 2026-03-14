<?php
declare(strict_types=1);

namespace Autoframe\Core\FtpTransfer\FtpBusinessLogic;

interface AfrFtpBusinessLogicInterface
{
    /**
     * Make backup.
     * @return void
     */
    public function makeBackup(): void;

}
