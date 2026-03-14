<?php

/*

$oCache = UryMemcached::getInstance();
$oCache->set('cheie','valoare');
echo $oCache->get('cheie');


 * */

class UryMemcached
{

    private $bConnected = false;
    private $oConnection = null;

    private static $aInstances = [];

    protected function __construct()
    {
    }

    /**
     * @return mixed
     * @throws Exception
     */
    protected function __clone()
    {
        throw new Exception('Cannot clone a singleton');
    }

    /**
     * Restore the instance after unserialization.
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }

    /**
     * Has instance.
     * @return bool
     */
    public static function hasInstance()
    {
        return isset(self::$aInstances[static::class]);
    }

    /**
     * Get instance.
     * @return UryMemcached
     */
    public static function getInstance()
    {
        $subclass = static::class;
        if (!self::hasInstance()) {
            $oMemcached = self::$aInstances[$subclass] = new static();
            $oMemcached->connect();
            return $oMemcached;
        }
        return self::$aInstances[$subclass];
    }


    /**
     * Connect.
     */
    public function connect($ip = '127.0.0.1', $on_new_object_cb = '11211', $connection_str = 1)
    {
        if (!$this->bConnected) {
            $this->oConnection = new Memcached($connection_str);

            if (!is_object($this->oConnection)) {
                return false;
            } else {
                $this->oConnection->addServers(array(
                    array($ip, $on_new_object_cb),
                ));
            }
        }
        return true;
    }

    /**
     * Functia care sterge date din cache
     */
    public function delete($sCheie, $iDelay = 0)
    {
        return $this->oConnection->delete($sCheie, $iDelay);
    } // END func delete()


    /**
     * Functia care se ocupa de inlocuirea valorii unei chei din cache
     */
    public function replace($sCheie, $mValue, $iTtl = 3600, $iFlag = 0)
    {
        $this->oConnection->delete($sCheie);
        return $this->set($sCheie, $mValue, $iTtl);
    } // END func replace()

    /*
        public function increment($key, $value = 1, $ttl = 3600)
        {
            $this->oConnection->add($key, 0, $ttl);
            $this->oConnection->increment($key, $value);
        }
    */
    // doar pe aici se acceseaza memcache-ul
    /**
     * Get.
     */
    public function get($sKey)
    {
        $mRaspuns = $this->oConnection->get($sKey);
        if ($mRaspuns && is_string($mRaspuns)) {
            return unserialize($this->oConnection->get($sKey));
        }
        return false;
    }

    // doar pe aici se acceseaza memcache-ul
    /**
     * Get multi.
     */
    public function getMulti($sKey)
    {
        return $this->oConnection->getMulti($sKey);
    }

    // doar pe aici se acceseaza memcache-ul
    /**
     * Set.
     */
    public function set($sCheie, $mValue, $iTtl = 3600)
    {
        return $this->oConnection->set($sCheie, serialize($mValue), $iTtl);
    }

    /**
     * Curatam tot cache-ul
     */
    public function flush()
    {
        return $this->oConnection->flush();
    } // END func flush()

    /**
     * Get all keys.
     */
    public function getAllKeys()
    {
        return $this->oConnection->getAllKeys();
    }

    /**
     * Fetch all.
     */
    public function fetchAll()
    {
        return $this->oConnection->fetchAll();
    }

    /**
     * Get key by prefix.
     */
    public function getKeyByPrefix($sCheie)
    {
        $iPrelixLen = strlen($sCheie);
        $aFound = [];
        foreach ($this->oConnection->getAllKeys() as $sVal) {
            if (substr($sVal, 0, $iPrelixLen) === $sCheie) {
                $aFound[] = $sVal;
            }
        }
        return $aFound;
    }

    /**
     * Get key containing.
     */
    public function getKeyContaining($sCheie)
    {
        $aFound = [];
        foreach ($this->oConnection->getAllKeys() as $sVal) {
            if (strpos($sVal, $sCheie) !== false) {
                $aFound[] = $sVal;
            }
        }
        return $aFound;
    }

    /**
     * Get stats.
     * @return array|false
     */
    public function getStats()
    {
        return $this->oConnection->getStats();
    }


    /**
     * Print details.
     */
    public function printDetails(){
        $aStats = $this->getStats();
        if(!$aStats){
            echo 'Memcached stats are unavailable!';
            return;
        }


        echo "<table border='1'>";



        echo "<tr><td>Memcache Server version:</td><td> ".$aStats ["version"]."</td></tr>";

        echo "<tr><td>Process id of this server process </td><td>".$aStats ["pid"]."</td></tr>";

        echo "<tr><td>Number of seconds this server has been running </td><td>".$aStats ["uptime"]."</td></tr>";

        echo "<tr><td>Accumulated user time for this process </td><td>".$aStats ["rusage_user"]." seconds</td></tr>";

        echo "<tr><td>Accumulated system time for this process </td><td>".$aStats ["rusage_system"]." seconds</td></tr>";

        echo "<tr><td>Total number of items stored by this server ever since it started </td><td>".$aStats ["total_items"]."</td></tr>";

        echo "<tr><td>Number of open connections </td><td>".$aStats ["curr_connections"]."</td></tr>";

        echo "<tr><td>Total number of connections opened since the server started running </td><td>".$aStats ["total_connections"]."</td></tr>";

        echo "<tr><td>Number of connection structures allocated by the server </td><td>".$aStats ["connection_structures"]."</td></tr>";

        echo "<tr><td>Cumulative number of retrieval requests </td><td>".$aStats ["cmd_get"]."</td></tr>";

        echo "<tr><td> Cumulative number of storage requests </td><td>".$aStats ["cmd_set"]."</td></tr>";



        $percCacheHit=((float)$aStats ["get_hits"]/ (float)$aStats ["cmd_get"] *100);

        $percCacheHit=round($percCacheHit,3);

        $percCacheMiss=100-$percCacheHit;



        echo "<tr><td>Number of keys that have been requested and found present </td><td>".$aStats ["get_hits"]." ($percCacheHit%)</td></tr>";

        echo "<tr><td>Number of items that have been requested and not found </td><td>".$aStats ["get_misses"]."($percCacheMiss%)</td></tr>";



        $MBRead= (float)$aStats["bytes_read"]/(1024*1024);



        echo "<tr><td>Total number of bytes read by this server from network </td><td>".$MBRead." Mega Bytes</td></tr>";

        $MBWrite= (float)$aStats["bytes_written"] /(1024*1024) ;

        echo "<tr><td>Total number of bytes sent by this server to network </td><td>".$MBWrite." Mega Bytes</td></tr>";

        $MBSize=(float) $aStats["limit_maxbytes"]/(1024*1024) ;

        echo "<tr><td>Number of bytes this server is allowed to use for storage.</td><td>".$MBSize." Mega Bytes</td></tr>";

        echo "<tr><td>Number of valid items removed from cache to free memory for new items.</td><td>".$aStats ["evictions"]."</td></tr>";



        echo "</table>";



    }

    /**
     * Get max keys.
     */
    public function getMaxKeys()
    {

    }

    private function calculateMaxKeys(int $iUpperLimit = 600)
    {
        for ($i = 1; $i <= 1000 * $iUpperLimit; $i++) {
            $this->set('k' . $i, 'y', 120);
        }
        $iKeys = (int)$this->getAllKeys();
        if ($iKeys) {
            file_put_contents(__DIR__ . DIRECTORY_SEPARATOR);
        }

    }


}


