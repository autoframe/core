<?php
declare(strict_types=1);

namespace Autoframe\Core\Bank\ExchangeRate;


//     $curs=new cursBnrThf(array('EUR','USD'));	//$curs->test();
//TODO!!!!!!!

use SimpleXMLElement;

class cursBnrXML
{
	protected static array $aNbFrames = [];
    protected string $sXmlUrl = 'https://www.bnr.ro/nbrfxrates.xml';
	protected string $date;
	protected array $currency;

    function __construct()
    {
    }

	public function setXmlUrl(string $sXmlUrl): self
	{
		$this->sXmlUrl = $sXmlUrl;
		return $this;
	}

    protected function parseXMLDocument()
    {
		if(empty(static::$aNbFrames[$this->sXmlUrl])){
			static::$aNbFrames[$this->sXmlUrl] = file_get_contents($this->sXmlUrl);
		}
        $xml = new SimpleXMLElement(static::$aNbFrames[$this->sXmlUrl]);
        $this->date = (string)$xml->Header->PublishingDate;
        foreach ($xml->Body->Cube->Rate as $line) {
            $this->currency[] = array("name" => $line["currency"], "value" => $line, "multiplier" => $line["multiplier"]??1);
        }
    }

    public function getExchangeRate(string $currency)
    {
		if(empty($this->date)){
			$this->parseXMLDocument();
		}

        foreach ($this->currency as $line) {
            if ($line['name'] == $currency) {
                return $line['value'];
            }
        }
        return 'Incorrect currency!';
    }
}

class cursBnrThf extends cursBnrXML
{
    protected array $monede = array('EUR', 'USD');

    function __construct($monede = array())
    {
        if (!empty($monede)) {
            $this->monede = $monede;
        }
        $this->checkLatest();
    }

    function checkLatest()
    {
        $storedDate = get_sv_val('curs_bnr_date');
        if (!$storedDate || date('Y-m-d', strtotime($storedDate)) < date('Y-m-d', time() - 3600 * 14 - 60 * 10)) {
            //no date or date older than 1 day and 14:10 minutes
            $this->updateCurs();
        }
    }

    public function updateCursInServerVal()
    {
        parent::__construct();
        set_sv_val('curs_bnr_date', date('Y-m-d'));
        set_sv_val('curs_bnr_last_date', $this->date);
        foreach ($this->monede as $moneda) {
            set_sv_val('curs_bnr_' . $moneda, $this->getExchangeRate($moneda));
        }
    }

    function test()
    {
        $this->updateCurs();
        print get_sv_val('curs_bnr_date');
        print '~';
        print get_sv_val('curs_bnr_last_date');
        print "<hr>";
        print "USD: " . get_sv_val('curs_bnr_USD');
        print "<hr>";
        print "EUR: " . get_sv_val('curs_bnr_EUR');
        print "<hr>";
    }
}
