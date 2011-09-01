<?php 

class Cronks_EventDB_EventDBHelperModel extends CronksBaseModel {
	public function getAddressFromBinary($bin) {
		$addr = inet_ntop($bin);
		if(preg_match("/f{4}:(\d{1,3})\./",$addr)) {
			$addr = explode(":",$addr);
		
			$addr = $addr[count($addr)-1];
		}
		
		return $addr;
	}
	public function resolveAddress($address) {
		$format = null;
		if(preg_match("/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/",$address))
			$format = "IPv4";
		else if(preg_match("/\d{0,4}(:{0,4}|::)+/",$address))
			$format = "IPv6";
		$d = array();
		switch($format) {
			case 'IPv4':
				$d = $this->resolveIPv4($address);
				break;
			case 'IPv6':
				$d = $this->resolveIPv6($address);
				break;
		}
		
		return $d; 
	}
	public function resolveIPv4($address) {
		$address = "::ffff:".$address;
		return inet_pton($address);
	}

	public function resolveIPv6($address) {
		return inet_pton($address);
	}
}
