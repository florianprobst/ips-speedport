<?
/**
* Speedport Timer Event class
*
* This configures speedport timer events and manages them
* This is just a simple capsulation allowing recurring cyclic events every x seconds.
*
* !!Other features are not implemented yet!!
*
* @link https://github.com/florianprobst/ips-speedport project website
*
* @author Florian Probst <florian.probst@gmx.de>
*
* @license GNU
* GNU General Public License, version 3
*/

/**
* class SpeedportTimerEvent
*/
class SpeedportTimerEvent{
	
	/**
	* ips id of the event
	*
	* @var int
	* @access private
	*/
	protected $id;

	/**
	* name of the event
	*
	* @var string
	* @access private
	*/
	protected $name;

	/**
	* id of the events parent
	* this defines where the event will be created
	*
	* @var int
	* @access private
	*/
	protected $parentId;
	
	/**
	* event cycle in seconds
	* this defines in which interval this event occurs (currently only a simple second interval is supported!)
	*
	* @var int
	* @access private
	*/
	protected $cycle;
	
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
	public function __construct($parentId, $name, $cycle, $debug = false){		
		$this->parentId = $parentId;
		$this->name = $name;
		$this->cycle = $cycle;
		$this->debug = $debug;
		$this->id = @IPS_GetEventIDByName($this->name, $this->parentId);
		
		//check if event does already exist
		if($this->id == false){
			if($this->debug) echo "INFO - create IPS event $name\n";
			$this->id = IPS_CreateEvent(1);																//create trigger event and store id
			IPS_SetName($this->id, $this->name);													//set event name
			IPS_SetParent($this->id, $this->parentId);										//move event to parent (this will be called when trigger occurs)
			IPS_SetEventCyclic($this->id, 0, 1, 0, 0, 1, $cycle);					//every $cycle seconds
			IPS_SetInfo($this->id, "this event was created by script " . $_IPS['SELF'] . " which is part of the Speedport library");
			
			$this->activate();
		}
	}
	
	/**
	* getName
	*
	* @return $string event name
	* @access public
	*/
	public function getName(){
		return $this->name;
	}
	
	/**
	* activate
	* enables this event
	*
	* @access public
	*/
	public function activate(){
		$time = time();
		IPS_SetEventCyclicTimeFrom($this->id, intval(date('H', $time)), intval (date('i', $time)), intval(date('s', $time)));
		//IPS_SetEventCyclicTimeFrom($this->id, 16, 06, 32);
		return IPS_SetEventActive($this->id, true);
	}
	
	/**
	* disable
	* disables this event
	*
	* @access public
	*/
	public function disable(){
		return IPS_SetEventActive($this->id, false);
	}
	
	/**
	* delete
	* deletes this event
	*
	* @access public
	*/
	public function delete(){
		return IPS_DeleteEvent($this->id);
	}
}
?>