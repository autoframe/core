<?php


namespace Autoframe\Core\String\Htpwd;


class AfrStrHtpwd
{
    /**
     * Crypt apr1 md5.
     */
    public static function crypt_apr1_md5($plainpasswd)
    {
        /* .htaccess
        AuthName "Protected Area. Use Admin Credentials"
        AuthType Basic
        AuthUserFile C:/xampp/.htpasswd
        Require valid-user
        Require ip 192.168.10. 192.168.40

        #############
        #Deny from 192.168.10.1
        #AuthName "Protected Area. Use Admin Credentials"
        #AuthType Basic
        #AuthUserFile C:/xampp/.htpasswd
        #Require valid-user
        #Satisfy Any
        #ErrorDocument 401     /401.html

        .htpasswd
        USERNAME:$apr1$....

         */
        $salt = substr(str_shuffle("abcdefghijklmnopqrstuvwxyz0123456789"), 0, 8);
        //	$salt='eI9V5izx';
        $len = strlen($plainpasswd);
        $text = $plainpasswd . '$apr1$' . $salt;
        $bin = pack("H32", md5($plainpasswd . $salt . $plainpasswd));
        for ($i = $len; $i > 0; $i -= 16) {
            $text .= substr($bin, 0, min(16, $i));
        }
        for ($i = $len; $i > 0; $i >>= 1) {
            $text .= ($i & 1) ? chr(0) : $plainpasswd[0];
        }
        $bin = pack("H32", md5($text));
        for ($i = 0; $i < 1000; $i++) {
            $new = ($i & 1) ? $plainpasswd : $bin;
            if ($i % 3) $new .= $salt;
            if ($i % 7) $new .= $plainpasswd;
            $new .= ($i & 1) ? $bin : $plainpasswd;
            $bin = pack("H32", md5($new));
        }
        $tmp = null;
        for ($i = 0; $i < 5; $i++) {
            $k = $i + 6;
            $j = $i + 12;
            if ($j == 16) $j = 5;
            $tmp = $bin[$i] . $bin[$k] . $bin[$j] . $tmp;
        }
        $tmp = chr(0) . chr(0) . $bin[11] . $tmp;
        $tmp = strtr(strrev(substr(base64_encode($tmp), 2)),
            "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/",
            "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz");

        return "$" . "apr1" . "$" . $salt . "$" . $tmp;

    }


    /**
     * Write htpasswd file.
     */
    public static function write_htpasswd_file($user_pass=array()){
        $pw='';
        foreach($user_pass as $user=>$pass){
            $pw.=$user.':'.crypt_apr1_md5($pass)."\r\n";
        }
        if(!$pw || count ($user_pass)<1){die('NO PASSWORD SET!');}
        file_put_contents('./.htpasswd',$pw);

        $htaccess_add='
	#Deny from 192.168.10.1		#not required. use only for internal network gateway
	AuthName "Protected Area. Use Admin Credentials"
	AuthType Basic
	AuthUserFile '.dirname($_SERVER['SCRIPT_FILENAME']).'/.htpasswd
	Require valid-user
	#Require ip 192.168.10. 192.168.40 #allow ip range without password
	#Satisfy Any		#not required
	ErrorDocument 401 "<h1>Use Admin Credentials to autentificate!</h1><br />If this problem persists, contact the administrator!"
	<Files ~ "^\.(htaccess|htpasswd)$">
	deny from all
	</Files>
	';
        //create once the htaccess file
        if(!is_file('./.htaccess')){
            file_put_contents('./.htaccess',$htaccess_add);
        }
        return 1;
    }

}
