<?

require_once('../IPSSpeedportHybrid.class.php');

$sp = new SpeedportHybrid($password, $url, $debug, $variable_profile_prefix, $call_sort, $parentId, $fw_update_interval);
$sp->update();
//$sp->cleanup();

if($debug){
	$variables = $sp->getVariables();
	foreach($variables as $var){
			echo $var->getName() . " => " . $var->getValue() ."\n";
	}
}

$sp->logout();

?>