# left-off:

de revizuit convertFacade / interface sa mearga fara sau cu Alias....

de facut interfata / fatada pentru DB si tbl / apoi entity


# trebuie sa:
    
## sa rescriu il loc de with static sa fie pe instante

    - pot sa citesc bazele de date de pe serverul live, adica din alias
    - pot sa citesc views ca si readonly
    - o sa tin o structua de array ca si storage model
    - array model se populeaza din 
        - alias->db => si din array in create / alter db / table
        - migrare clasa din alias si invers
        - pachete de upgrade din composer cu structura de array merge
        - overwrite / lock structura array tabele
    - entity generare nou / update clase cu trait de autofill property
    - entity pivot / junction tables
    - hidrate / cache