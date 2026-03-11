<?php

require_once __DIR__ . '/../../../vendor/autoload.php';


$oGa = \Autoframe\Core\CliTools\AfrGetOpt::getInstance();
// Example usage
$arguments = ['test.php', '-f', 'value for f', '-v', '-a', '--time', 'afternoon', '--other=other value', '--option'];
$arguments = ['test.php', '-f=hh', 'value for f', '-rex=x', 'invalid', '-f', 'second f value', '--time', 'afternoon', '--other=other value', '--time', 't2'];
//$arguments = ['test.php', '-fac=hh', '--other=other value', '--option', '-x'];
$arguments = $oGa->tokenizeCliLineInput('test.php -f=hh "value for f" -rex=x invalid -f "second f value" --time afternoon --other="other value" --time t2',false);
var_dump($oGa->getoptDetectAllArgs($arguments));

