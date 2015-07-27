<?
// Aufgabe des Skripts
// -------------------
// Dieses Skript greift auf die Weboberfläche des Telekom Speedport Hybrid Routers zu
// und liest dabei alle möglichen Informationen aus. Dazu gehören u.a. DSL-Status,
// IP-Adresse, Anruflisten, DSL-Informationen, Leitungsqualität, LTE-Verbindungsqualität,
// WLAN-Informationen, etc.
//
//
// Weiterführende Informationen
// ----------------------------
// Das Skript legt selbstständig benötigte IPS-Variablen und Variablenprofile unterhalb des Skriptes an.
// Derzeit sind dies 29-Variablen und 9 Variablenprofile.
// Durch das Speichern der Werte in IPS-Variablen wird Logging und das Anbinden von IPS-Events
// ermöglicht.
// Zur besseren Auffindbarkeit und eindeutigen Zuordnung werden alle Variablenprofile mit einem Präfix
// angelegt. Standardmässig lautet das "Speedport_".
//
// Die in den Variablenprofilen festgelegten Wertungen (was ist gut und was schlecht) von Dämpfungswerten,
// etc. basieren auf Daten aus meiner Internetrecherche. Dafür keine Gewähr :-)
//
// Es ist zu berücksichtigen, dass jeder Aufruf dieses Skripts andere Benutzer aus der
// Weboberfläche des Routers herauswirft. Desweiteren benötigt das Skript je nach Rechenkapazität
// und Verbindung des IPS-Hosts zum Router einen Moment zur Ausführung. Bei mir sind es bis zu 5 Sekunden.
//
// Getestet wurde das Skript bei mir mit einem Speedport Hybrid auf Firmware 050124.01.00.057
//
// Ältere Speedportmodelle verfügen über ein anderes Webinterface / Anmeldeverfahren und funktionieren
// voraussichtlich nicht. Eventuell funktioniert es mit aktuellen Non-Hybrid-Speedports.
//
//
// Installation
// ------------
// 1. In IPS ein neues Skript anlegen, diesen Code einfügen
// 2. die fünf Konfigurationsparameter anpassen (zumindest IP und Passwort müssen angepasst werden)
// 3. Skript ausführen. Fertig.
//
// zur schnelleren Deinstallation gibt's ganz unten eine auskommentierte Funktion $sp->cleanup();
// Wenn die ausgeführt wird, werden alle erstellten Variablen und Variablenprofile wieder gelöscht.
// (Achtung: Variablenprofile werden anhand des Präfix gesucht. Wenn der geändert wurde und noch alte
// Profile existieren, werden diese nicht automatisch gelöscht.)
//
//
// Externe Quellen
// ---------------
// Das Script setzt die Klasse "speedport" von Jan Altensen voraus. Diese ist in diesem Skript
// inkludiert. (Quelle: https://github.com/Stricted/speedport-hybrid-php-api/). Da die Klasse Funktionen
// von PHP 5.5 voraussetzt (PBKDF2) ist die Login-Funktion angepasst worden, dass bei PHP < 5.5 die
// Funktion compat_pbkdf2() aufgerufen wird. Diese habe ich von hier bezogen:
// Quelle: https://gist.github.com/rsky/5104756
//
// Auch wenn es schöner aussieht, die einzelnen Klassen und Funktionen in "includes / requires" zu packen
// habe ich in diesem Fall darauf verzichtet um eine einfache Installation zu ermöglichen. Ihr könnt das
// gerne auseinandernehmen :-)
//
// Autor: mesa

/**
	* DER NACHFOLGENDE BEREICH MUSS ANGEPASST WERDEN
**/

$password = "70334117";										//Kennwort für den Zugriff auf den Router
$url = "http://192.168.1.1/";							//IP-Adresse des Speedport-Routers (häufig auch "speedport.ip")

/**
	* OPTIONALE ANPASSUNGEN
**/
$debug = false;														//Debug-Informationen auf Konsole ausgeben
$variable_profile_prefix = "Speedport_";	//Prefix für anzulegende Variablenprofile
$call_sort = SORT_DESC;										//Sortier-Reihenfolge für Anruflisten. SORT_DESC => neueste zuerst, SORT_ASC => älteste zuerst.

//Intervall in Minuten in dem eine Firmware-Updateprüfung erfolgen soll
//(aufwändige Funktion; nicht so oft durchführen. Bsp.: 1 mal im Monat => ca. 43200 Minuten)
$fw_update_interval = 43200 /*[Objekt #43200 existiert nicht]*/;

//Speicherort für zu erstellende Speedport Variablen.
//Standard: Variablen werden unterhalb dieses Scripts abgelegt. (Linux User bitte weiter unten lesen!)
//
//Achtung: Wenn das geändert wird, erstellt das Script unterhalb dieser Id diesem Ort alle Variablen neu
//sofern diese nicht bereits da hin "verschoben" wurden. Bereits existierende Variablen an einem anderen
//Ort bleiben bestehen und müssten dann manuell gelöscht werden.
//Nochmal Achtung: Linux-Versionen (u.a. auf Banana Pi) scheinen in der aktuellen Version nicht mit der
//Variable $_IPS['SELF'] zurecht zu kommen. Für diese Benutzer muss die ScriptId manuell gesetzt werden!
$parentId = $_IPS['SELF'];

if($parentId == NULL)
	throw new Exception("Die Variable parentId darf nicht leer sein. Das automatische Auslesen der ScriptId ist fehlgeschlagen, bitte setze eine manuelle parentId");

/**
	* AB HIER MUSS NICHTS MEHR GEÄNDERT WERDEN
**/

class SpeedportVariableProfile{
	public $name;										//Name des Variablenprofils
	public $type;										//Datentyp des Profils (bool, int, float, string)
	public $prefix;									//Prefix
	public $suffix;									//Suffix
	public $assoc;									//Wert-Associations und Formatierung
	public $debug = false;

	//IPS Datentypen
	const tBOOL				= 0;
	const tINT				= 1;
	const tFLOAT			= 2;
	const tSTRING			= 3;

	public function __construct($name, $type, $prefix = "", $suffix = "", $assoc = NULL){
		$this->name = $name;
		$this->type = $type;
		$this->prefix = $prefix;
		$this->suffix = $suffix;
		$this->assoc = $assoc;

		$this->create($name, $type, $prefix, $suffix, $assoc);
	}

	private function create($name, $type, $prefix = "", $suffix = "", $assoc = NULL){
		if($type != self::tBOOL && $type != self::tINT && $type != self::tFLOAT && $type != self::tSTRING)
			throw new Exception("method createVariableProfile does not support profiles of type $type!");
		if(!IPS_VariableProfileExists($name)){
			if($this->debug) echo "INFO - VariablenProfil $name existiert nicht und wird jetzt neu angelegt\n";
			IPS_CreateVariableProfile($name, $type);
			IPS_SetVariableProfileText($name, $prefix, $suffix);
			if(isset($assoc)){
				foreach($assoc as $a){
					if($this->debug) echo "INFO - VariablenProfilAssociation für Variable $name und Wert ". $a["name"]." existiert nicht und wird jetzt neu angelegt\n";
					IPS_SetVariableProfileAssociation($name, $a["val"], $a["name"], $a["icon"], $a["color"]);
				}
			}
		}
	}

	public function delete(){
		IPS_DeleteVariableProfile($this->name);
	}
}

class SpeedportVariable{
	protected $id;												//ID der Variable
	protected $name;											//Name der Variable
	protected $type;											//Datentyp der Variable (bool, int, float, string)
	protected $parent;										//ID des Elternelements
	protected $value;											//Wert der Variable
	protected $profile;										//Variablenprofil der Klasse SpeedportVariableProfile
	public $debug = false;								//aktiviere debug-informationen innerhalb dieser Klasse

	//IPS Datentypen
	const tBOOL				= 0;
	const tINT				= 1;
	const tFLOAT			= 2;
	const tSTRING			= 3;

	public function __construct($name, $type, $parent, $value = NULL, $profile = NULL){
		//if(isset($profile) && !($profile instanceof SpeedportVariableProfile))
		//	throw new Exception("Param profile must be an instance of SpeedPortVariableProfile!");

		$this->name = $name;
		$this->type = $type;
		$this->parent = $parent;
		$this->profile = $profile;
		$this->value = $value;

		$this->id = @IPS_GetVariableIDByName($name, $parent);
		if($this->id == false){
			if($this->debug) echo "INFO - create IPS variable $name\n";
			$this->id = IPS_CreateVariable($this->type);
			IPS_SetName($this->id, $name);
			IPS_SetParent($this->id, $parent);
			IPS_SetInfo($this->id, "this variable was created by script " . $_IPS['SELF']);
			if(isset($profile) && ($profile instanceof SpeedportVariableProfile)){
				IPS_SetVariableCustomProfile($this->id, $profile->name);
			}else if(isset($profile)){
				IPS_SetVariableCustomProfile($this->id, $profile);	//only a string profile name
			}
		}
	}

	public function set($value){
		if($this->type == self::tBOOL && !is_bool($value))
			throw new Exception("(Variable ". $this->name .")Param 'value' is not a boolean.");
		if($this->type == self::tINT && !is_int($value))
			throw new Exception("(Variable ". $this->name .")Param 'value' is not an integer.");
		if($this->type == self::tFLOAT && !is_float($value))
			throw new Exception("(Variable ". $this->name .")Param 'value' is not a float.");
		if($this->type == self::tSTRING && !is_string($value))
			throw new Exception("(Variable ". $this->name .")Param 'value' is not a string.");
		$this->value = $value;
		SetValue($this->id, $value);
	}

	public function getId(){
		return $this->id;
	}

	public function getValue(){
		return GetValue($this->id);
	}

	public function getName(){
		return $this->name;
	}

	public function getType(){
		return $this->type;
	}

	public function getParent(){
		return $this->parent;
	}

	public function getProfile(){
		return $this->profiles;
	}

	public function delete(){
		IPS_DeleteVariable($this->id);
	}
}

class SpeedportCall{
	public $id;
	public $date;
	public $time;
	public $number;
	public $duration;
	public $timestamp;

	public function __construct($date, $time, $number, $duration){
	   $this->date = $date;
	   $this->time = $time;
	   $this->number = $number;
	   $this->duration = $duration;
		$this->timestamp = strtotime("$date $time");
	   $this->id = 9 . str_replace(".", "", $this->date) . str_replace(":", "", $this->time);
		//		echo "$date $time: " . date('Y-m-d H:i:s', $this->timestamp) ."\n";
	}
}

/**
 * Generate a PBKDF2 key derivation of a supplied password
 *
 * This is a hash_pbkdf2() implementation for PHP versions 5.3 and 5.4.
 * @link http://www.php.net/manual/en/function.hash-pbkdf2.php
 *
 * @param string $algo
 * @param string $password
 * @param string $salt
 * @param int $iterations
 * @param int $length
 * @param bool $rawOutput
 *
 * @return string
 */
function compat_pbkdf2($algo, $password, $salt, $iterations, $length = 0, $rawOutput = false)
{
    // check for hashing algorithm
    if (!in_array(strtolower($algo), hash_algos())) {
        trigger_error(sprintf(
            '%s(): Unknown hashing algorithm: %s',
            __FUNCTION__, $algo
        ), E_USER_WARNING);
        return false;
    }

    // check for type of iterations and length
    foreach (array(4 => $iterations, 5 => $length) as $index => $value) {
        if (!is_numeric($value)) {
            trigger_error(sprintf(
                '%s() expects parameter %d to be long, %s given',
                __FUNCTION__, $index, gettype($value)
            ), E_USER_WARNING);
            return null;
        }
    }

    // check iterations
    $iterations = (int)$iterations;
    if ($iterations <= 0) {
        trigger_error(sprintf(
            '%s(): Iterations must be a positive integer: %d',
            __FUNCTION__, $iterations
        ), E_USER_WARNING);
        return false;
    }

    // check length
    $length = (int)$length;
    if ($length < 0) {
        trigger_error(sprintf(
            '%s(): Iterations must be greater than or equal to 0: %d',
            __FUNCTION__, $length
        ), E_USER_WARNING);
        return false;
    }

    // check salt
    if (strlen($salt) > PHP_INT_MAX - 4) {
        trigger_error(sprintf(
            '%s(): Supplied salt is too long, max of INT_MAX - 4 bytes: %d supplied',
            __FUNCTION__, strlen($salt)
        ), E_USER_WARNING);
        return false;
    }

    // initialize
    $derivedKey = '';
    $loops = 1;
    if ($length > 0) {
        $loops = (int)ceil($length / strlen(hash($algo, '', $rawOutput)));
    }

    // hash for each blocks
    for ($i = 1; $i <= $loops; $i++) {
        $digest = hash_hmac($algo, $salt . pack('N', $i), $password, true);
        $block = $digest;
        for ($j = 1; $j < $iterations; $j++) {
            $digest = hash_hmac($algo, $digest, $password, true);
            $block ^= $digest;
        }
        $derivedKey .= $block;
    }

    if (!$rawOutput) {
        $derivedKey = bin2hex($derivedKey);
    }

    if ($length > 0) {
        return substr($derivedKey, 0, $length);
    }

    return $derivedKey;
}

/**
 * @author      Jan Altensen (Stricted)
 * @license     GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @copyright   2015 Jan Altensen (Stricted)
 */
class speedport {
	/**
	 * password-challenge
	 * @var	string
	 */
	private $challenge = '';

	/**
	 * hashed password
	 * @var	string
	 */
	private $hash = '';

	/**
	 * session cookie
	 * @var	string
	 */
	private $session = '';

	/**
	 * router url
	 * @var	string
	 */
	private $url = '';

	/**
	 * derivedk cookie
	 * @var	string
	 */
	private $derivedk = '';

	public function __construct ($password, $url = 'http://speedport.ip/') {
		$this->url = $url;
		$this->getChallenge();

		if (empty($this->challenge)) {
			throw new Exception('unable to get the challenge from the router');
		}

		$login = $this->login($password);

		if ($login === false) {
			throw new Exception('unable to login');
		}
	}

	/**
	 * Requests the password-challenge from the router.
	 */
	public function getChallenge () {
		$path = 'data/Login.json';
		$fields = array('csrf_token' => 'nulltoken', 'showpw' => 0, 'challengev' => 'null');
		$data = $this->sentRequest($path, $fields);
		$data = json_decode($data['body'], true);
		if ($data[1]['varid'] == 'challengev') {
			$this->challenge = $data[1]['varvalue'];
		}
	}

	/**
	 * login into the router with the given password
	 *
	 * @param	string	$password
	 * @return	boolean
	 */
	public function login ($password) {
		$path = 'data/Login.json';
		$this->hash = hash('sha256', $this->challenge.':'.$password);
		$fields = array('csrf_token' => 'nulltoken', 'showpw' => 0, 'password' => $this->hash);
		$data = $this->sentRequest($path, $fields);
		$json = json_decode($data['body'], true);
		if ($json[15]['varid'] == 'login' && $json[15]['varvalue'] == 'success') {
			if (isset($data['header']['Set-Cookie']) && !empty($data['header']['Set-Cookie'])) {
				preg_match('/^.*(SessionID_R3=[a-z0-9]*).*/i', $data['header']['Set-Cookie'], $match);
				if (isset($match[1]) && !empty($match[1])) {
					$this->session = $match[1];
				}
				else {
					throw new Exception('unable to get the session cookie from the router');
				}

				// calculate derivedk
				if (version_compare(phpversion(), '5.5', '<')) {
				  // php version isn't high enough
				  // for php version < 5.5 we need our compatible pbkdf2 function
					$this->derivedk = compat_pbkdf2('sha1', hash('sha256', $password), substr($this->challenge, 0, 16), 1000, 32);
				}else{
					$this->derivedk = hash_pbkdf2('sha1', hash('sha256', $password), substr($this->challenge, 0, 16), 1000, 32);
				}

				return true;
			}
		}

		return false;
	}

	/**
	 * logout
	 *
	 * @return	array
	 */
	public function logout () {
		$path = 'data/Login.json';
		$fields = array('logout' => 'byby');
		$data = $this->sentRequest($path, $fields);
		// reset challenge and session
		$this->challenge = '';
		$this->session = '';

		$json = json_decode($data['body'], true);

		return $json;
	}

	/**
	 * reboot the router
	 *
	 * @return	array
	 */
	public function reboot () {
		$path = 'data/Reboot.json';
		$fields = array('csrf_token' => 'nulltoken', 'showpw' => 0, 'password' => $this->hash, 'reboot_device' => 'true');
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);
		$json = json_decode($data['body'], true);

		return $json;
	}

	/**
	 * change dsl connection status
	 *
	 * @param	string	$status
	 */
	public function changeConnectionStatus ($status) {
		$path = 'data/Connect.json';

		if ($status == 'online' || $status == 'offline') {
			$fields = array('csrf_token' => 'nulltoken', 'showpw' => 0, 'password' => $this->hash, 'req_connect' => $status);
			$cookie = 'challengev='.$this->challenge.'; '.$this->session;
			$this->sentRequest($path, $fields, $cookie);
		}
		else {
			throw new Exception();
		}
	}

	/**
	 * return the given json as array
	 *
	 * the following paths are known to be valid:
	 * /data/dsl.json
	 * /data/interfaces.json
	 * /data/arp.json
	 * /data/session.json
	 * /data/dhcp_client.json
	 * /data/dhcp_server.json
	 * /data/ipv6.json
	 * /data/dns.json
	 * /data/routing.json
	 * /data/igmp_proxy.json
	 * /data/igmp_snooping.json
	 * /data/wlan.json
	 * /data/module.json
	 * /data/memory.json
	 * /data/speed.json
	 * /data/webdav.json
	 * /data/bonding_client.json
	 * /data/bonding_tunnel.json
	 * /data/filterlist.json
	 * /data/bonding_tr181.json
	 * /data/letinfo.json
	 *
	 * /data/Status.json (No login needed)
	 *
	 * @param	string	$file
	 * @return	array
	 */
	public function getData ($file) {
		$path = 'data/'.$file.'.json';
		$fields = array();
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);

		if (empty($data['body'])) {
			throw new Exception('unable to get '.$file.' data');
		}

		$json = json_decode($data['body'], true);

		return $json;
	}

	/**
	 * get the router syslog
	 *
	 * @return	array
	 */
	public function getSyslog() {
		$path = 'data/Syslog.json';
		$fields = array('exporttype' => '0');
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);

		if (empty($data['body'])) {
			throw new Exception('unable to get syslog data');
		}

		return explode("\n", $data['body']);
	}

	/**
	 * get the Missed Calls from router
	 *
	 * @return	array
	 */
	public function getMissedCalls() {
		$path = 'data/ExportMissedCalls.json';
		$fields = array('exporttype' => '1');
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);

		if (empty($data['body'])) {
			throw new Exception('unable to get syslog data');
		}

		return explode("\n", $data['body']);
	}

	/**
	 * get the Taken Calls from router
	 *
	 * @return	array
	 */
	public function getTakenCalls() {
		$path = 'data/ExportTakenCalls.json';
		$fields = array('exporttype' => '2');
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);

		if (empty($data['body'])) {
			throw new Exception('unable to get syslog data');
		}

		return explode("\n", $data['body']);
	}

	/**
	 * get the Dialed Calls from router
	 *
	 * @return	array
	 */
	public function getDialedCalls() {
		$path = 'data/ExportDialedCalls.json';
		$fields = array('exporttype' => '3');
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);

		if (empty($data['body'])) {
			throw new Exception('unable to get syslog data');
		}

		return explode("\n", $data['body']);
	}

	/*
	// we cant encrypt and decrypt AES with mode CCM, we need AES with CCM mode for the commands
	// (stupid, all other data are send as plaintext and some 'normal' data are encrypted...)
	public function reconnectLte () {
		$path = 'data/modules.json';
		$fields = array('csrf_token' => 'nulltoken', 'showpw' => 0, 'password' => $this->hash, 'lte_reconn' => 'true');
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);
		$json = json_decode($data['body'], true);

		return $json;
	}
	*/
	/*
	public function resetToFactoryDefault () {
		$path = 'data/resetAllSetting.json';
		$fields = array('csrf_token' => 'nulltoken', 'showpw' => 0, 'password' => $this->hash, 'reset_all' => 'true');
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);
		$json = json_decode($data['body'], true);

		return $json;
	}
	*/

	/**
	 * check if firmware is actual
	 *
	 * @return	array
	 */
	public function checkFirmware () {
		$path = 'data/checkfirmware.json';
		$fields = array('checkfirmware' => 'true');
		$cookie = 'challengev='.$this->challenge.'; '.$this->session;
		$data = $this->sentRequest($path, $fields, $cookie);

		if (empty($data['body'])) {
			throw new Exception('unable to get checkfirmware data');
		}

		$json = json_decode($data['body'], true);

		return $json;
	}

	/**
	 * sends the request to router
	 *
	 * @param	string	$path
	 * @param	array	$fields
	 * @param	string	$cookie
	 * @return	array
	 */
	private function sentRequest ($path, $fields = array(), $cookie = '') {
		$url = $this->url.$path;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);

		if (!empty($fields)) {
			curl_setopt($ch, CURLOPT_POST, count($fields));
			curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
		}

		if (!empty($cookie)) {
			curl_setopt($ch, CURLOPT_COOKIE, $cookie);
		}

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HEADER, true);


		if ($cookie) {

		}

		$result = curl_exec($ch);

		$header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		$header = substr($result, 0, $header_size);
		$body = substr($result, $header_size);
		curl_close($ch);

		// fix invalid json
		$body = preg_replace("/(\r\n)|(\r)/", "\n", $body);
		$body = preg_replace('/\'/i', '"', $body);
		$body = preg_replace("/\[\s+\]/i", '[ {} ]', $body);
		$body = preg_replace("/},\s+]/", "}\n]", $body);

		return array('header' => $this->parse_headers($header), 'body' => $body);
	}

	/**
	 * parse the curl return header into an array
	 *
	 * @param	string	$response
	 * @return	array
	 */
	private function parse_headers($response) {
		$headers = array();
		$header_text = substr($response, 0, strpos($response, "\r\n\r\n"));

		foreach (explode("\r\n", $header_text) as $i => $line) {
			if ($i === 0) {
				$headers['http_code'] = $line;
			}
			else {
				list ($key, $value) = explode(': ', $line);
				$headers[$key] = $value;
			}
		}

		return $headers;
	}
}

class SpeedportHybrid extends speedport{

	protected $password;										//speedport router admin passwort
	protected $url;													//speedport router ip-adresse
	protected $debug;												//bei true werden per echo debuginformationen ausgegeben
	protected $variable_profile_prefix;			//Präfix für neu anzulegende Variablenprofile
	protected $parentId;										//ID des Parents in welchem die Variablen erstellt werden sollen

	protected $dsl_status;									//dsl status: online-offline
	protected $lte_enabled;									//lte aktiviert / deaktiviert
	protected $lte_signal;									//lte signalstärke (0-5 balken) -> wird jedoch umgewandelt auf 0 - 100% (20% je Balken)
	protected $dsl_downstream;							//dsl downstream in kbit/s
	protected $dsl_upstream;								//dsl upstream in kbit/s
	protected $wlan_ssid;										//wlan ssid
	protected $wlan_5ghz_ssid;							//wlan ssid 5ghz
	protected $wlan_enabled;								//wlan aktiviert / deaktiviert
	protected $wlan_5ghz_enabled;						//wlan 5ghz aktiviert / deaktiviert
	protected $firmware_version;						//firmware version
	protected $fw_update_interval;					//Intervall in Minutem in welchem Updateprüfungen durchgeführt werden sollen

	//die Werte des Routers zur Leitungsqualität sind durch 10 zu teilen, sonst ergeben Sie keinen Sinn :-)
	//also der Wert 54 entspricht 5.4 dB - das passt, ich habe nur ein 2000er DSL RAM :-(
	protected $snr_margin_upstream;					//SNR Margin upstream (schlecht: < 10dB, OK: 11-20dB, gut: 20-28dB, super: > 29dB);
	protected $snr_margin_downstream;				//SNR Margin downstream (schlecht: < 10dB, OK: 11-20dB, gut: 20-28dB, super: > 29dB)
	protected $line_attenuation_up;					//line attenuation upstream (super: < 20dB, sehr gut: 20-30dB, gut: 30-40dB, OK: 40-50dB, schlecht: > 50dB)
	protected $line_attenuation_down;				//line attenuation upstream (super: < 20dB, sehr gut: 20-30dB, gut: 30-40dB, OK: 40-50dB, schlecht: > 50dB)
	protected $crc_errors_upload;						//gesendete fehlerhafte, irreparable Datenpakete  (cyclic redundancy check)
	protected $hec_errors_upload;						//gesendete fehlerhafte aber korrigierte Datenpakete (header error correction)
	protected $fec_errors_upload;						//gesendete fehlerhafte aber korrigierte Datenpakete (forward error correction)
	protected $crc_errors_download;					//empfangene fehlerhafte, irreparable Datenpakete  (cyclic redundancy check)
	protected $hec_errors_download;					//empfangene fehlerhafte aber korrigierte Datenpakete (header error correction)
	protected $fec_errors_download;					//empfangene fehlerhafte aber korrigierte Datenpakete (forward error correction)
	protected $dsl_synchronisation;					//ist die DSL-Leitung synchronisiert?

	protected $lte_sim_card;								//Status der LTE SIM-Karte (OK, Nicht OK)
	//RSRP (Reference Signal Received Power)
	//LTE-Empfangsstärke / Signalstärke
	//Wertebereich von -140 dBm bis -50 dBm
	//sehr gut: -50 bis -65; gut: -65 bis -80; befriedigend: -80 bis -95; ausreichend: -95 bis -105; mangelhaft: -110 bis -125; ungenügend: -125 bis -140
	protected $lte_rsrp;
	//RSRQ (Reference	Signal Received Quality)
	//LTE-Signalqualität
	//Wertebereich von -3 dB bis -20 dB
	//sehr gut: -3 dB; gut: -4 bis -5 dB, befriedigend:	-6 bis -8 dB; ausreichend: -9 bis -11dB; mangelhaft: -12 bis -15dB; ungenügend: -16 bis -20dB
	protected $lte_rsrq;

	protected $tunnel_bonding;							//gibt an ob DSL und LTE Verbindungen gekoppelt sind
	protected $ipv4_address;								//aktuelle IP-Adresse im Internet

	protected $call_sort = SORT_DESC;				//Sortierrichtung der Anruflisten (nach Datum und Uhrzeit)=> SORT_DESC/SORT_ASC
	protected $dialed_calls = array();			//historie herausgegangener Gespräche / Anrufe (Array)
	protected $missed_calls = array();			//historie verpasster Anrufe (Array)
	protected $taken_calls = array();				//historie angenommener Anrufe (Array)

	protected $variable_profiles = array();	//speichert alle IPS Variablenprofile
	protected $variables = array();					//speichert alle IPS Variablen

	//IPS Datentypen
	const tBOOL				= 0;
	const tINT				= 1;
	const tFLOAT			= 2;
	const tSTRING			= 3;

	//Farbcodes, von "schlecht" nach "gut"
	const hColor1			= 0xFF0000;						//rot (schlecht)
	const hColor2			= 0xFF9D00;						//orange
	const hColor3			= 0xFFF700;						//gelb
	const hColor4			= 0x9DFF00;						//hellgrün
	const hColor5			= 0x46F700;						//grün
	const hColor6			= 0x46F700;						//grün

	public function __construct($password, $url = "http://speedport.ip", $debug = true, $variable_profile_prefix = "Speedport_", $call_sort = SORT_DESC, $parentId = "", $fw_update_interval = 43200 /*[Objekt #43200 existiert nicht]*/){
		parent::__construct($password, $url);
		if($parentId == "") $parentId = $_IPS['SELF'];
		$this->debug = $debug;
		$this->variable_profile_prefix = $variable_profile_prefix;
		$this->call_sort = $call_sort;
		$this->parentId = $parentId;
		$this->fw_update_interval = $fw_update_interval;
		$this->setup();
	}

	public function cleanup(){
		foreach($this->variable_profiles as $profile){
			if($this->debug) "INFO - deleting variable profile: ". $profile->name ."\n";
			$profile->delete();
		}

		foreach($this->variables as $variable){
			if($this->debug) "INFO - deleting variable: ". $variable->getName() ."\n";
			$variable->delete();
		}
	}

	private function setup(){
		//Prüfe ob Variablenprofile existieren und erstelle diese wenn nötig

		$assoc[0] = ["val"=>0,	"name"=>"Offline",	"icon" => "", "color" => self::hColor1];
		$assoc[1] = ["val"=>1,	"name"=>"Online",	"icon" => "", "color" => self::hColor6];
		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "DSL_Status", self::tBOOL, "", "", $assoc));
		unset($assoc);

		$assoc[0] = ["val"=>0,	"name"=>"Disabled",	"icon" => "", "color" => self::hColor1];
		$assoc[1] = ["val"=>1,	"name"=>"Enabled",	"icon" => "", "color" => self::hColor6];
		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "LTE_Enabled", self::tBOOL, "", "", $assoc));
		unset($assoc);

		$assoc[0] = ["val"=>0,	"name"=>"Veraltet",	"icon" => "", "color" => self::hColor1];
		$assoc[1] = ["val"=>1,	"name"=>"Aktuell",	"icon" => "", "color" => self::hColor6];
		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "Firmware_UpToDate", self::tBOOL, "", "", $assoc));
		unset($assoc);

		$assoc[0] = ["val"=>0,	"name"=>0,	"icon" => "", "color" => self::hColor1];
		$assoc[1] = ["val"=>1,	"name"=>20,	"icon" => "", "color" => self::hColor2];
		$assoc[2] = ["val"=>2,	"name"=>40,	"icon" => "", "color" => self::hColor3];
		$assoc[3] = ["val"=>3,	"name"=>60,	"icon" => "", "color" => self::hColor4];
		$assoc[4] = ["val"=>4,	"name"=>80,	"icon" => "", "color" => self::hColor5];
		$assoc[5] = ["val"=>5,	"name"=>100,	"icon" => "", "color" => self::hColor6];
		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "LTE_Signal", self::tINT, "", " %", $assoc));
		unset($assoc);

		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "Bandwidth", self::tFLOAT, "", " kbit/s"));

		$assoc[0] = ["val"=>0,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor1];
		$assoc[1] = ["val"=>11,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor3];
		$assoc[2] = ["val"=>20,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor4];
		$assoc[3] = ["val"=>28,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor5];
		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "SNR_Margin", self::tFLOAT, "", " dB", $assoc));
		unset($assoc);

		$assoc[0] = ["val"=>0,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor5];
		$assoc[1] = ["val"=>21,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor4];
		$assoc[2] = ["val"=>31,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor3];
		$assoc[3] = ["val"=>41,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor2];
		$assoc[4] = ["val"=>51,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor1];
		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "Line_Attenuation", self::tFLOAT, "", " dB", $assoc));
		unset($assoc);

		$assoc[0] = ["val"=>-140,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor1];
		$assoc[1] = ["val"=>-125,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor2];
		$assoc[2] = ["val"=>-105,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor3];
		$assoc[3] = ["val"=>-95,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor4];
		$assoc[4] = ["val"=>-80,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor5];
		$assoc[5] = ["val"=>-65,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor6];
		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "RSRP", self::tINT, "", " dBm", $assoc));
		unset($assoc);

		$assoc[0] = ["val"=>-20,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor1];
		$assoc[1] = ["val"=>-15,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor2];
		$assoc[2] = ["val"=>-11,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor3];
		$assoc[3] = ["val"=>-8,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor4];
		$assoc[4] = ["val"=>-5,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor5];
		$assoc[5] = ["val"=>-3,	"name"=>"%.1f",	"icon" => "", "color" => self::hColor6];
		array_push($this->variable_profiles, new SpeedportVariableProfile($this->variable_profile_prefix . "RSRQ", self::tINT, "", " dBm", $assoc));
		unset($assoc);

		//Erstelle IPS-Variablen wenn nötig
		array_push($this->variables, new SpeedportVariable("DSL_Status", self::tBOOL, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "DSL_Status")));
		array_push($this->variables, new SpeedportVariable("LTE_Enabled", self::tBOOL, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "LTE_Enabled")));
		array_push($this->variables, new SpeedportVariable("LTE_Signal", self::tINT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "LTE_Signal")));
		array_push($this->variables, new SpeedportVariable("DSL_Downstream", self::tFLOAT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "Bandwidth")));
		array_push($this->variables, new SpeedportVariable("DSL_Upstream", self::tFLOAT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "Bandwidth")));
		array_push($this->variables, new SpeedportVariable("WLAN_ssid", self::tSTRING, $this->parentId, NULL, NULL));
		array_push($this->variables, new SpeedportVariable("WLAN_5Ghz_ssid", self::tSTRING, $this->parentId, NULL, NULL));
		array_push($this->variables, new SpeedportVariable("WLAN_enabled", self::tBOOL, $this->parentId, NULL, "~Switch"));
		array_push($this->variables, new SpeedportVariable("WLAN_5Ghz_enabled", self::tBOOL, $this->parentId, NULL,  "~Switch"));
		array_push($this->variables, new SpeedportVariable("Firmware_Version", self::tSTRING, $this->parentId, NULL,  NULL));
		array_push($this->variables, new SpeedportVariable("SNR_Margin_Downstream", self::tFLOAT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "SNR_Margin")));
		array_push($this->variables, new SpeedportVariable("SNR_Margin_Upstream", self::tFLOAT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "SNR_Margin")));
		array_push($this->variables, new SpeedportVariable("Line_Attenuation_Upstream", self::tFLOAT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "Line_Attenuation")));
		array_push($this->variables, new SpeedportVariable("Line_Attenuation_Downstream", self::tFLOAT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "Line_Attenuation")));
		array_push($this->variables, new SpeedportVariable("CRC_errors_upload", self::tFLOAT, $this->parentId, NULL,  NULL));
		array_push($this->variables, new SpeedportVariable("HEC_errors_upload", self::tFLOAT, $this->parentId, NULL,  NULL));
		array_push($this->variables, new SpeedportVariable("FEC_errors_upload", self::tFLOAT, $this->parentId, NULL,  NULL));
		array_push($this->variables, new SpeedportVariable("CRC_errors_download", self::tFLOAT, $this->parentId, NULL,  NULL));
		array_push($this->variables, new SpeedportVariable("HEC_errors_download", self::tFLOAT, $this->parentId, NULL,  NULL));
		array_push($this->variables, new SpeedportVariable("FEC_errors_download", self::tFLOAT, $this->parentId, NULL,  NULL));
		array_push($this->variables, new SpeedportVariable("DSL_Synchronization", self::tBOOL, $this->parentId, NULL, "~Alert.Reversed"));
		array_push($this->variables, new SpeedportVariable("LTE_SIM_Card", self::tBOOL, $this->parentId, NULL, "~Alert.Reversed"));
		array_push($this->variables, new SpeedportVariable("RSRP", self::tINT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "RSRP")));
		array_push($this->variables, new SpeedportVariable("RSRQ", self::tINT, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "RSRQ")));
		array_push($this->variables, new SpeedportVariable("Tunnel_Bonding", self::tBOOL, $this->parentId, NULL, "~Alert.Reversed"));
		array_push($this->variables, new SpeedportVariable("IP_Address", self::tSTRING, $this->parentId, NULL, NULL));
		array_push($this->variables, new SpeedportVariable("Dialed_Calls", self::tSTRING, $this->parentId, NULL, "~HTMLBox"));
		array_push($this->variables, new SpeedportVariable("Missed_Calls", self::tSTRING, $this->parentId, NULL, "~HTMLBox"));
		array_push($this->variables, new SpeedportVariable("Taken_Calls", self::tSTRING, $this->parentId, NULL, "~HTMLBox"));
		array_push($this->variables, new SpeedportVariable("Firmware_UpToDate", self::tBOOL, $this->parentId, NULL, $this->getProfileByName($this->variable_profile_prefix . "Firmware_UpToDate")));

		//Sortiere Variablen
		$i=0;
		foreach($this->variables as $variable){
			IPS_SetPosition($variable->getId(), ++$i);
		}
	}

	private function getProfileByName($name){
		foreach($this->variable_profiles as $profile){
			if($profile->name == $name)
				return $profile;
		}
		return NULL;
	}

	private function getVariableByName($name){
		foreach($this->variables as $variable){
			if($variable->getName() == $name)
				return $variable;
		}
		return NULL;
	}

	public function update(){
		$data = $this->getData('status');

		if($data[15]["varvalue"] == "online"){															//in format "online/offline", out: true/false
			$this->dsl_status						= true;
		}else{
			$this->dsl_status						= false;
		}

		$this->lte_enabled						= (bool)$data[9]["varvalue"];
		$this->lte_signal							=	(int)$data[12]["varvalue"];					//0-5 Balken. Jeder entspricht 20%
		$this->dsl_downstream					= (float)$data[19]["varvalue"];
		$this->dsl_upstream						= (float)$data[20]["varvalue"];
		$this->wlan_ssid							= (string)$data[21]["varvalue"];
		$this->wlan_5ghz_ssid					= (string)$data[22]["varvalue"];
		$this->wlan_enabled						= (bool)$data[23]["varvalue"];
		$this->wlan_5ghz_enabled			= (bool)$data[24]["varvalue"];
		$this->firmware_version				= (string)$data[27]["varvalue"];

		$data = $this->getData('dsl');
		$this->snr_margin_upstream		= (float)$data["Line"]["uSNR"] / 10;
		$this->snr_margin_downstream	= (float)$data["Line"]["dSNR"] / 10;
		$this->line_attenuation_up		= (float)$data["Line"]["uLine"] / 10;
		$this->line_attenuation_down	= (float)$data["Line"]["dLine"] / 10;
		$this->crc_errors_upload			= (float)$data["Line"]["uCRC"];
		$this->hec_errors_upload			= (float)$data["Line"]["uHEC"];
		$this->fec_errors_upload			= (float)$data["Line"]["uFEC"];
		$this->crc_errors_download		= (float)$data["Line"]["dCRC"];
		$this->hec_errors_download		= (float)$data["Line"]["dHEC"];
		$this->fec_errors_download		= (float)$data["Line"]["dFEC"];

		if($data["Connection"]["state"] == "Up"){
			$this->dsl_synchronisation	= true;
		}else{
			$this->dsl_synchronisation	= false;
		}

		$data = $this->getData('lteinfo');
		if($data["card_status"] == "SIM OK"){
			$this->lte_sim_card					= true;
		}else{
			$this->lte_sim_card					= false;
		}

		$this->lte_rsrp								= (int)$data["rsrp"];
		$this->lte_rsrq								= (int)$data["rsrq"];

		$data = $this->getData('bonding_tunnel');
		if($data["bonding"] == "Up"){
			$this->tunnel_bonding				= true;
		}else{
			$this->tunnel_bonding				= false;
		}

		$this->ipv4_address						= (string)$data["ipv4"];

		//Schreibe Daten in IPS-Variablen
		$this->getVariableByName("DSL_Status")->set($this->dsl_status);
		$this->getVariableByName("LTE_Enabled")->set($this->lte_enabled);
		$this->getVariableByName("LTE_Signal")->set($this->lte_signal);
		$this->getVariableByName("DSL_Downstream")->set($this->dsl_downstream);
		$this->getVariableByName("DSL_Upstream")->set($this->dsl_upstream);
		$this->getVariableByName("WLAN_ssid")->set($this->wlan_ssid);
		$this->getVariableByName("WLAN_5Ghz_ssid")->set($this->wlan_5ghz_ssid);
		$this->getVariableByName("WLAN_enabled")->set($this->wlan_enabled);
		$this->getVariableByName("WLAN_5Ghz_enabled")->set($this->wlan_5ghz_enabled);
		$this->getVariableByName("Firmware_Version")->set($this->firmware_version);
		$this->getVariableByName("SNR_Margin_Downstream")->set($this->snr_margin_upstream);
		$this->getVariableByName("SNR_Margin_Upstream")->set($this->snr_margin_downstream);
		$this->getVariableByName("Line_Attenuation_Upstream")->set($this->line_attenuation_up);
		$this->getVariableByName("Line_Attenuation_Downstream")->set($this->line_attenuation_down);
		$this->getVariableByName("CRC_errors_upload")->set($this->crc_errors_upload);
		$this->getVariableByName("HEC_errors_upload")->set($this->hec_errors_upload);
		$this->getVariableByName("FEC_errors_upload")->set($this->fec_errors_upload);
		$this->getVariableByName("CRC_errors_download")->set($this->crc_errors_download);
		$this->getVariableByName("HEC_errors_download")->set($this->hec_errors_download);
		$this->getVariableByName("FEC_errors_download")->set($this->fec_errors_download);
		$this->getVariableByName("DSL_Synchronization")->set($this->dsl_synchronisation);
		$this->getVariableByName("LTE_SIM_Card")->set($this->lte_sim_card);
		$this->getVariableByName("RSRP")->set($this->lte_rsrp);
		$this->getVariableByName("RSRQ")->set($this->lte_rsrq);
		$this->getVariableByName("Tunnel_Bonding")->set($this->tunnel_bonding);
		$this->getVariableByName("IP_Address")->set($this->ipv4_address);

		$this->processCalls($this->dialed_calls, $this->getDialedCalls(), $this->getVariableByName("Dialed_Calls"));
		$this->processCalls($this->missed_calls, $this->getMissedCalls(), $this->getVariableByName("Missed_Calls"));
		$this->processCalls($this->taken_calls, $this->getTakenCalls(), $this->getVariableByName("Taken_Calls"));

		$this->firmwareUpdateCheck();
	}

	//diese Methode braucht einen Augenblick und sollte in einem angemessenen Interval abgefragt werden
	public function firmwareUpdateCheck(){
		$fw_update_variable = $this->getVariableByName("Firmware_UpToDate");
		$last_update = IPS_GetVariable($fw_update_variable->getId())["VariableUpdated"];
		$minutes = round((time() - $last_update) / 60);
		if($this->debug) echo "Last Firmware update check occured: " . date('d.m.Y H:i:s', $last_update) ." (now it's:" . date('d.m.Y H:i:s') . ") that was " . $minutes ." minutes ago\n";

		if($minutes > $this->fw_update_interval){
			if($this->debug) echo "Run firmwareUpdateCheck. Result: ";
			$fw = $this->checkFirmware();
			if($fw[1]["varvalue"]==1){
				if($this->debug) echo "no update available\n";
				$this->getVariableByName("Firmware_UpToDate")->set(true);
			}else{
				if($this->debug) echo "update necessary\n";
				$this->getVariableByName("Firmware_UpToDate")->set(false);
			}
		}
	}

	//gibt alle Variablen aus um sie extern weiterverarbeiten zu können
	public function getVariables(){
		return $this->variables;
	}

	private function SortCalls($order = SORT_ASC){
		if(count($this->dialed_calls) > 0){
			foreach($this->dialed_calls as $call => $node){
			   $timestamps[$call] = $node->timestamp;
			}
			array_multisort($timestamps, $order, $this->dialed_calls);
		}
		unset($timestamps);

		if(count($this->missed_calls) > 0){
			foreach($this->missed_calls as $call => $node){
			   $timestamps[$call] = $node->timestamp;
			}
			array_multisort($timestamps, $order, $this->missed_calls);
		}
		unset($timestamps);

		if(count($this->taken_calls) > 0){
			foreach($this->taken_calls as $call => $node){
			   $timestamps[$call] = $node->timestamp;
			}
			array_multisort($timestamps, $order, $this->taken_calls);
		}
	}

	private function addCall($list, $new_call){
		if(!($new_call instanceof SpeedportCall))
			throw new Exception("Param new_call must be an instance of SpeedportCall!");

		//Prüfe ob der Anruf bereits in der Liste erfasst ist. Falls ja, füge ihn nicht
		//nochmals hinzu.
		$found = false;
		foreach($list as $call){
			if($call->id == $new_call->id){
				$found = true;
			}
		}
		if(!$found){
			array_push($list, $new_call);
		}
		return $list;
	}

	public function processCalls(&$target_list, $calls, $variable){
		if(!($variable instanceof SpeedportVariable))
	   	throw new Exception("Param variable must be an instance of SpeedportVariable!");

		array_shift($calls);       											//entferne ersten Eintrag (Überschrift)
		foreach($calls as $new_call){
		  if($new_call != ""){                					//am Ende befindet sich manchmal ein Leer-Eintrag
				$t = explode(" ", $new_call);
				if(count($t) < 4) $t[3] = "00:00:00";       //missed calls haben keine Gesprächsdauer
				$target_list = $this->addCall($target_list, new SpeedportCall($t[0], $t[1], $t[2], $t[3]));
			}
		}

		$val = $variable->getValue();
		$doc = new DOMDocument();
		if($val != "") {
		   $doc->loadHTML($val);   											//lade existentes HTML aus IPS Variable

			$lines = $doc->getElementsByTagName('tr');		//jeder Eintrag ist ein einer eigenen Zeile (Zeile für Überschrift berücksichtigen!)
			$counter = -1; 																//starte bei -1 wegen der Überschrift
			foreach($lines as $line){
			   $counter++;
			   if($counter > 0){ 													//erste line ist Überschrift, ignorieren.
					$colcount=0;
					$temp = "";
					foreach($line->childNodes as $column){
						$temp[$colcount] = $column->nodeValue;
						$colcount++;
					}
					$target_list = $this->addCall($target_list, new SpeedportCall($temp[0], $temp[1], $temp[2], $temp[3]));
				}
  		}
		}

		$this->SortCalls($this->call_sort);  						//sortiere Anrufe
		$html = "<html><body><table><tr><td width='25%'>Datum</td><td width='20%'>Uhrzeit</td><td width='40%'>Nummer</td><td width='25%'>Dauer</td></tr>";
		foreach($target_list as $call){
		   $date = $call->date;
		   $time = $call->time;
		   $number = $call->number;
		   $duration = $call->duration;
		   $html .= "<tr><td>$date</td><td>$time</td><td>$number</td><td>$duration</td></tr>";
		}
		$html .= "</table></body></html>";
		$doc->loadHTML($html);
  	$val = $doc->saveHTML();
  	$variable->set($val);
	}
}


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