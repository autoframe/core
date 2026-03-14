<?php


namespace Autoframe\Core\String\Validate;


class AfrStrValidate
{
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    //http://www.portabilitate.ro/getnumber.aspx?lang=ro&number=0742601660
    //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
    /**
     * Is mobile.
     */
    public static function is_mobile($tel)
    {
        if (strlen($tel) != 12) {
            $tel = '';
        }
        if (!is_numeric($tel)) {
            $tel = '';
        } //determin daca e nr de tel mobil din romania
        if (substr_count($tel, '+407') != 1) {
            $tel = '';
        }
        if ($tel == '') {
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * Is tel.
     */
    public static function is_tel($tel)
    {
        if (strlen($tel) < 13) {
            $tel = '';
        }
        if (!is_numeric($tel)) {
            $tel = '';
        } //determin daca e  tel din romania
        if (substr_count($tel, '+40') != 1) {
            $tel = '';
        }
        if ($tel == '') {
            return 0;
        } else {
            return 1;
        }
    }

    /**
     * Validate tel.
     */
    public static function validate_tel($tel){//prefixe 02 romtelecom, 03 upc, rds, rcs, zaptelfix, vdf acasa 07 mobil;
        if($tel[0]=='4'){$tel='+'.$tel;}//fix convertion to number
        if(strlen($tel)<10){return '';}	$replace='.,- \'"*%#`	~()[\]|<>?/';
        for($i=0;$i<strlen($replace);$i++){$tel=str_replace($replace[$i],NULL,$tel);}
        if(!is_numeric($tel)){return '';}
        if(strlen($tel)>13){return '';}

        if(strlen($tel)==10){//scurt fara +4
            if(substr($tel,0,2)=='07'){$tel='+4'.$tel;}
            elseif(substr($tel,0,2)=='02'){$tel='+4'.$tel;}
            elseif(substr($tel,0,2)=='03'){$tel='+4'.$tel;}
            else{return '';}
        }
        elseif(strlen($tel)==11){
            if(substr($tel,0,2)=='02'){$tel='+4'.$tel;}
            elseif(substr($tel,0,2)=='03'){$tel='+4'.$tel;}
            else{return '';}
        }
        elseif(strlen($tel)==12){	if(substr($tel,0,2)!='+4'){return '';}		}
        elseif(strlen($tel)==13){
            if(substr($tel,0,4)=='+402'){}
            elseif(substr($tel,0,4)=='+403'){}
            else{return '';}
        }
        return $tel;
    }

}
