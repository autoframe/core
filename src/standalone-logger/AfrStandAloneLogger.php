<?php

if (empty($_SERVER['REQUEST_TIME_FLOAT'])) {
    $_SERVER['REQUEST_TIME_FLOAT'] = microtime(true);
}

class AfrStandAloneLogger
{
    /**
     * @var string
     */
    public static $sCtrl = ''; // se va seta intre controlere diferite / info diferite

    /**
     * @var bool
     */
    public static $bHrTime = false;
    /**
     * @var bool
     */
    public static $bRealMemoryUsage = false;
    /**
     * @var int
     */
    public static $traceLevel = 0;
    /**
     * @var array
     */
    protected $aTimeMarker = [];
    /**
     * @var array
     */
    protected $aMemoryMb = [];
    /**
     * @var array
     */
    protected $aPreservedArgs = [];

    /**
     * @var array
     */
    protected $aBacktrace = [];
    /**
     * @var array
     */
    protected static $instances = [];

    final protected function __construct()
    {
        $this->mark(static::class);
    }


    /**
     * @throws Exception
     */
    final public function __clone()
    {
        throw new Exception('Cannot clone a singleton: ' . static::class);
    }

    /**
     * @throws Exception
     */
    final public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton: ' . static::class);
    }


    /**
     * The method you use to get the Singleton's instance.
     * @return self
     */
    final public static function getInstance()
    {
        if (empty(self::$instances[static::class])) {
            self::$instances[static::class] = new static();
            return self::$instances[static::class];
        }
        return self::$instances[static::class];
    }

    /**
     * Check set time float.
     * @return float
     */
    public static function checkSetTimeFloat()
    {
        if (empty($_SERVER['REQUEST_TIME_FLOAT'])) {
            $_SERVER['REQUEST_TIME_FLOAT'] = static::hrMicroTime();
        }
        return (float)$_SERVER['REQUEST_TIME_FLOAT'];
    }

    /**
     * Hr micro time.
     * @return float
     */
    public static function hrMicroTime()
    {
        if (static::$bHrTime && function_exists('hrtime')) {
            return hrtime(true) / 1e+6;
        }
        return microtime(true);
    }

    /**
     * Memory usage.
     * @return float
     */
    public static function memoryUsage()
    {
        
        $_SERVER['REQUEST_TIME_FLOAT'] ??= microtime(true);
        

        return round((memory_get_usage(static::$bRealMemoryUsage) / (1024 * 1024)), 2);
    }

    /**
     * Mark.
     * @param string|null $name
     * @param array $aArgs
     * @param int $iUpperTraceLimit
     * @param int $traceLevel
     * @return void
     */
    public function mark($name = null, $aArgs = [], $iUpperTraceLimit = 5, $traceLevel = null)
    {
        $fTime = static::hrMicroTime();
        $fMem = static::memoryUsage();

        if ($name === null) {
            $name = '#' . count($this->aTimeMarker);
        }
        $name .= '@' . trim(
                (string)round(static::hrMicroTime() - static::checkSetTimeFloat(), 6),
                '0'
            );
        $i = 2;
        while (isset($this->aTimeMarker[$name])) {
            $name .= '_' . $i;
            $i++;
        }

        $this->aTimeMarker[$name] = $fTime;
        $this->aMemoryMb[$name] = $fMem;
        $this->aPreservedArgs[$name] = $aArgs;
        $this->aBacktrace[$name] = $iUpperTraceLimit ? static::get_minified_backtrace(
            2,
            $iUpperTraceLimit,
            $traceLevel === null ? static::$traceLevel : $traceLevel
        ) : '-';
    }

    /**
     * Elapsed time.
     * @param $point1
     * @param $point2
     * @return float
     */
    public function elapsedTime($point1 = null, $point2 = null)
    {
        if ($point1 === null) {
            $point1 = array_key_first($this->aTimeMarker);
        }

        return round(
            (isset($this->aTimeMarker[$point2]) ? $this->aTimeMarker[$point2] : static::hrMicroTime()) - $this->aTimeMarker[$point1],
            4
        );
    }

    /**
     * Wall time.
     * @param bool $bDiff
     * @return array
     */
    public function wallTime($bDiff = false)
    {
        $aWall = array_merge(['REQUEST_TIME_FLOAT' => self::checkSetTimeFloat()], $this->aTimeMarker);
        if ($bDiff) {
            $aDiff = [];
            $prevK = 'REQUEST_TIME_FLOAT';
            $aWall['now'] = static::hrMicroTime();
            foreach ($aWall as $k => $v) {
                if ($k === $prevK) {
                    continue;
                }
                $aDiff[$prevK . ' to ' . $k] = number_format($v - $aWall[$prevK],6);
                $prevK = $k;
            }
            return $aDiff;
        }
        return $aWall;
    }

    /**
     * Array to string.
     * @param array $aData
     * @return string
     */
    public static function arrayToString(array $aData)
    {
        $bAssocKeys = false;
        foreach ($aData as $mKey => &$arg) {
            if (!$bAssocKeys && !is_numeric($mKey)) {
                $bAssocKeys = true;
            }
            if (is_object($arg)) {
                $arg = get_class($arg);
            } elseif ($arg === null) {
                $arg = 'NULL';
            } elseif (is_bool($arg)) {
                $arg = $arg ? 'TRUE' : 'FALSE';
            } elseif (is_array($arg)) {
                $arg = static::arrayToString($arg);
            } elseif (is_resource($arg)) {
                $arg = 'resource#' . get_resource_id($arg) . '@' . get_resource_type($arg);
            } else {
                $arg = (string)$arg;
            }
        }
        if ($bAssocKeys) {
            $sReturn = '';
            foreach ($aData as $mKey => $sVal) {
                $sReturn .= $sReturn ? ', ' : '[';
                $sReturn .= $mKey . '=>' . $sVal;
            }
            return $sReturn . ']';
        }

        return '[' . implode(', ', $aData) . ']';
    }

    /**
     * Get minified backtrace.
     * @param int $iSkip
     * @param int $iUpperTraceLimit
     * @param int $traceLevel
     * @return array
     */
    public static function get_minified_backtrace(
        $iSkip = 1,
        $iUpperTraceLimit = 5,
        $traceLevel = DEBUG_BACKTRACE_PROVIDE_OBJECT
    )
    {
        $aHuge = debug_backtrace($traceLevel, $iUpperTraceLimit + $iSkip);
        $aStack = [];
        foreach ($aHuge as $iKey => &$aItem) {
            if ($iKey >= $iSkip) {
                $aItem['file'] = isset($aItem['file']) ? $aItem['file'] : '[PHP Kernel]';
                $aItem['line'] = isset($aItem['line']) ? $aItem['line'] : '0';
                if (!empty($aItem['object']) && is_object($aItem['object'])) {
                    $aItem['object'] = get_class($aItem['object']);
                }
                if (!empty($aItem['args']) && is_array($aItem['args'])) {
                    $aItem['args'] = static::arrayToString($aItem['args']);
                } else {
                    $aItem['args'] = '[]';
                }


                $sText = '#' . ($iKey - $iSkip) . ' ' . $aItem['file'] . ':' . $aItem['line'] . ' ';
                $sText .= (!empty($aItem['object']) ? $aItem['object'] . (isset($aItem['type']) ? $aItem['type'] : '') : '');
                $sText .= (isset($aItem['function']) ? $aItem['function'] : '') . '(' . substr($aItem['args'], 1, -1) . ')';

                $aStack[] = $sText;

            }


        }
        unset($aItem);
        unset($aHuge);

        return $aStack;
    }

    /**
     * Get report array.
     * @param bool $bRequestParameters
     * @return array
     */
    public function getReportArray($bRequestParameters = false)
    {
        $aNullableError = error_get_last();
        return [
            'iErrCode' => isset($aNullableError['type']) ? $aNullableError['type'] : 0,
            'sErrInfo' => !empty($aNullableError) ? json_encode($aNullableError) : '',
            'sFromWhatPath' => static::fromWhatPath(false),
            'iHttpResponseCode' => (int)http_response_code(),
            'sCtrl' => static::$sCtrl,
            'fExecTime' => static::hrMicroTime() - static::checkSetTimeFloat(),
            'aWallTime' => $this->wallTime(false),
            'aWallTimeDiff' => $this->wallTime(true),
            'aMarks' => array_keys($this->aTimeMarker),
            'aMemoryMb' => $this->aMemoryMb,
            'aPreservedArgs' => $this->aPreservedArgs,
            'aBacktrace' => $this->aBacktrace,
            'sRequestParameters' => $bRequestParameters ? static::requestParametersAsString() : '-',
        ];
    }

    /**
     * From what path.
     * @param bool $bFilename
     * @return string
     */
    public static function fromWhatPath($bFilename)
    {
        $path = !empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : $_SERVER['PHP_SELF'];
        if ($bFilename) {
            return static::checkSetTimeFloat() . '.' . rand(100, 999) . '@' .
                preg_replace('/[^a-z0-9]+/', '-', strtolower($path)) . '.log';
        }
        return $path;
    }

    /**
     * Request parameters as string.
     * @return string
     */
    public static function requestParametersAsString()
    {
        $sDelim = "\n~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~\n";
        return
            'IP:' . static::getClientIpAddr() . $sDelim .
            'UA:' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '-') . $sDelim .
            'POST:' . print_r(isset($_POST) ? $_POST : null, true) . $sDelim .
            'GET:' . print_r(isset($_GET) ? $_GET : null, true) . $sDelim .
            'FILES:' . print_r(isset($_FILES) ? $_FILES : null, true) . $sDelim .
            'COOKIE:' . print_r(isset($_COOKIE) ? $_COOKIE : null, true) . $sDelim .
            'SESSION:' . print_r(isset($_SESSION) ? $_SESSION : null, true) . $sDelim .
            'ARGV:' . print_r(isset($_SERVER['argv']) ? $_SERVER['argv'] : null, true) . $sDelim;
    }

    /**
     * Get client ip addr.
     * @return string
     */
    public static function getClientIpAddr()
    {
        foreach ([
                     'HTTP_TRUE_CLIENT_IP',  //cloudflare
                     'HTTP_X_FORWARDED_FOR',
                     'HTTP_X_FORWARDED',
                     'HTTP_X_CLUSTER_CLIENT_IP',
                     'HTTP_FORWARDED_FOR',
                     'HTTP_FORWARDED',
                     'HTTP_CLIENT_IP',
                     'REMOTE_ADDR',
                 ] as $sKey) {
            if (isset($_SERVER[$sKey]) && filter_var($_SERVER[$sKey], FILTER_VALIDATE_IP)) {
                return $_SERVER[$sKey];
            }
        }
        return 'UNKNOWN_IP';
    }

    /**
     * Dump to file.
     * @param string $sDirPath
     * @param bool $bRequestParameters
     * @param bool $bNameByExecTime
     * @return void
     */
    public function dumpToFile($sDirPath, $bRequestParameters = false, $bNameByExecTime = false)
    {
        $aReportArray = $this->getReportArray($bRequestParameters);
        if (!is_dir($sDirPath)) {
            $sDirPath = __DIR__;
        }
        $sDirPath = rtrim($sDirPath, '/\\') . DIRECTORY_SEPARATOR;
        $sDirPath .= $bNameByExecTime ? $aReportArray['fExecTime'] . '_' : '';
        $sDirPath .= static::fromWhatPath(true);
        $sDirPath .= $bNameByExecTime ? '' : '_T_' . $aReportArray['fExecTime'] . '.log';
        file_put_contents($sDirPath, print_r($aReportArray, true));
    }

    /**
     * Dump to sql.
     * @param bool $bRequestParameters
     * @param bool $bJson
     * @param string $sTbl
     * @return false|int|string|null
     */
    public function dumpToSQL($bRequestParameters = false, $bJson = false, $sTbl = 'afr_performance')
    {

        $aReportArray = $this->getReportArray($bRequestParameters);
        foreach ($aReportArray as &$v) {
            if (is_array($v)) {
                $v = $bJson ? json_encode($v) : print_r($v, true);
            } else {
                $v = trim($v . ' '); //force cast to str
            }
        }
        $sTbl .= '_' . date('Ymd');
        $qTbl = "CREATE TABLE IF NOT EXISTS `$sTbl` (\n `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,\n ";
        foreach (array_keys($aReportArray) as $col) {
            $qTbl .= "`$col` text,\n";
        }
        $qTbl .= " PRIMARY KEY (`id`) \n) ENGINE=MyISAM DEFAULT CHARSET=utf8;";
        sql_query($qTbl);
        return insert_qa($sTbl, $aReportArray);

    }

}



