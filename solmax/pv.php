<?php
#get the local settings

require('config.php');

#leave these alone....
$logdata_file 			= $path_to_pv . 'problem_log.txt';
$pvdata_file 			= $path_to_pv . 'pvdata.txt';
$error_file 			= $path_to_pv . 'errors.txt';
$success_insert_file 	= $path_to_pv . 'success_inserts.txt';

#Initialize variables
$V_SM_RESULT_COMMSTATE = "OFFLINE";
$V_SM_RESULT_AC_POWER_NOW = 0;
$V_SM_RESULT_AC_VOLTAGE_NOW = 0;
$V_SM_RESULT_AC_CURRENT_NOW = 0;
$V_SM_RESULT_DC_VOLTAGE_NOW = 0;
$V_SM_RESULT_DC_CURRENT_NOW = 0;
$V_SM_RESULT_AC_FREQ_NOW = 0;
$V_SM_RESULT_POWER_INSTALLED = 0;
$V_SM_RESULT_OPHOURS = 0;
$V_SM_RESULT_STARTUPS = 0;
$V_SM_RESULT_ENERGY_TODAY = 0;
$V_SM_RESULT_ENERGY_YESTERDAY = 0;
$V_SM_RESULT_ENERGY_MONTH_THIS = 0;
$V_SM_RESULT_ENERGY_MONTH_LAST = 0;
$V_SM_RESULT_ENERGY_YEAR_THIS = 0;
$V_SM_RESULT_ENERGY_YEAR_LAST = 0;
$V_SM_RESULT_ENERGY_TOTAL = 0;
$V_SM_RESULT_PERCENT_LOAD_NOW = 0;
$V_SM_RESULT_TEMP_HEAT_SINK_NOW = 0;
$V_SM_RESULT_OPSTATE_TEXT = "";
$V_SM_RESULT_SOFTWARE_VERSION = "";

#When do we want to record this data?
#Between sunrise and sunset...

function get_gmt_offset()
{
	$ThisYear = (date("Y")); 
	$MarStartDate = ($ThisYear."-03-25"); 
	$OctStartDate = ($ThisYear."-10-25"); 
	$MarEndDate = ($ThisYear."-03-31"); 
	$OctEndDate = ($ThisYear."-10-31"); 
	while ($MarStartDate <= $MarEndDate) 
	{ 
		$day = date("l", strtotime($MarStartDate)); 
		if ($day == "Sunday"){ 
			$BSTStartDate = ($MarStartDate); 
		} 
		$MarStartDate++; 
	} 
	$BSTStartDate = (date("U", strtotime($BSTStartDate))+(60*60)); 
	while ($OctStartDate <= $OctEndDate) 
	{ 
		$day = date("l", strtotime($OctStartDate)); 
		if ($day == "Sunday"){ 
			$BSTEndDate = ($OctStartDate); 
		} 
		$OctStartDate++; 
	} 
	$BSTEndDate = (date("U", strtotime($BSTEndDate))+(60*60)); 
	$now = mktime(); 
	if (($now >= $BSTStartDate) && ($now <= $BSTEndDate)){ 
		return 1; 
	} 
	else
	{ 
		return 0; 
	} 
}

# Heavy trig maths follows; works out sunrise and sunset time based on your lat, lng, and offset from GMT
# I'm *guessing* this would work for the southern hemisphere too...

$spd = 86400;
$tz = get_gmt_offset();
$julian_day = cal_to_jd(CAL_GREGORIAN,date('m'),date('d'),date('Y'));
$julian_century = ($julian_day-2451545)/36525;
$geo_mean_long_sun = (280.46646+$julian_century*(36000.76983+$julian_century*0.0003032))%360;
$geo_mean_anom_sun = 357.52911+$julian_century*(35999.05029-0.0001537*$julian_century);
$eccent_earth_orbit= 0.016708634-$julian_century*(0.000042037+0.0000001267*$julian_century);
$Sun_Eq_of_Ctr=sin(deg2rad($geo_mean_anom_sun))*(1.914602-$julian_century*(0.004817+0.000014*$julian_century))+sin(deg2rad(2*$geo_mean_anom_sun))*(0.019993-0.000101*$julian_century)+sin(deg2rad(3*$geo_mean_anom_sun))*0.000289;
$Sun_True_Long=$geo_mean_long_sun+$Sun_Eq_of_Ctr;
$Sun_True_Anom=$geo_mean_anom_sun+$Sun_Eq_of_Ctr;
$Sun_Rad_Vector=(1.000001018*(1-$eccent_earth_orbit*$eccent_earth_orbit))/(1+$eccent_earth_orbit*cos(deg2rad($Sun_True_Anom)));
$Sun_App_Long=$Sun_True_Long-0.00569-0.00478*sin(deg2rad(125.04-1934.136*$julian_century));
$Mean_Obliq_Ecliptic=23+(26+((21.448-$julian_century*(46.815+$julian_century*(0.00059-$julian_century*0.001813))))/60)/60;
$Obliq_Corr=$Mean_Obliq_Ecliptic+0.00256*cos(deg2rad(125.04-1934.136*$julian_century));
$Sun_Rt_Ascen=90-rad2deg(atan2(cos(deg2rad($Sun_App_Long)),cos(deg2rad($Obliq_Corr))*sin(deg2rad($Sun_App_Long))));
$Sun_Declin=rad2deg(asin(sin(deg2rad($Obliq_Corr))*sin(deg2rad($Sun_App_Long))));
$var_y=tan(deg2rad($Obliq_Corr/2))*tan(deg2rad($Obliq_Corr/2));
$Eq_of_Time=4*rad2deg($var_y*sin(2*deg2rad($geo_mean_long_sun))-2*$eccent_earth_orbit*sin(deg2rad($geo_mean_anom_sun))+4*$eccent_earth_orbit*$var_y*sin(deg2rad($geo_mean_anom_sun))*cos(2*deg2rad($geo_mean_long_sun))-0.5*$var_y*$var_y*sin(4*deg2rad($geo_mean_long_sun))-1.25*$eccent_earth_orbit*$eccent_earth_orbit*sin(2*deg2rad($geo_mean_anom_sun)));
$HA_Sunrise=rad2deg(acos(cos(deg2rad(90.833))/(cos(deg2rad($lat))*cos(deg2rad($Sun_Declin)))-tan(deg2rad($lat))*tan(deg2rad($Sun_Declin))));
$Solar_Noon=(720-4*$lng-$Eq_of_Time+$tz*60)/1440;
$Sunrise_Time=$Solar_Noon-$HA_Sunrise*4/1440;
$Sunset_Time=$Solar_Noon+$HA_Sunrise*4/1440;

$Solar_Noon = $spd * $Solar_Noon;
$Solar_Noon = strtotime('midnight') + $Solar_Noon;
$Sunrise_Time = $spd * $Sunrise_Time;
$Sunrise_Time = strtotime('midnight') + $Sunrise_Time;
$Sunset_Time = $spd * $Sunset_Time;
$Sunset_Time = strtotime('midnight') + $Sunset_Time;
$current_time = strtotime('now');

# Start of code for capturing data...
$cmd = array(
	array( 'descr' => 'Address',                   'name' => 'ADR', 'convert' => function($i){ return hexdec($i); } ), # 0
	array( 'descr' => 'Type',                      'name' => 'TYP', 'convert' => function($i) { return "0x" . $i; } ), # 1
	array( 'descr' => 'Software version',          'name' => 'SWV', 'convert' => function($i){ return sprintf("%1.1f", hexdec($i) / 10 ); } ), # 2
	array( 'descr' => 'Date day',                  'name' => 'DDY', 'convert' => function($i){ return hexdec($i); } ), # 3
	array( 'descr' => 'Date month',                'name' => 'DMT', 'convert' => function($i){ return hexdec($i); } ), # 4
	array( 'descr' => 'Date year',                 'name' => 'DYR', 'convert' => function($i){ return hexdec($i); } ), # 5
	array( 'descr' => 'Time hours',                'name' => 'THR', 'convert' => function($i){ return hexdec($i); } ), # 6
	array( 'descr' => 'Time minutes',              'name' => 'TMI', 'convert' => function($i){ return hexdec($i); } ), # 7
	array( 'descr' => '???Error 1, number???',     'name' => 'E11', 'convert' => function($i){ return hexdec($i); } ), # 8
	array( 'descr' => '???Error 1, day???',        'name' => 'E1D', 'convert' => function($i){ return hexdec($i); } ), # 9
	array( 'descr' => '???Error 1, month???',      'name' => 'E1M', 'convert' => function($i){ return hexdec($i); } ), # 10
	array( 'descr' => '???Error 1, hour???',       'name' => 'E1h', 'convert' => function($i){ return hexdec($i); } ), # 11
	array( 'descr' => '???Error 1, minute???',     'name' => 'E1m', 'convert' => function($i){ return hexdec($i); } ), # 12
	array( 'descr' => '???Error 2, number???',     'name' => 'E21', 'convert' => function($i){ return hexdec($i); } ), # 13
	array( 'descr' => '???Error 2, day???',        'name' => 'E2D', 'convert' => function($i){ return hexdec($i); } ), # 14
	array( 'descr' => '???Error 2, month???',      'name' => 'E2M', 'convert' => function($i){ return hexdec($i); } ), # 15
	array( 'descr' => '???Error 2, hour???',       'name' => 'E2h', 'convert' => function($i){ return hexdec($i); } ), # 16
	array( 'descr' => '???Error 2, minute???',     'name' => 'E2m', 'convert' => function($i){ return hexdec($i); } ), # 17
	array( 'descr' => '???Error 3, number???',     'name' => 'E31', 'convert' => function($i){ return hexdec($i); } ), # 18
	array( 'descr' => '???Error 3, day???',        'name' => 'E3D', 'convert' => function($i){ return hexdec($i); } ), # 19
	array( 'descr' => '???Error 3, month???',      'name' => 'E3M', 'convert' => function($i){ return hexdec($i); } ), # 20
	array( 'descr' => '???Error 3, hour???',       'name' => 'E3h', 'convert' => function($i){ return hexdec($i); } ), # 21
	array( 'descr' => '???Error 3, minute???',     'name' => 'E3m', 'convert' => function($i){ return hexdec($i); } ), # 22
	array( 'descr' => 'Operating hours',           'name' => 'KHR', 'convert' => function($i){ return hexdec($i); } ), # 23
	array( 'descr' => 'Energy today [Wh]',         'name' => 'KDY', 'convert' => function($i){ return (hexdec($i) * 100); } ), # 24
	array( 'descr' => 'Energy yesterday [kWh]',    'name' => 'KLD', 'convert' => function($i){ return (hexdec($i) * 100); } ), # 25
	array( 'descr' => 'Energy this month [kWh]',   'name' => 'KMT', 'convert' => function($i){ return hexdec($i); } ), # 26
	array( 'descr' => 'Energy last monh [kWh]',    'name' => 'KLM', 'convert' => function($i){ return hexdec($i); } ), # 27
	array( 'descr' => 'Energy this year [kWh]',    'name' => 'KYR', 'convert' => function($i){ return hexdec($i); } ), # 28
	array( 'descr' => 'Energy last year [kWh]',    'name' => 'KLY', 'convert' => function($i){ return hexdec($i); } ), # 29
	array( 'descr' => 'Energy total [kWh]',        'name' => 'KT0', 'convert' => function($i){ return hexdec($i); } ), # 30
	array( 'descr' => 'Language',                  'name' => 'LAN', 'convert' => function($i){ return hexdec($i); } ), # 31
	array( 'descr' => 'DC voltage [mV]',           'name' => 'UDC', 'convert' => function($i){ return (hexdec($i) * 100); } ), # 32
	array( 'descr' => 'AC voltage [mV]',           'name' => 'UL1', 'convert' => function($i){ return (hexdec($i) * 100); } ), # 33
	array( 'descr' => 'DC current [mA]',           'name' => 'IDC', 'convert' => function($i){ return (hexdec($i) * 10); } ), # 34
	array( 'descr' => 'AC current [mA]',           'name' => 'IL1', 'convert' => function($i){ return (hexdec($i) * 10); } ), # 35
	array( 'descr' => 'AC power [mW]',             'name' => 'PAC', 'convert' => function($i){ return (hexdec($i) * 500); } ), # 36
	array( 'descr' => 'Power installed [mW]',      'name' => 'PIN', 'convert' => function($i){ return (hexdec($i) * 500); } ), # 37
	array( 'descr' => 'AC power [%]',              'name' => 'PRL', 'convert' => function($i){ return hexdec($i); } ), # 38
	array( 'descr' => 'Start ups',                 'name' => 'CAC', 'convert' => function($i){ return hexdec($i); } ), # 39
	array( 'descr' => '???',                       'name' => 'FRD', 'convert' => function($i){ return "0x" . $i; } ), # 40
	array( 'descr' => '???',                       'name' => 'SCD', 'convert' => function($i){ return "0x" . $i; } ), # 41
	array( 'descr' => '???',                       'name' => 'SE1', 'convert' => function($i){ return "0x" . $i; } ), # 42
	array( 'descr' => '???',                       'name' => 'SE2', 'convert' => function($i){ return "0x" . $i; } ), # 43
	array( 'descr' => '???',                       'name' => 'SPR', 'convert' => function($i){ return "0x" . $i; } ), # 44
	array( 'descr' => 'Temerature Heat Sink',      'name' => 'TKK', 'convert' => function($i){ return hexdec($i); } ), # 45
	array( 'descr' => 'AC Frequency',              'name' => 'TNF', 'convert' => function($i){ return (hexdec($i) / 100); } ), # 46
	array( 'descr' => 'Operation State',           'name' => 'SYS', 'convert' => function($i){ return hexdec($i); } ), # 47
	array( 'descr' => 'Build number',              'name' => 'BDN', 'convert' => function($i){ return hexdec($i); } ), # 48
	array( 'descr' => 'Error-Code(?) 00',          'name' => 'EC00', 'convert' => function($i){ return hexdec($i); } ), # 49
	array( 'descr' => 'Error-Code(?) 01',          'name' => 'EC01', 'convert' => function($i){ return hexdec($i); } ), # 50
	array( 'descr' => 'Error-Code(?) 02',          'name' => 'EC02', 'convert' => function($i){ return hexdec($i); } ), # 51
	array( 'descr' => 'Error-Code(?) 03',          'name' => 'EC03', 'convert' => function($i){ return hexdec($i); } ), # 52
	array( 'descr' => 'Error-Code(?) 04',          'name' => 'EC04', 'convert' => function($i){ return hexdec($i); } ), # 53
	array( 'descr' => 'Error-Code(?) 05',          'name' => 'EC05', 'convert' => function($i){ return hexdec($i); } ), # 54
	array( 'descr' => 'Error-Code(?) 06',          'name' => 'EC06', 'convert' => function($i){ return hexdec($i); } ), # 55
	array( 'descr' => 'Error-Code(?) 07',          'name' => 'EC07', 'convert' => function($i){ return hexdec($i); } ), # 56
	array( 'descr' => 'Error-Code(?) 08',          'name' => 'EC08', 'convert' => function($i){ return hexdec($i); } ), # 57
);

# Operating Modes...
# For next 'release'...
# Literal translation from German to English
$OPMODES = array(
	array( 'mode' => '20001,0',		'desc' => '20001,0'),				# 0
	array( 'mode' => '20002,0',		'desc' => 'Insufficient exposure'),	# 1
	array( 'mode' => '20003,0',		'desc' => 'Approach'), 				# 2
	array( 'mode' => '20004,0',		'desc' => 'MPP operation'), 		# 3
	array( 'mode' => '20005,0',		'desc' => '20005,0'),				# 4
	array( 'mode' => '20006,0',		'desc' => '20006,0'),				# 5
	array( 'mode' => '20007,0',		'desc' => '20007,0'),				# 6
	array( 'mode' => '20008,0',		'desc' => 'Network operation'),		# 7
	array( 'mode' => '20009,0',		'desc' => '20009,0'),				# 8
);

function checksum16 ($msg)
{
	# calculates the checksum 16 of the given string argument
	$bytes = unpack("C*", $msg);
	$sum = 0;
	foreach($bytes as $b)
	{
		$sum += $b;
		$sum = $sum % pow(2,16);
	}
	return $sum;
}

function mkmsg ($dst,$questions)
{
	# makes a message with the items in the given array as questions
	$src = 'FB';
	$dst = sprintf('%02X', $dst);
	$len = '00';
	$cs = '0000';
	$msg = is_array($questions) ? "64:" . implode(';', $questions) : "64:" . $questions;
	$len = strlen("{" . $src . ";" . $dst . ";" . $len . "|" . $msg . "|" . $cs . "}");
	$len = sprintf("%02X", $len);
	$cs = checksum16($src . ";" . $dst . ";" . $len . "|" . $msg . "|");	
	$cs = sprintf("%04X", $cs);
	return "{" . $src . ";" . $dst . ";" . $len . "|" . $msg . "|" . $cs . "}";
}

function getsmparam ($P_HANDLE, $P_TIMEOUT, $P_DEVADDR, $P_COMMAND) {

   	$V_MSG = mkmsg($P_DEVADDR, $P_COMMAND['name']);
	$V_RV = fwrite($P_HANDLE, $V_MSG);
	if(!$V_RV) die("Write error: $!");
	# Reading first 9 bytes
	$V_MSG = fread($P_HANDLE, 9);
	
	if(!preg_match("/([0-9A-F]{2});FB;([0-9A-F]{2})/",$V_MSG,$matches))
    {
        flush();
        fclose($P_HANDLE);
        die("Invalid response from header");
    }

	if($matches[1] != $P_DEVADDR)
    {
        flush();
        fclose($P_HANDLE);
        die("wrong source address: {$matches[1]} != $P_DEVADDR");
    }
	$V_LEN = hexdec($matches[2]);
	$V_LEN -= 9; # header is already in
	$V_MSG = fread($P_HANDLE, $V_LEN);

	#Logic required here to separately test OPSTATES and return that value
	if(!preg_match('/^\|64:(\w{3})=([0-9A-F]+)\|([0-9A-F]{4})}$/',$V_MSG,$matches))
    {
        flush();
        fclose($P_HANDLE);
        die("invalid response");
    }

	if($matches[1]!=$P_COMMAND['name'])
    {
        flush();
        fclose($P_HANDLE);
        die("wrong response");
    }

    $retval = $P_COMMAND['convert']($matches[2]);
	return $retval;
}

function ping($host,$port,$timeout=3)
{
    $fsock = fsockopen($host, $port, $errno, $errstr, $timeout);
    if (!$fsock)
    {
        return 0;
    }
    else
    {
        return $fsock;
    }
}

function curl_data($data, $curl_url)
{
	$session = curl_init();
	curl_setopt($session, CURLOPT_URL, $curl_url);
	curl_setopt($session, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($session, CURLOPT_POST, true);
	curl_setopt($session, CURLOPT_POSTFIELDS, $data);
	curl_setopt($session, CURLOPT_HEADER, false);
	$response = curl_exec($session);
	$info = curl_getinfo($session);
	curl_close($session);
	return $response;
}

#
# main()
#

error_reporting(0);

#only try and record any data between the hours of sunrise and sunset
if($current_time>=$Sunrise_Time && $current_time<=$Sunset_Time)
{
	# First PING Device to check whether it is online or not...
	$V_SOCK = ping($V_SM_IPADDR,$V_SM_IPPORT);
	 
	if ($V_SOCK){
	    #debug("We have a connection");
	    # Device is responding...
	    $V_SM_RESULT_COMMSTATE = "ONLINE";
	
	    # Connect to Device and get some Data...
	    # The following commands are working fine on SolarMax 6000S with Firmware 1.5.2066 over Ethernet...
	
		# Get PAC/[W]...
		$V_SM_RESULT_AC_POWER_NOW = getsmparam($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[36])/1000;
		# Get UL1/[V]...
		$V_SM_RESULT_AC_VOLTAGE_NOW = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[33])/1000;
		# Get IL1/[A]...
		$V_SM_RESULT_AC_CURRENT_NOW = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[35])/1000;
		# Get UDC/[V]...
		$V_SM_RESULT_DC_VOLTAGE_NOW = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[32])/1000;
		# Get IDC/[A]...
		$V_SM_RESULT_DC_CURRENT_NOW = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[34])/1000;
		# Get PIN/[W]...
		$V_SM_RESULT_POWER_INSTALLED = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[37])/1000;
		# Get TNF...
		$V_SM_RESULT_AC_FREQ_NOW = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[46]);
		# Get KHR/[h]...
		$V_SM_RESULT_OPHOURS = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[23]);
		# Get CAC/[h]...
		$V_SM_RESULT_STARTUPS = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[39]);
		# Get KDY/[kWh]...
		$V_SM_RESULT_ENERGY_TODAY = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[24]);
		# Get KLD/[kWh]...
		$V_SM_RESULT_ENERGY_YESTERDAY = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[25])/1000;
		# Get KMT/[kWh]...
		$V_SM_RESULT_ENERGY_MONTH_THIS = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[26]);
		# Get KLM/[kWh]...
		$V_SM_RESULT_ENERGY_MONTH_LAST = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[27]);
		# Get KYR/[kWh]...
		$V_SM_RESULT_ENERGY_YEAR_THIS = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[28]);
		# Get KLY/[kWh]...
		$V_SM_RESULT_ENERGY_YEAR_LAST = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[29]);
		# Get PRL/[%]...
		$V_SM_RESULT_PERCENT_LOAD_NOW = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[38]);
		# Get KTO/[kWh]...
		$V_SM_RESULT_ENERGY_TOTAL = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[30]);
		# Get TKK...
		$V_SM_RESULT_TEMP_HEAT_SINK_NOW = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[45]);
		# Get SYS (Operating-State)...
		#NOT YET OPERATIONAL AND WILL CAUSE ERROR (Data returned not in a format that is catered for, and is encoded so pretty meaningless)
		#$V_SM_RESULT_OPSTATE_TEXT = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[47]);
		# Get SWV/BDN (Software-Version + Build-Number)...
		$V_SM_RESULT_SOFTWARE_VERSION = getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[2]).'.'.getsmparam ($V_SOCK, $V_SM_COMM_TIMEOUT, $V_SM_DEVICE_ADDR, $cmd[48]);
	}
	else
	{
		# Communication failure...
		$V_SM_RESULT_COMMSTATE = "COMMFAIL";
		$V_SM_RESULT_AC_POWER_NOW = 0;
		$V_SM_RESULT_AC_VOLTAGE_NOW = 0;
		$V_SM_RESULT_AC_CURRENT_NOW = 0;
		$V_SM_RESULT_DC_VOLTAGE_NOW = 0;
		$V_SM_RESULT_DC_CURRENT_NOW = 0;
		$V_SM_RESULT_AC_FREQ_NOW = 0;
		$V_SM_RESULT_POWER_INSTALLED = 0;
		$V_SM_RESULT_OPHOURS = 0;
		$V_SM_RESULT_STARTUPS = 0;
		$V_SM_RESULT_ENERGY_TODAY = 0;
		$V_SM_RESULT_ENERGY_YESTERDAY = 0;
		$V_SM_RESULT_ENERGY_MONTH_THIS = 0;
		$V_SM_RESULT_ENERGY_MONTH_LAST = 0;
		$V_SM_RESULT_ENERGY_YEAR_THIS = 0;
		$V_SM_RESULT_ENERGY_YEAR_LAST = 0;
		$V_SM_RESULT_ENERGY_TOTAL = 0;
		$V_SM_RESULT_PERCENT_LOAD_NOW = 0;
		$V_SM_RESULT_TEMP_HEAT_SINK_NOW = 0;
		$V_SM_RESULT_OPSTATE_TEXT = "";
		$V_SM_RESULT_SOFTWARE_VERSION = "";
		
	}
	
	    # Un-comment for local testing of communication
		/*
		print "COMMUNICATION...............: $V_SM_RESULT_COMMSTATE\n";
	    print "AC Power now.........[Watt].: $V_SM_RESULT_AC_POWER_NOW\n";
	    print "Load now................[%].: $V_SM_RESULT_PERCENT_LOAD_NOW\n";
	    print "AC Voltage now.......[Volt].: $V_SM_RESULT_AC_VOLTAGE_NOW\n";
	    print "AC Current now.......[Amps].: $V_SM_RESULT_AC_CURRENT_NOW\n";
	    print "DC Voltage now.......[Volt].: $V_SM_RESULT_DC_VOLTAGE_NOW\n";
	    print "DC Current now.......[Amps].: $V_SM_RESULT_DC_CURRENT_NOW\n";
	    print "Power installed......[Watt].: $V_SM_RESULT_POWER_INSTALLED\n";
	    print "AC Frequency now.......[Hz].: $V_SM_RESULT_AC_FREQ_NOW\n";
	    print "Operating Hours.........[h].: $V_SM_RESULT_OPHOURS\n";
	    print "Startups....................: $V_SM_RESULT_STARTUPS\n";
	    print "Energy today...........[Wh].: $V_SM_RESULT_ENERGY_TODAY\n";
	    print "Energy yesterday.......[Wh].: $V_SM_RESULT_ENERGY_YESTERDAY\n";
	    print "Energy this Month.....[kWh].: $V_SM_RESULT_ENERGY_MONTH_THIS\n";
	    print "Energy last Month.....[kWh].: $V_SM_RESULT_ENERGY_MONTH_LAST\n";
	    print "Energy this Year......[kWh].: $V_SM_RESULT_ENERGY_YEAR_THIS\n";
	    print "Energy last Year......[kWh].: $V_SM_RESULT_ENERGY_YEAR_LAST\n";
	    print "Energy total..........[kWh].: $V_SM_RESULT_ENERGY_TOTAL\n";
	    print "Temperature Heat Sink..[°C].: $V_SM_RESULT_TEMP_HEAT_SINK_NOW\n";
	    print "Operational State...........: $V_SM_RESULT_OPSTATE_TEXT\n";
	    print "Software-Version............: $V_SM_RESULT_SOFTWARE_VERSION\n";
		*/
	
	flush();
	fclose($V_SOCK);
	
	$data = array(
		"timestamp" => time(),
		"HUMAN_TIME" => date('Y-m-d H:i:s'),
		"USERNAME" => $USERNAME, 
		"COMMSTATE" => $V_SM_RESULT_COMMSTATE,
		"AC_POWER_NOW" => $V_SM_RESULT_AC_POWER_NOW,
		"AC_VOLTAGE_NOW" => $V_SM_RESULT_AC_VOLTAGE_NOW,
		"AC_CURRENT_NOW" => $V_SM_RESULT_AC_CURRENT_NOW,
		"DC_VOLTAGE_NOW" => $V_SM_RESULT_DC_VOLTAGE_NOW,
		"DC_CURRENT_NOW" => $V_SM_RESULT_DC_CURRENT_NOW,
		"AC_FREQ_NOW" => $V_SM_RESULT_AC_FREQ_NOW,
		"POWER_INSTALLED" => $V_SM_RESULT_POWER_INSTALLED,
		"OPHOURS" => $V_SM_RESULT_OPHOURS,
		"STARTUPS" => $V_SM_RESULT_STARTUPS,
		"ENERGY_TODAY" => $V_SM_RESULT_ENERGY_TODAY,
		"ENERGY_YESTERDAY" => $V_SM_RESULT_ENERGY_YESTERDAY,
		"ENERGY_MONTH_THIS" => $V_SM_RESULT_ENERGY_MONTH_THIS,
		"ENERGY_MONTH_LAST" => $V_SM_RESULT_ENERGY_MONTH_LAST,
		"ENERGY_YEAR_THIS" => $V_SM_RESULT_ENERGY_YEAR_THIS,
		"ENERGY_YEAR_LAST" => $V_SM_RESULT_ENERGY_YEAR_LAST,
		"ENERGY_TOTAL" => $V_SM_RESULT_ENERGY_TOTAL,
		"PERCENT_LOAD_NOW" => $V_SM_RESULT_PERCENT_LOAD_NOW,
		"TEMP_HEAT_SINK_NOW" => $V_SM_RESULT_TEMP_HEAT_SINK_NOW,
		"OPSTATE_TEXT" => $V_SM_RESULT_OPSTATE_TEXT,
		"SOFTWARE_VERSION" => $V_SM_RESULT_SOFTWARE_VERSION,
		"HUMAN_SUNRISE" => date('Y-m-d H:i:s',$Sunrise_Time),
		"HUMAN_SUNSET" => date('Y-m-d H:i:s',$Sunset_Time),
		"HUMAN_NOON" => date('Y-m-d H:i:s',$Solar_Noon),
		"UNIX_SUNRISE" => $Sunrise_Time,
		"UNIX_SUNSET" => $Sunset_Time,
		"UNIX_NOON" => $Solar_Noon
		);
			
	if($V_SM_RESULT_COMMSTATE=='ONLINE' && $V_SM_RESULT_AC_POWER_NOW<50)
	{
		$current_count = 0;
		#houston, we have a problem.  Log and monitor and email if more than three in a row.

		if (file_exists($logdata_file)) 
		{
			$count = file($logdata_file);
			$current_count = $count[0];
		}
		
		$current_count++;
		
		if($current_count>=3)
		{
			#then email me and let me know!
			$body = "Solar PV power output for system: $USERNAME has been recorded as 3 consecutive instances of < 50 Wh.\n\n$dashboard_url";
			$email_headers = "From: \"$USERNAME Solar PV\" <$email_from>";
			foreach($email_to_addresses as $email_to)
			{
				mail($email_to,'SOLAR PV ERROR',$body,$email_headers);
			}
			#reset the counter
		    $fp = fopen($logdata_file, "w");
		    fputs ($fp, 0);
		    fclose ($fp);
		}
		else
		{
			#just log the new increment
			$fp = fopen($logdata_file, "w");
			fputs ($fp, $current_count);
			fclose ($fp);
		} 
	}
	else 
    {
    	#we are getting power again, so zero the log
        $fp = fopen($logdata_file, "w");
        fputs ($fp, 0);
        fclose ($fp);
    }


	$response = curl_data($data, $curl_url);
	
	$response = json_decode($response,true); 
	
	#Check $response and if fail, save array to local file as a JSON string
	#one JSON string per line
	#if success, then parse that file and write all lines to db, then empty file.

	if(!$response['liid'] || $response['liid']==0 || $response['liid']=='')
	{
		#format the array as a nice JSON string
		#was there an error code associated or just a null (no response) - either way, store for next try unless the error was 1062
		if($response['errno']!='1062')
		{
			$json = json_encode($data);
		    $fp = fopen($pvdata_file, 'a');
			fwrite($fp, $json . "\n");
			fclose($fp);
		}
	}
	else
	{
		#check to see if there is any data waiting to be written:
		if(file_exists($pvdata_file))
		{
			$handle = @fopen($pvdata_file, "r");
			#parse each line of the file
			if ($handle) {
				#get a line of data (json)
			    while (($data = fgets($handle, 4096)) !== false) {
			    	#increment count of number of lines to write
			    	$to_write++;
			        $write_data = json_decode($data,true);
			        #send the new curl request
			        $response = curl_data($write_data, $curl_url);
			        $response = json_decode($response,true);
			        #if we get a successful response - increment how many of these
			        if($response['liid']>0)
			        {
			        	$responses++;
			        }
			    }
			    fclose($handle);
			}
			#double check that the number of responses = number of lines of data waiting
			if($to_write==$responses)
			{
				unlink($pvdata_file);
			}
			else
			{
			    $fp = fopen($error_file, 'a');
				fwrite($fp, "to write:$to_write -> responses:$responses\n");
			}
		}
	    $fp = fopen($success_insert_file, 'a');
		fwrite($fp, "Time: " . date('Y-m-d H:i:s') . " :: Row ID->" . $response['liid'] . " :: Error (if any)->" . $response['errno'] . "\n");
		fclose($fp);
		unlink($pvdata_file); 
	}
}	

/* End of file */
