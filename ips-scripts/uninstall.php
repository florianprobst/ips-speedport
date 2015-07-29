<?
//Das Ausführen des Skripts entfernt alle durch das Skript erstellten Variablen und Variablenprofile.
//Anmerkung: das Skript sucht nur unterhalb der ParentId des Config Skripts nach Speedport-Variablen.

$config_script = 41641 /*[System\Skripte\Speedport\Config]*/; //instanz id des ip-symcon config skripts

require_once(IPS_GetScript($config_script)['ScriptFile']);
require_once('../webfront/user/ips-speedport/IPSSpeedportHybrid.class.php');

$sp = new IPSSpeedportHybrid($password, $url, $debug, $variable_profile_prefix, $call_sort, $parentId, $fw_update_interval);
$sp->cleanup();
?>