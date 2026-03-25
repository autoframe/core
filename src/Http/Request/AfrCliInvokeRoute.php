<?php

namespace Autoframe\Core\Http\Request;

interface AfrCliInvokeRoute extends AfrCliConstantsInterface{
	//CALL using index.php CLI_INVOKE_ARGV_KEY=some.fqcn
	//CALL using index.php CLI_INVOKE_ARGV_KEY=some/fqcn
	//CALL using index.php CLI_INVOKE_ARGV_KEY=some~fqcn
	//CALL using index.php CLI_INVOKE_ARGV_KEY="some\fqcn"
	public function cliInvoke();



}