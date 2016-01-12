<?
/**
* SpeedportHybrid Script class
*
* This configures speedport automatically created scripts and manages them
*
* @link https://github.com/florianprobst/ips-speedport project website
*
* @author Florian Probst <florian.probst@gmx.de>
*
* @license GNU
* GNU General Public License, version 3
*/

/**
* class Speedport Script
*/
class SpeedportScript{
	
	/**
	* ips instance id of the script
	*
	* @var int
	* @access private
	*/
	protected $id;

	/**
	* name of the script
	*
	* @var string
	* @access private
	*/
	protected $name;

	/**
	* id of the scripts parent
	* this defines where the script will be created
	*
	* @var int
	* @access private
	*/
	protected $parentId;
	
	/**
	* debug information
	* enables debug information for this class
	*
	* @var boolean
	* @access private
	*/
	private $debug;
	
	/**
	* constructor
	*
	* @throws Exception if $type is not valid
	* @access public
	*/
	public function __construct($parentId, $name, $content, $debug = false){
		$this->parentId = $parentId;
		$this->name = $name;
		$this->content = $content;
		$this->debug = $debug;
		$this->id = @IPS_GetScriptIDByName($this->name, $this->parentId);
		
		//check if event does already exist
		if($this->id == false){
			if($this->debug) echo "INFO - create IPS script $name\n";
			$this->id = IPS_CreateScript(0);
			IPS_SetName($this->id, $this->name);
			IPS_SetParent($this->id, $this->parentId);
			IPS_SetScriptContent($this->id, $this->content);
			IPS_SetInfo($this->id, "this script was created by script " . $_IPS['SELF'] . " which is part of the IPS-Speedport library");
		}
	}
	
	/**
	* getInstanceId
	*
	* @return integer scripts instance id
	* @access public
	*/
	public function getInstanceId(){
		return $this->id;
	}
	
	/**
	* getName
	*
	* @return $string script name
	* @access public
	*/
	public function getName(){
		return $this->name;
	}
	
	/**
	* setScriptTimer
	* calls this script every $seconds while 0 disables it
	*
	* @param integer $seconds must be smaller 3600
	* @access public
	*/
	public function setScriptTimer($seconds)
	{
		if($seconds < 0 || $seconds >= 3600){
			throw new Exception("Parameter \$seconds is only valid with 0 - 3599");
		}
		return IPS_SetScriptTimer($this->id, $seconds);
	}
	
	/**
	* disable
	* disables this scripts timer
	*
	* @access public
	*/
	public function disableScriptTimer(){
		return IPS_SetScriptTimer($this->id, 0);
	}
	
	/**
	* delete
	* deletes this script
	*
	* @access public
	*/
	public function delete(){
		return IPS_DeleteScript($this->id, true);
	}
}
?>