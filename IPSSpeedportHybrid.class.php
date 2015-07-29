<?
require_once('speedport-hybrid-php-api/SpeedportHybrid.class.php');
require_once('lib/SpeedportVariableProfile.class.php');
require_once('lib/SpeedportVariable.class.php');
require_once('lib/SpeedportCall.class.php');

class IPSSpeedportHybrid extends SpeedportHybrid{

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

	protected $up_time;											//up time basierend auf "online" Zeit des LAN Adapters in Tagen

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
		parent::__construct($url);
		if($parentId == "") $parentId = $_IPS['SELF'];
		$this->debug = $debug;
		$this->variable_profile_prefix = $variable_profile_prefix;
		$this->call_sort = $call_sort;
		$this->parentId = $parentId;
		$this->fw_update_interval = $fw_update_interval;
		$this->setup();
		$this->login($password);
	}
	
	public function __destruct(){
		$this->logout();
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
		array_push($this->variables, new SpeedportVariable("UpTime", self::tINT, $this->parentId, NULL, NULL));

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
		
		echo "UPTIME " . $this->getUptime() . " \n";
		$this->up_time									= (int)$this->getUptime();

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
		$this->getVariableByName("UpTime")->set($this->up_time);

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

		/*
		mit der neuen api wurde das anruf-array verändert und ist je nach anruftyp unterschiedlich
		das array ist zu normalisieren.
		
		dialed calls: array(5) with index "id", "dialedcalls_date", "dialedcalls_time", "dialedcalls_who", "dialedcalls_duration"
		missed calls: array(4) with index "id", "missedcalls_date", "missedcalls_time", "missedcalls_who"
		taken calls: array(5) with index "id", "takencalls_date", "takencalls_time", "takencalls_who", "takencalls_duration"
		*/
		foreach($calls as $c){
			if(isset($c["dialedcalls_date"])){
					$t[0] = $c["dialedcalls_date"];
					$t[1] = $c["dialedcalls_time"];
					$t[2] = $c["dialedcalls_who"];
					$t[3] = $c["dialedcalls_duration"];
			}
			if(isset($c["missedcalls_date"])){
					$t[0] = $c["missedcalls_date"];
					$t[1] = $c["missedcalls_time"];
					$t[2] = $c["missedcalls_who"];
					$t[3] = "00:00:00";												//missed calls haben keine Gesprächsdauer
			}
			if(isset($c["takencalls_date"])){
					$t[0] = $c["takencalls_date"];
					$t[1] = $c["takencalls_time"];
					$t[2] = $c["takencalls_who"];
					$t[3] = $c["takencalls_duration"];
			}
			$target_list = $this->addCall($target_list, new SpeedportCall($t[0], $t[1], $t[2], $t[3]));
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

?>