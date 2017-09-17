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
require_once("functionlib.php");

$link = dbms_connect($dbhost, $dbuser, $dbpass, $dbname);

date_default_timezone_set('America/Chicago');

$date_a = array(date("Y"), date("m"), date("d"));
$date = implode("-", $date_a);
$time_a = array(date("H"), date("i"), date("s"));
$time = implode(":", $time_a);
$datetime = $date . " " . $time;
/*?
$query = "SELECT notify,address,autoupdateip FROM universal;";
$result = dbms_query($query);
$line = dbms_fetch_array($result, "ASSOC");
dbms_free_result($result);
$notification = $line['notify'];
$address = $line['address'];
$autoupdateip = $line['autoupdateip'];

//db check, upgrade
$query = "SELECT version FROM universal;";
$result = dbms_query($query);
$line = dbms_fetch_array($result, "NUM");
dbms_free_result($result);
$oldversion = $line[0];

//if different, upgrade, notify

if($version>$oldversion){
  limph_db_upgrade($oldversion, $version);
  
  $sub = "Limph DB upgraded from $oldversion to $version";
  $message = "<html>";
  $from = "-f" . $address;
  
  $message .= "<h2>" . $sub . "</h2>";
  
  $headers = "From: $address\r\n";
  $headers .= "Reply-to: $address\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
  
  mail($address, $sub, $message, $headers, $from);
  
 };

?*/

//hourly report
if((date("i")=="00")&&($notification=="1")){
  $sub = "Limph Hourly Report";
  $message = "<html>";
  
  $hour = date("H");
  if($hour>0){
    $hour--;
  } else {
    $hour = "23";
  };
  
  $query = "SELECT COUNT(number) FROM hosts WHERE status = '0' and enabled = '1';";
  $result = dbms_query($query);
  $down = dbms_fetch_array($result, "NUM");
  dbms_free_result($result);
  
  $query = "SELECT COUNT(number) FROM hosts WHERE status = '1' and enabled = '1';";
  $result = dbms_query($query);
  $up = dbms_fetch_array($result, "NUM");
  dbms_free_result($result);
  
  $message .= "<h2>{$down['0']} Down, {$up['0']} Up</h2>";
  
  $newtime_a = array($hour, date("i"), date("s"));
  $newtime = implode(":", $newtime_a);
  $newdatetime = $date . " " . $newtime;
  
  $count = "0";
  $query2 = "SELECT datetime,type,hostname FROM history WHERE datetime >= '$newdatetime' ORDER BY datetime ASC;";
  $result2 = dbms_query($query2);
  while($line = dbms_fetch_array($result2, "NUM")){
    $message .= "<h3>{$line['2']} {$line['1']} at {$line['0']}</h3><br />";
    $count++;
  };
  dbms_free_result($result2);
  
  if($count=="0"){
    $message .= "<h2>No host transitions</h2>";
  };
  
  $from = "-f" . $address;
  
  $headers = "From: $address\r\n";
  $headers .= "Reply-to: $address\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
  
  $query = "SELECT email FROM users WHERE digest = '1';";
  $result = dbms_query($query);
  while($email = dbms_fetch_array($result, "NUM")){
    mail($email[0], $sub, $message, $headers, $from);
  };
  dbms_free_result($result);
  
 };

//host checks
$query = "SELECT ip,timeout,status,number,name,type,port,parent,autoupdateip FROM hosts WHERE enabled = '1' AND parent= '0' ORDER BY name;";
$result = dbms_query($query);

$hosts = array();
while($line = dbms_fetch_array($result, "NUM")){
  array_push($hosts, $line);
 };

dbms_free_result($result);

dbms_close($link);

function chunk_check($hosts, $parent){
  global $datetime, $notification, $address, $autoupdateip;
  global $dbhost, $dbuser, $dbpass, $dbname;

  $chunks = array_chunk($hosts, 15);
  $chunk_count = count($chunks);
  
  while($chunk_count>0){
    $pid = pcntl_fork();
    if(!$pid){
      //here
      $link = dbms_connect($dbhost, $dbuser, $dbpass, $dbname);
      foreach($chunks[$chunk_count-1] as $line){
	$status = host_check($line[5], $line[0], $line[1], $line[6]);
	//if host is down
	if($status=="0"){

          //check for ip change
          $newip = dbms_escape_string(gethostbyname($line[4]));
          if($newip!=$line[0]){
            $ipchange = 1;
            if($autoupdateip=="1" and $line['8']=="1"){
              $query = "UPDATE hosts SET ip='$newip' WHERE number = '{$line['3']}';";
              $result = dbms_query($query);
            };
          }else{$ipchange = 0;};

	  //if host was up
	  if($line[2]=="1"){
	    //record down
	    $query = "INSERT INTO history (hostname,host,type,datetime) VALUES ('{$line['4']}','{$line['3']}','Down','$datetime');";
	    $result1 = dbms_query($query);
	    
	    //change to down
	    $query = "UPDATE hosts SET status = '0' WHERE number = '{$line['3']}';";
	    $result1 = dbms_query($query);
	    
	    
	    //notify down
	    if($notification=="1"){
	      $sub = "Limph Host DOWN - {$line['4']}";
	      $message = "<html>";
	      $from = "-f" . $address;
	      
	      $message .= "<h2>" . $sub . "</h2>";
	      
	      if($ipchange=="1"){
		$message .= "<h3>IP address changed from {$line['0']} to $newip</h3>";
		if($autoupdateip=="1" and $line['8']=="1"){
		  $message .= "<h3>Automatically updated.</h3>";
		};
	      };
	      
	      $headers = "From: $address\r\n";
	      $headers .= "Reply-to: $address\r\n";
	      $headers .= "MIME-Version: 1.0\r\n";
	      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
	      
	      $query = "SELECT number,email FROM users WHERE notify = '1';";
	      $result = dbms_query($query);
	      while($notifuser = dbms_fetch_array($result, "NUM")){
		$query1 = "SELECT COUNT(number) FROM host_notif WHERE host = '{$line['3']}' AND user = '{$notifuser[0]}';";
		$result1 = dbms_query($query1);
		while($email = dbms_fetch_array($result1, "NUM")){
		  if($email[0]>'0'){
		    mail($notifuser[1], $sub, $message, $headers, $from);
		  };
		};
		dbms_free_result($result1);
	      };
	      dbms_free_result($result);
	    }; //end notification
	  }; //end if was up
	}; //end if down
	//if host is up
	if($status=="1"){
	  //if host was down
	  if($line[2]=="0"){
	    //record up
	    $query = "INSERT INTO history (hostname,host,type,datetime) VALUES ('{$line['4']}','{$line['3']}','Up','$datetime');";
	    $result1 = dbms_query($query);
	    
	    //change to up
	    $query = "UPDATE hosts SET status = '1' WHERE number = '{$line['3']}';";
	    $result1 = dbms_query($query);
	    //notify up
	    if($notification=="1"){
	      $sub = "Limph Host UP - {$line['4']}";
	      $message = "<html>";
	      $from = "-f" . $address;
	      
	      $message .= "<h2>" . $sub . "</h2>";
	      
	      $headers = "From: $address\r\n";
	      $headers .= "Reply-to: $address\r\n";
	      $headers .= "MIME-Version: 1.0\r\n";
	      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
	      
	      $query = "SELECT number,email FROM users WHERE notify = '1';";
	      $result = dbms_query($query);
	      while($notifuser = dbms_fetch_array($result, "NUM")){
		$query1 = "SELECT COUNT(number) FROM host_notif WHERE host = '{$line['3']}' AND user = '{$notifuser['0']}';";
		$result1 = dbms_query($query1);
		while($email = dbms_fetch_array($result1, "NUM")){
		  if($email[0]>'0'){
		    mail($notifuser[1], $sub, $message, $headers, $from);
		  };
		};
		dbms_free_result($result1);
	      };
	      dbms_free_result($result);
	    }; //end notification
	  }; //end if was down
	}; //end status=1
      }; //end foreach chunks
      dbms_close($link);
      exit;
    }; //end childpid
    $chunk_count--;
  }; //end while chunk count

  //Wait for all children to finish
  $status = null;
  pcntl_waitpid(-1, $status);

  $link = dbms_connect($dbhost, $dbuser, $dbpass, $dbname);

  //get up hosts and subcheck
  $query = "SELECT number FROM hosts WHERE parent = '$parent' AND status = '1' ORDER BY name;";
  $result = dbms_query($query);
  while($line = dbms_fetch_array($result, "NUM")){
    $query2 = "SELECT ip,timeout,status,number,name,type,port,parent,autoupdateip FROM hosts WHERE enabled = '1' AND parent = '{$line['0']}' ORDER BY name;";
    $result2 = dbms_query($query2);
    
    $hosts = array();
    while($line2 = dbms_fetch_array($result2, "NUM")){
      array_push($hosts, $line2);
    };
    dbms_free_result($result2);

    dbms_close($link);
    chunk_check($hosts, $line[0]);
    $link = dbms_connect($dbhost, $dbuser, $dbpass, $dbname);
    
  };
  dbms_free_result($result);
  dbms_close($link);

}; //end of chunk_check

chunk_check($hosts, 0);

$link = dbms_connect($dbhost, $dbuser, $dbpass, $dbname);

//set children of up hosts to visible
function visible($host){
  $query4 = "UPDATE hosts SET visible = '1' WHERE number = '$host';";
  $result4 = dbms_query($query4);
  
  $query = "SELECT number FROM hosts WHERE parent = '$host';";
  $result = dbms_query($query);
  while($line = dbms_fetch_array($result, "NUM")){
    visible($line[0]);
  };
  dbms_free_result($result);
};

$query3 = "SELECT number FROM hosts WHERE status = '1';";
$result3 = dbms_query($query3);
while($line = dbms_fetch_array($result3, "NUM")){
  visible($line[0]);
 };
dbms_free_result($result3);

//set children of down hosts to not visible
function invisible($host){
  $query4 = "UPDATE hosts SET visible = '0' WHERE number = '$host';";
  $result4 = dbms_query($query4);
  
  $query = "SELECT number FROM hosts WHERE parent = '$host';";
  $result = dbms_query($query);
  while($line = dbms_fetch_array($result, "NUM")){
    invisible($line[0]);
  };
  dbms_free_result($result);
};


$query3 = "SELECT number FROM hosts WHERE status = '0';";
$result3 = dbms_query($query3);
while($line = dbms_fetch_array($result3, "NUM")){
  invisible($line[0]);
 };
dbms_free_result($result3);

//State/threshold checks/notifications
if($notification=="1"){
  $query = "SELECT name FROM hosts;";
  $result = dbms_query($query);
  $hosts = dbms_fetch_array($result, "ASSOC");
  dbms_free_result($result);
  foreach($hosts as $hostname){
    $query = "SELECT DISTINCT disk FROM state WHERE hostname = '$hostname' AND type = 'diskfree';";
    $result5 = dbms_query($query);
    while($disks = dbms_fetch_array($result5, "ASSOC")){
      $query6 = "SELECT data FROM state WHERE type = 'diskfree' AND hostname = '$hostname' AND disk = '{$disks['disk']}' ORDER BY datetime DESC LIMIT 1;";
      $result6 = dbms_query($query6);
      $current_free = dbms_fetch_array($result6, "ASSOC");
      dbms_free_result($result6);
      if(disk_thresh_check($hostname, $disks['disk'], $current_free['data'])==1){ 
	$sub = "Limph Host Warning - $hostname disk {$disks['disk']} below threshold";
	$message = "<html>";
	$from = "-f" . $address;

	if($current_free['data']>=1000000000){
	  $x = round(($current_free['data']/1000000000), 2) . "GB";
	}elseif($current_free['data']>=1000000){
	  $x = round(($current_free['data']/1000000), 2) . "MB";
	}elseif($current_free['data']>=1000){
	  $x = round(($current_free['data']/1000), 2) . "KB";
	}else{ $x = $current_free['data'] . " bytes";};
	$current_free['data']= $x;
	
	$message .= "<h2>" . $sub . "</h2>";
	$message .= "<h3>Disk " . $disks['disk'] . " " . $current_free['data'] . " free</h3>";
	
	$headers = "From: $address\r\n";
	$headers .= "Reply-to: $address\r\n";
	$headers .= "MIME-Version: 1.0\r\n";
	$headers .= "Content-type: text/html; charset=iso-8859-1\r\n";
	
	$query = "SELECT number,email FROM users WHERE notify = '1';";
	$result = dbms_query($query);
	while($notifuser = dbms_fetch_array($result, "NUM")){
	  $query1 = "SELECT COUNT(number) FROM host_notif WHERE host = '{$line['3']}' AND user = '{$notifuser['0']}';";
	  $result1 = dbms_query($query1);
	  while($email = dbms_fetch_array($result1, "NUM")){
	    if($email[0]>'0'){
	      mail($notifuser[1], $sub, $message, $headers, $from);
	    };
	  };
	  dbms_free_result($result1);
	};
	dbms_free_result($result);

      }; #Disk Threshold
    };
    dbms_free_result($result5);
    
    $query6 = "SELECT data FROM state WHERE type = 'sysload' AND hostname = '$hostname' ORDER BY datetime DESC LIMIT 1;";
    $result6 = dbms_query($query6);
    $current_load = dbms_fetch_array($result6, "ASSOC");
    dbms_free_result($result6);
    if(sysload_thresh_check($hostname, $current_load['data'])==1){ 
      //notify

      $values = explode("#", $current_load['data']);

      $sub = "Limph Host Warning - $hostname load above threshold";
      $message = "<html>";
      $from = "-f" . $address;
      
      $message .= "<h2>" . $sub . "</h2>";
      $message .= "<h3>1 Minute: " . $values[0] . " 5 Minutes: " . $values[1] . " 15 Minutes " . $values[2] . "</h3>";

      $headers = "From: $address\r\n";
      $headers .= "Reply-to: $address\r\n";
      $headers .= "MIME-Version: 1.0\r\n";
      $headers .= "Content-type: text/html; charset=iso-8859-1\r\n";

      $query = "SELECT number FROM users WHERE notify = '1';";
      $result = dbms_query($query);
      while($notifuser = dbms_fetch_array($result, "NUM")){
	$query1 = "SELECT COUNT(number) FROM host_notif WHERE host = '{$line['3']}' AND user = '{$notifuser['0']}';";
	$result1 = dbms_query($query1);
	while($email = dbms_fetch_array($result1, "NUM")){
	  if($email[0]>'0'){
	    mail($notifuser[1], $sub, $message, $headers, $from);
	  };
	};
	dbms_free_result($result1);
      };
      dbms_free_result($result);
    }; #Load Threshold
  };
 };

dbms_close($link);

?>
