<?
//Sammelt alle Statusinformationen, Anruferlisten, etc. und legt diese in den dafόr vorgesehenen IPS Variablen ab.
//Es ist ratsam dieses Skript per Interval-Ereignis in IP-Symcon regelmδίig auszufόhren. (bsp.: alle 10 Minuten)

$config_script = 41641 /*[System\Skripte\Speedport\Config]*/; //instanz id des ip-symcon config skripts

require_once(IPS_GetScript($config_script)['ScriptFile']);
require_once('../webfront/user/ips-speedport/IPSSpeedportHybrid.class.php');

$sp = new IPSSpeedportHybrid($password, $url, $debug, $variable_profile_prefix, $call_sort, $parentId, $fw_update_interval);
$sp->update();

$event = @IPS_GetEventIDByName($variable_profile_prefix . "UpdateStatusEvent", $_IPS['SELF']);
if($event == null){
	$event = IPS_CreateEvent(1); //zyklisches Event
	IPS_SetName($event, $variable_profile_prefix . "UpdateStatusEvent");
	IPS_SetEventCyclic($event, 0, 0, 0, 0, 2, $status_update_interval);
	IPS_SetParent($event, $_IPS['SELF']);
	IPS_SetEventActive($event, true);
}
?>