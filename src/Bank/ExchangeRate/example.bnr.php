<?php
namespace Autoframe\Core\Bank\ExchangeRate;

require_once __DIR__ . '/../../../vendor/autoload.php';


$oFx = AfrCurrencyExchangeBnr::getInstance();
//$oFx->setDefaultToCurrency(AfrCurrencyCodeInterface::EUR);

// latest rate: 1 EUR => x RON
$fEurToRon = $oFx->getExchangeRateBaseCurrency(AfrCurrencyCodeInterface::EUR);

// latest conversion
$sDefC = $oFx->getDefaultToCurrency();
$fRon = $oFx->convert(100.0, AfrCurrencyCodeInterface::EUR);
$fEur = $oFx->convert(100.0, AfrCurrencyCodeInterface::USD, AfrCurrencyCodeInterface::EUR);

// date-based conversion, if that day exists in cache
$fHistorical = $oFx->convertByDate(
	100.0,
	AfrCurrencyCodeInterface::USD,
	gmdate('Y-m-01'),
	AfrCurrencyCodeInterface::EUR
);
echo "fEurToRon:$fEurToRon
sDefC:$sDefC
fRon:$fRon
fEur:$fEur
fHistorical:$fHistorical
";