<?php 

class HostQuickFilterValidator extends AgaviStringValidator {
	
	protected $ip = false;
	protected $mask = false;
	public static $TYPES = array(
		"NAME" => 0,
		"IP_ADDR" => 1,
		"NETMASK" => 2
	);

	protected function validate() {
		if (!parent::validate()) {
			$this->throwError();
			return false;
		}	
		$query = $this->getData($this->getArgument());
		
		list($this->ip, $this->mask) = $this->cidrToIpMaskPair($query,self::$IPV6_FORMAT);

	
		switch($this->determineType($this->ip,$this->mask)) {
			case self::$TYPES["NAME"]:
				
				$query	= str_replace("*","%",$query);
				$this->export(array(
					"target" => "host_name",
					"operator" => 60,
					"value" => $query
				));
			
				break;
			case self::$TYPES["IP_ADDR"]:	
			
				$this->export(array(
					"target" => "host_address",
					"operator" => 50,
					"value" => $this->ip
				));
				break;

			// (host_address & $binmask) <= host_address (db) AND 
			// (host_address & $binmask | ~binmask) >= host_address (db)  
			case self::$TYPES["NETMASK"]:
				
				 $binmask = $this->createBinaryNetmask($this->mask);
				 $maskedIP = $this->ip & $binmask;
				 $cleared = $maskedIP | ~$binmask;
				 $this->export(array(
					"isGroup" => true,
					"operator" => "AND",	
					"filter" => array(
						array(
							"target" => "host_address",
							"operator" => 71,
							"value" => $maskedIP			
						), 
						array(
							"target" => "host_address",
							"operator" => 70,
							"value" => $cleared 
						
						)
					)
				));

				break;
			default:
				$this->export(false);
		}
		
		return true;
	}
	
	// determine type of request
	protected function determineType() {
		if ($this->ip === false) {
			return self::$TYPES["NAME"];		
		} else {
			if ($this->mask == 128) 
				return self::$TYPES["IP_ADDR"]; 
			else  
				return self::$TYPES["NETMASK"]; 
			
		}
		return self::$TYPES["NAME"];
	}
	
	public static $IPV6_FORMAT = 0x01;
	/**
	 * Parse and split an address in CIDR notation
	 *
	 * Valid params are:
	 * 192.168.0.0/24 - IPv4 Subnets
	 * 127.0.0.1 	  - Single IP addresses, will assume full bitmask if
none
	 * 	              is given (/32 for IPv4, /128 for IPv6)
	 *
	 * Of course also IPv6 notation is valid, such as:
	 * ::1
	 * ::ffff:192.168.0.0/96 
	 *
	 * The function will return an Array with the IP address in binary
form
	 * and an Integer netmask. If the given address is invalid, the
result
	 * will be array(false, false)
	 *
	 * @param string CIDR
	 * @return array
	 
	 */

	public static function cidrToIpMaskPair($cidr, $flags = null)
	{
		if (($pos = strpos($cidr, '/')) !== false) {
			$mask = substr($cidr, $pos + 1);
			if (! preg_match('~^\d{1,3}~', $mask)) {
				return array(false, false);
			}
			$mask = (int) $mask;
			
			$ip = @inet_pton(substr($cidr, 0, $pos));
			if ($ip !== false) {
				if ($mask > strlen($ip) * 8) {
					return array(false, false);
				}
			}
		} else {
			if (($ip = @inet_pton($cidr)) === false) {
				return array(false, false);
			} else {
				$mask = strlen($ip) * 8;
			}
		}
		if (strlen($ip) === 4 && $flags & self::$IPV6_FORMAT) {
			$ip = str_repeat(pack('C', 0), 10) . str_repeat(pack('C',
0xff), 2) . $ip;
			$mask += 96; 
		}

		return array($ip, $mask);
	}


	/**
	* Convert a bitmask to a binary mask
	* Will return an
	*
	* @param bitmask VLSM 
	* @return string
	*/
	public function createBinaryNetmask($bitmask, $length = 128)
	{
		$hmask = str_repeat('f', $bitmask >> 2);
		switch($bitmask & 3)
		{
			case 3: $hmask .= 'e'; break;
			case 2: $hmask .= 'c'; break;
			case 1: $hmask .= '8'; break;
		}
		$hmask = str_pad($hmask, $length >> 2, '0');
		$hmask = pack('H*', $hmask);
		return $hmask;
	}

}
