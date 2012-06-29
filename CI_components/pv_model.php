<?php
class PV_model extends Model{
	
	function PV_model()
	{
		parent::Model();
	}
	
	function getData($d,$lat,$lng)
	{
		$data = Array();
		if($d)
		{
			$date_clause = $this->db->escape($d);
		}
		else
		{
			$date_clause = 'curdate()';
		}
		$sql = "SELECT HUMAN_TIME,AC_POWER_NOW,HUMAN_SUNRISE,HUMAN_NOON,HUMAN_SUNSET FROM your.table_name WHERE DATE_FORMAT(HUMAN_TIME,'%Y-%m-%d')=$date_clause AND USERNAME='YOU_SYSTEM_NAME' ORDER BY HUMAN_TIME ASC";
		$query = $this->db->query($sql);
		foreach ($query->result() as $row)
		{
			$solar_time = date('H:i:s',strtotime($row->HUMAN_TIME));
			$elevation = $this->solar_elevation($solar_time,$lat,$lng);
			$elevation = $elevation<0 ? 0 : $elevation * 70;
			$data[] = array(date('Y,m,d,H,i,s',strtotime($row->HUMAN_TIME)) => array($row->AC_POWER_NOW, $elevation));
		}
		return $data;	
	 }

	function getDates($d)
	{
		$sql = "SELECT DISTINCT(DATE_FORMAT(HUMAN_TIME,'%d/%m/%Y')) as nice_date,DATE_FORMAT(HUMAN_TIME,'%Y-%m-%d') as db_date FROM ggt.solar_pv WHERE USERNAME='SCOTSTON' ORDER BY HUMAN_TIME ASC";
		$query = $this->db->query($sql);
		foreach ($query->result() as $row)
		{
			if($d!='')
			{
				$selected = $row->db_date==$d ? ' selected' : '';
			}
			else
			{
				$selected = $row->nice_date==date('d/m/Y') ? ' selected' : '';
			}
		    $dates .= "<option value=\"" . $row->db_date . "\"$selected>" . $row->nice_date . "</option>";
		}
		return $dates;	
	 }
	 
	 function solar_elevation($t,$lat,$lng)
	 {
		$d = date('d');
		$m = date('m');
		$Y = date('Y');
		$bits = explode(':',$t);
		$H = $bits[0];
		$i = $bits[1];
		$s = $bits[2];
		#echo"Time->$Y-$m-$d $H:$i:$s\n";

		$st = $H/24 + ($i/60/24) + ($s/60/60/24);
		$tz = $this->get_gmt_offset();
		#echo $tz;
		$julian_day = cal_to_jd(CAL_GREGORIAN,$m,$d,$Y)+($st)-$tz/24;
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
		$Sunlight_Duration=8*$HA_Sunrise;
		$True_Solar_Time=($st*1440+$Eq_of_Time+4*$lng-60*$tz) % 1440;
		$Hour_Angle=($True_Solar_Time/4<0) ? $True_Solar_Time/4+180 : $True_Solar_Time/4-180;
		$Solar_Zenith_Angle=rad2deg(acos(sin(deg2rad($lat))*sin(deg2rad($Sun_Declin))+cos(deg2rad($lat))*cos(deg2rad($Sun_Declin))*cos(deg2rad($Hour_Angle))));
		$Solar_Elevation_Angle=90-$Solar_Zenith_Angle;
		if($Solar_Elevation_Angle>85)
		{
			$Approx_Atmospheric_Refraction=0;
		}
		elseif($Solar_Elevation_Angle>5)
		{
			$Approx_Atmospheric_Refraction=58.1/tan(deg2rad($Solar_Elevation_Angle))-0.07/(tan(deg2rad($Solar_Elevation_Angle))^3)+0.000086/(tan(deg2rad($Solar_Elevation_Angle))^5);
		}
		elseif($Solar_Elevation_Angle>-0.575)
		{
			$Approx_Atmospheric_Refraction=1735+$Solar_Elevation_Angle*(-518.2+$Solar_Elevation_Angle*(103.4+$Solar_Elevation_Angle*(-12.79+$Solar_Elevation_Angle*0.711)));
		}
		else
		{
			$Approx_Atmospheric_Refraction=-20.772/tan(deg2rad($Solar_Elevation_Angle));
		}
		$Approx_Atmospheric_Refraction=$Approx_Atmospheric_Refraction/3600;
		$Solar_Elevation_corrected_for_atm_refraction=$Solar_Elevation_Angle+$Approx_Atmospheric_Refraction;
		$Solar_Azimuth_Angle=($Hour_Angle>0) ? rad2deg(acos(((sin(deg2rad($lat))*cos(deg2rad($Solar_Zenith_Angle)))-sin(deg2rad($Sun_Declin)))/(cos(deg2rad($lat))*sin(deg2rad($Solar_Zenith_Angle)))))+180 % 360 : 540-rad2deg(acos(((sin(deg2rad($lat))*cos(deg2rad($Solar_Zenith_Angle)))-sin(deg2rad($Sun_Declin)))/(cos(deg2rad($lat))*sin(deg2rad($Solar_Zenith_Angle))))) % 360;
		
		
		$spd = 86400;
		$Solar_Noon = $spd * $Solar_Noon;
		$Solar_Noon = strtotime("$Y-$m-$d 00:00:01") + $Solar_Noon;
		
		$Sunrise_Time = $spd * $Sunrise_Time;
		$Sunrise_Time = strtotime("$Y-$m-$d 00:00:01") + $Sunrise_Time;
		
		$Sunset_Time = $spd * $Sunset_Time;
		$Sunset_Time = strtotime("$Y-$m-$d 00:00:01") + $Sunset_Time;
		
		$current_time = strtotime('now');

        /*echo "TZ:$tz\n";
		echo "julian_day:          $julian_day\n";
		echo "julian_century:      $julian_century\n";
		echo "geo_mean_long_sun:   $geo_mean_long_sun\n";
		echo "geo_mean_anom_sun:   $geo_mean_anom_sun\n";
		echo "eccent_earth_orbit:  $eccent_earth_orbit\n";
		echo "Sun_Eq_of_Ctr:       $Sun_Eq_of_Ctr\n";
		echo "Sun_True_Long:       $Sun_True_Long\n";
		echo "Sun_True_Anom:       $Sun_True_Anom\n";
		echo "Sun_Rad_Vector:      $Sun_Rad_Vector\n";
		echo "Sun_App_Long:        $Sun_App_Long\n";
		echo "Mean_Obliq_Ecliptic: $Mean_Obliq_Ecliptic\n";
		echo "Obliq_Corr:          $Obliq_Corr\n";
		echo "Sun_Rt_Ascen:        $Sun_Rt_Ascen\n";
		echo "Sun_Declin:          $Sun_Declin\n";
		echo "var_y:               $var_y\n";
		echo "Eq_of_Time:          $Eq_of_Time\n";
		echo "HA_Sunrise:          $HA_Sunrise\n";
		echo "Human Solar_Noon:    " . date ('Y-m-d H:i:s',$Solar_Noon) . "\n";
		echo "Human Sunrise_Time:  " . date ('Y-m-d H:i:s',$Sunrise_Time) . "\n";
		echo "Human Sunset_Time:   " . date ('Y-m-d H:i:s',$Sunset_Time) . "\n";
		echo "UNIX Solar_Noon:     $Solar_Noon\n";
		echo "UNIX Sunrise_Time:   $Sunrise_Time\n";
		echo "UNIX Sunset_Time:    $Sunset_Time\n";
		echo "Sunlight_Duration:   $Sunlight_Duration\n";
		echo "True_Solar_Time:     $True_Solar_Time\n";
		echo "Hour_Angle:          $Hour_Angle\n";
		echo "Solar_Zenith_Angle:  $Solar_Zenith_Angle\n";
		echo "Solar_Elevation_Angle:$Solar_Elevation_Angle\n";
		echo "Approx_Atmospheric_Refraction:$Approx_Atmospheric_Refraction\n";
		echo "Solar_Elevation_corrected_for_atm_refraction:$Solar_Elevation_corrected_for_atm_refraction\n";
		echo "Solar_Azimuth_Angle: $Solar_Azimuth_Angle\n";*/

		return $Solar_Elevation_corrected_for_atm_refraction;
	}
	 
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
}
/* Ends PV_model.php */