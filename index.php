<?php
//kill when not accessed commandline
(PHP_SAPI !== 'cli' || isset($_SERVER['HTTP_USER_AGENT'])) && die('cli only');
// secure files
define ('SECURITY', 1);
// import the configuration definitions
include 'config.php';
$headers = [
 	'X-Auth-Email:' . X_AUTH_EMAIL,
   	'X-Auth-Key:' . X_AUTH_KEY,
	'Content-Type: application/json',
];
define ('HTTPHEADER',$headers);


//cloudflare api function
function cloudflare($queryString, $newIp) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $queryString);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	if (!empty($newIp)) {
    	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
    	curl_setopt($ch, CURLOPT_POSTFIELDS,$newIp);
    }
	curl_setopt($ch, CURLOPT_HTTPHEADER, HTTPHEADER);
	$answer = curl_exec ($ch);
	curl_close ($ch);
	return $answer;
}

// check current public IP against stored IP
// on match do not make the 'call' to cloudflare api
// get the server's public IP address
$currentip = file_get_contents('https://api.ipify.org');
#echo "The server's public IP address is: " . $currentip  .'</br>';
// get stored IP address
$file = __DIR__.'/stored.ip';
// Open the file to get existing content
$storedip = file_get_contents($file);

#echo $storedip;

if($storedip != $currentip) {

	//get the zone ID for DOMAIN
	$zone_entry = cloudflare("https://api.cloudflare.com/client/v4/zones/?name=".DOMAIN."&status=active&match=all",'');
	$obj = json_decode($zone_entry, true);
	$zoneId = $obj['result'][0]['id'];

	//get the DNS entry for the subdomains
	//check for existence $check
	//and match with public $currentip
	//change content (ip) and (modified_on)
	//to match API syntax
	//remove key "meta"
	//add empty key "data" 
	//correct array syntax [] for "data" to {}
	foreach (SUBDOMAINS as $subdomain) {
	$dns_entry = cloudflare("https://api.cloudflare.com/client/v4/zones/".$zoneId."/dns_records?type=A&name=".$subdomain."&match=all",'');
		$check = (json_decode($dns_entry, true))['result'];
		if (!empty($check)) {
			$obj = (json_decode($dns_entry, true))['result'][0];	
    		if ($obj['content'] != $currentip) {
    		unset($obj['meta']);
    		$newIP = array("content"=>$currentip, "modified_on"=>gmdate("Y-m-d\TH:i:s.u\Z"), "data"=>array());
   		 	$newIP = str_replace("[]","{}",json_encode(array_replace($obj,$newIP)));
    		$success =  cloudflare("https://api.cloudflare.com/client/v4/zones/".$zoneId."/dns_records/".$obj['id'], $newIP);
    	    openlog("CloudflareUpdateLog", LOG_PID, LOG_LOCAL0);
    	    syslog(LOG_INFO, $subdomain . " updated to ip: " .$currentip );
   		    closelog();
        	}
    	} 
	}
	// Write the contents back to the file
	file_put_contents($file, $currentip);
}
?>
