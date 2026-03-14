# app


### speedup tips opcache

pro-tip: replace .ENV with .PHP to take advantage of opcache

php.ini => zend_extension=opcache


    opcache.memory_consumption	= 256
    opcache.enable_cli = On
    opcache.max_accelerated_files = 32000



### speed-up tips composer

    composer update --no-dev

### speedup tips env

- env files are processed and saved as a 
/afr.env to afr.php 

    env files as .php and oppcache
    enable apcu also for concrete lightweignt 30mb / 4000 indexes

### use memcached+redis+sockCache
### use worker as bridge using apcu for fast / reusable keys

### New project and first deploy of config files 
- php afr-deploy                #Assisted Q&A
- php afr-deploy {deploy-path}  #Custom path
