# TODO:


- AfrEvent + Tenant Config C:\xampp\htdocs\core\src\!b2b-app\afr\Tenant\AfrEvent.php
- ENV + 
- logger class
- DATABASE, de terminat cu PDO base QUERY!
- de integrat Memcached / socket cache in DB
- AW CONTAINER
- afrConfig cel mai probabil ca si design pattern
- routing
- views...


# FILOZIFIA:

- fiecare interfata va fi un serviciu
- interfetele se vor initia prin fatade sau AW
- AW conform context din srategies, etc
- va exista un config de aw de mapare ca si php(opcache) / serialize
- daca se foloseste interface to concrete, el se va folosi ca si fallback si se va salva in cache
- lista de file cache dupa tipologie si instructiuni clear...
- 