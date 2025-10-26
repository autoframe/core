<?php

namespace Autoframe\Core\Exception;
//TODO:
classss AfrExceptionHandler {}
/*
set_exception_handler(function(){});
set_exception_handler(?callable $callback): string|array|object|null
set_error_handler(?callable $callback, int $error_levels = E_ALL): string|array|object|null
*/

/*
     set_error_handler(
        function($level, $error, $file, $line){
            if(0 === error_reporting()){
                return false;
            }
            throw new ErrorException($error, -1, $level, $file, $line);
        },
        E_ALL
    );

    register_shutdown_function(function(){
        $error = error_get_last();
        if($error){
            throw new ErrorException($error['message'], -1, $error['type'], $error['file'], $error['line']);
        }
    });

    set_exception_handler(function($exception){
        // ... more code ...
    });
 * */