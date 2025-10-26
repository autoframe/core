# memcache / memcached
 TODO check if memcache exists!
## ini:   
- session.save_handler=memcached
- session.save_path=mHost:11211
## php:
 session_module_name('memcache');
 session_save_path('tcp://127.0.0.1:11211?persistent=1&weight=2&timeout=10&retry_interval=10');
 session_save_path('localhost:11211'); // memcache uses port 11211
 session_save_path('localhost:11211:41,otherHost:11211:60') // First part is hostname or path to socket, next is port and the last is the weight for that server
 session_start();

# files

## ini:

- session.save_handler=files
- session.save_path=/some/path

## php:
session_module_name("files");  // ASCII files
session_save_path(/some/path); // memcache uses port 11211
session_start();


# sqlite

## ini:
session.save_handler = sqlite
session.save_path = /tmp/phpsess.db



# user deprecated from ini... use 		session_set_save_handler(SessionHandlerInterface, true);
session_module_name("user");   // Custom session backend deprecated in php 7.2 
use:
- session_set_save_handler(SessionHandlerInterface+ SessionUpdateTimestampHandlerInterface, true);
