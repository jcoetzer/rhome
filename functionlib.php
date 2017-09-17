<?php
/*
   Limph Is Monitoring Pingable Hosts
   Copyright (C) 2006 Jonathan Ciesla
   
   This program is free software; you can redistribute it and/or
   modify it under the terms of the GNU General Public License
   as published by the Free Software Foundation; either version 2
   of the License, or (at your option) any later version.
   
   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.
   
   You should have received a copy of the GNU General Public License
   along with this program; if not, write to the Free Software
   Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA
  */
require_once("config.php");
require_once "Net/Ping.php";

$version = "1.9.8.1";

function limph_key()
{
	echo "<table border=0 align=center>";
	echo "<tr><th>Key:</th><td bgcolor=#00FF00>Up</td><td bgcolor=#FFA500>Warning</td><td bgcolor=#FF0000>Down</td><td bgcolor=#FF00FF>Unreachable</td><td bgcolor=#808080>Disabled</td></tr>";
	echo "</table>";
}
  
function dbms_fetch_array($result, $type)
{
	if($type=="NUM"){$line = pg_fetch_array($result, NULL, PGSQL_NUM);};
	if($type=="ASSOC"){$line = pg_fetch_array($result, NULL, PGSQL_ASSOC);};
	if($type=="BOTH"){$line = pg_fetch_array($result, NULL, PGSQL_BOTH);};
	return $line;
}

function dbms_free_result($result)
{
	pg_free_result($result);
}
  
function dbms_query($query)
{  
	$result = pg_query($query);
	return $result;
}
  
function dbms_escape_string($string)
{
	$string = pg_escape_string($string);
	return $string;    
}

function dbms_close($link)
{
	pg_close($link);
}

function dbms_connect($dbhost, $dbuser, $dbpass, $dbname)
{
	$link = pg_connect("host=$dbhost dbname=$dbname user=$dbuser password=$dbpass");
	return $link;
}

function sysload_thresh_check($hostname, $value)
{
	$return = 0;
	$query2 = "SELECT value FROM thresholds WHERE hostname = '$hostname' AND type = 'sysload';";
	$result2 = dbms_query($query2);
	$line2 = dbms_fetch_array($result2, "ASSOC");
	dbms_free_result($result2);

	$thresh_bits = explode("#", $line2['value']);
	$data_bits = explode("#", $value);

	if($data_bits[0]>$thresh_bits[0]||$data_bits[1]>$thresh_bits[1]||$data_bits[2]>$thresh_bits[2])
	{
		$return=1;
	}

	return $return;
}

function disk_thresh_check($hostname, $disk, $value)
{
	$return = 0;
	$query2 = "SELECT value FROM thresholds WHERE hostname = '$hostname' AND disk = '$disk' AND type = 'diskfree';";
	$result2 = dbms_query($query2);
	$line2 = dbms_fetch_array($result2, "ASSOC");
	dbms_free_result($result2);

	if($value<$line2['value']){ $return = 1; };

	return $return;
}

function favicon()
{
	echo "<link rel=\"shortcut icon\" href=\"favicon.png\" type=\"image/x-icon\">";
}

function authentication($flag_me, $level)
{
	session_start();
	/*?
	if((!isset($_SESSION['limphstate']))||(!isset($_SESSION['limphid']))||($flag_me < $level))
	{
		echo"Try logging in. . .\n";
		global $siteaddress;
		echo "<meta http-equiv=refresh content=5;url=$siteaddress/index.php />";
		exit;
	}
	?*/
}

function udp_ping($host, $timeout)
{
	#Inspired by Simon Riget's 11/17/2004 post to php.net

	//open
	$sock=fsockopen('udp://'.$host, 7, $errno, $errstr, $timeout);
	if (!$sock)
	{
		//store errno
		$status = "0";
	} 
	else 
	{
		stream_set_timeout($sock, $timeout); 
		//send somthing
		$write=fwrite($sock, "AYBABTU\n");
		if(!$write)
		{
			//capture write error to db
			break;
		}
		//try to get something back
		fread($sock,8);
		$info = stream_get_meta_data($sock);
		if($info['timed_out'])
		{
			$status = "0";
		} 
		else 
		{
			$status = "1";
		}
		fclose($sock);
	}
	return $status;
}

function tcp_check($host, $timeout, $port)
{
	//open
	if($sock=fsockopen($host, $port, $errno, $errstr, $timeout))
	{
		$status = "1";
		fclose($sock);
	} 
	else 
	{
		$status = "0";
	}

	return $status;
}

function icmp_check($host)
{
	$ping = Net_Ping::factory();
	if (PEAR::isError($ping)) 
	{
		$status = "0";
	} 
	else 
	{
		$ping->setArgs(array('count' => 1, 'timeout' => 1));
		$status = $ping->ping($host)->_received;
	}

	return $status;
}

function host_check($type, $host, $timeout, $port)
{
	if($type=="udp")
	{
		$status = udp_ping($host, $timeout);
	}
	if($type=="tcp")
	{
		$status = tcp_check($host, $timeout, $port);
	}
	if($type=="icmp")
	{
		$status = icmp_check($host);
	}

	return $status;
}

function valid_date($fodder)
{
	$chunks = explode("-", $fodder);
	$answer = checkdate($chunks[1], $chunks[2], $chunks[0]);
	return $answer;
}

function parent_check($parent, $number)
{
	if($parent==$number){ return 0; }
	$orig = $parent;
	while($parent != 0)
	{
		$query = "SELECT parent FROM hosts WHERE number = '$parent';";
		$result = dbms_query($query);
		$line = dbms_fetch_array($result, "NUM");
		dbms_free_result($result);

		if($line[0]==$number){ return 0; };

		$parent = $line[0];
	}

	return $orig;
}

function button_image($text, $back, $length)
{
	$width = "20";

	#if($back=="grey"){$back_color=array(204, 204, 204);};
	#if($back=="white"){$back_color=array(255, 255, 255);};

	header("Content-type: image/png");
	#$im = @imagecreate($length, $width)
	#   or die("Cannot Initialize new GD image stream");
	$file = $back . "_" . $width . "x" . $length . "_bevel.png";
	$im = imagecreatefrompng($file);
	$blue = imagecolorallocate($im, 0, 0, 255);
	$black = imagecolorallocate($im, 0, 0, 0);

	#$background = imagecolorallocate($im, $back_color[0], $back_color[1], $back_color[2]);
	#imagefilledrectangle($im, 0, 0, $length-1, $width-1, $background);

	imagestring($im, 3, 5, 5, $text, $black);

	imagepng($im);
	imagedestroy($im);
}

?>
