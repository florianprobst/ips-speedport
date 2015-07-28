<?
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
?>