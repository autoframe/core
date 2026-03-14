<?php
declare(strict_types=1);

namespace Autoframe\Core\Env\Parser;

use Autoframe\Core\Env\Exception\AfrEnvException;
use Autoframe\Core\DesignPatterns\Singleton\AfrSingletonInterface;

interface AfrEnvParserInterface extends AfrSingletonInterface
{
    /**
     * Parse str.
     * @param string $sEnvLines
     * @return array
     */
    public function parseStr(string $sEnvLines): array;

    /**
     * Parse file.
     * @param string $sPath
     * @return array
     * @throws AfrEnvException
     */
    public function parseFile(string $sPath): array;

}
