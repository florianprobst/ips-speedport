<?
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
?>