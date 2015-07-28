<?
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
?>