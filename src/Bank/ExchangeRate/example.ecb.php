<?php
namespace Autoframe\Core\Bank\ExchangeRate;
require_once __DIR__ . '/../../../vendor/autoload.php';


$oFx = AfrCurrencyExchangeEcb::getInstance();
$oFx->setDefaultToCurrency(AfrCurrencyCodeInterface::USD);

// latest rate: 1 EUR => x USD
$fEurToUsd = $oFx->getExchangeRateBaseCurrency(AfrCurrencyCodeInterface::USD);
$oFx->setDefaultToCurrency(AfrCurrencyCodeInterface::USD);
$fEurToUsdx = $oFx->getExchangeRateBaseCurrency(AfrCurrencyCodeInterface::USD);
$oFx->setDefaultToCurrency(AfrCurrencyCodeInterface::EUR);

// latest conversion
$fUsd = $oFx->convert(100.0, AfrCurrencyCodeInterface::EUR, AfrCurrencyCodeInterface::USD);
$fEur = $oFx->convert(100.0, AfrCurrencyCodeInterface::RON, AfrCurrencyCodeInterface::EUR);

// date-based conversion, if that day exists in cache
$fHistorical = $oFx->convertByDate(
	100.0,
	AfrCurrencyCodeInterface::USD,
	gmdate('Y-m-01'),
	AfrCurrencyCodeInterface::RON
);

echo "fEurToUsd:$fEurToUsd
fEurToUsdx:$fEurToUsdx
fUsd:$fUsd
fEur:$fEur
fHistorical:$fHistorical
";