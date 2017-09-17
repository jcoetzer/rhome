<?php

function get_zb_code($rc)
{
	switch ($rc)
	{
		case 290:
		case 291:
		case 304:
		case 305:
		case 306:
		case 307:
		case 308:
		case 309:
		case 310:
			// ZBSTATUS_ALARM          
			return 1;
		case 273:
		case 277:
			// ZBSTATUS_FIRE           
			return 2;
		case 256:
		case 288:
		case 289:
			// ZBSTATUS_PANIC          
			return 3;
			/*?
			// ZBSTATUS_STAY           
			return 0xFF;
			// ZBSTATUS_ARMED          
			return 0x50;
			// ZBSTATUS_INIT           
			return 99;
			// ZBSTATUS_ARM_DAY        
			return 8;
			// ZBSTATUS_ARM_NITE       
			return 9;
			// ZBSTATUS_DISARMED       
			return 0x51;
			?*/
		default :
			// ZBSTATUS_UNKNOWN    
			return 0;
	}

}

date_default_timezone_set("Africa/Johannesburg");
echo strftime("%b %d %Y %H:%M:%S") . "\n";

$opts = getopt("i");

// Server in the this format: <computer>\<instance name> or 
// <server>,<port> when using a non default port number
$server = '10.255.181.252\RISCOIPRCV';

// Connect to MSSQL
$link = mssql_connect($server, 'test', 'password123');

if (!$link) 
{
        exit("Something went wrong while connecting to MSSQL\n");
}

//? $query = "select top 100 [id], [datetime], [group], [code], [identifiera] from ipreceiverng.dbo.ut_queue where [code]>0 and [code]<=304 and [group]!=4 order by [id] desc;";
$query = "select top 100 [id], [datetime], [group], [code], [identifiera] from ipreceiverng.dbo.ut_queue where [code]>0 and [code]<=310 and [group]=1 and [pid]=1 order by [id] desc;";
$result = mssql_query($query);
if ($result!=false) 
{
	$riscoi=array();
	$riscoc=array();
	$riscot=array();
	$riscog=array();
	while ( $record = mssql_fetch_array($result) )
	{
		$id = $record['id'];
		$code = $record['code'];
		$ip = $record['identifiera'];
		$dt = $record['datetime'];
		$grp = $record['group'];
		//? printf("%s %s %s %s %s\n", $id, $dt, $code, $ip, $grp);
		$riscoi[$id] = $ip;
		$riscoc[$id] = $code;
		$riscot[$id] = $dt;
		$riscog[$id] = $grp;
	}
}
else
{
	exit("MSSQL query failed\n");
}
// Close the link to MSSQL
mssql_close($link);

//? printf("Connect to PostgreSQL\n");
$conn = pg_pconnect("host=localhost user=defidev dbname=adomis password=p");
if (!$conn)  
{
        exit("Something went wrong while connecting to PostgreSQL\n");
}
foreach($riscoi as $id => $ip)
{
	$query = "select dt from risco r where r.id=" . $id;
	$result = pg_query($conn, $query);
	if  (!$result) {
		exit("Query $query did not execute");
	}
	elseif (pg_num_rows($result) == 0) 
	{
		$code = $riscoc[$id];
		$zcode = get_zb_code($code);
		echo "New alarm " . $id . " code " . $code . " from " . $ip . "\n";
		// Get description for code
		$query = "select unit, appt, descrptn from adomis_box, rcodes where modbip='" . $ip . "' and rcode=" . $code;
		$result = pg_query($conn, $query);
		if  (!$result) {
			echo "Query $query did not execute\n";
			continue;
		}
		if ($col_value = pg_fetch_array($result))
		{
			$descr = $col_value['descrptn'];
			$unitNum = $col_value['unit'];
			$name = $col_value['appt'];
			$zone = 1;
		}
		else
		{
			echo "IP address $ip unknown\n";
			continue;
		}
		// Update RISCO sync table
		//? $query = sprintf("insert into risco (id, dt, rcode, rgroup, modbpip) values (%d, to_timestamp('%s','Mon DD YYYY HH12:MI:SS:000PM'), %d, %d, '%s')",
		//? 		$id, $riscot[$id], $code, $riscog[$id], $ip);
		$query = sprintf("insert into risco (id, dt, rcode, rgroup, modbpip) values (%d, current_timestamp, %d, %d, '%s')",
				$id, $code, $riscog[$id], $ip);
		$result = pg_query($conn, $query);
		if  (!$result) {
			echo "Query $query did not execute\n";
			continue;
		}
		// Add record to event log
		//? $query = sprintf("insert into eventlog (name, eventtype, boxip, unit, loc, moment, zone) values ('%s at %d (%s)', '%d', '%s', %d, %d, to_timestamp('%s','Mon DD YYYY HH12:MI:SS:000PM'), 1)",
		//? 		$descr, $unitNum, $name, $zcode, $ip, $unitNum, $zone, $riscot[$id]);
		$query = sprintf("insert into eventlog (name, eventtype, boxip, unit, loc, moment, zone) values ('%s at %d (%s)', '%d', '%s', %d, %d, current_timestamp, 1)",
				$descr, $unitNum, $name, $zcode, $ip, $unitNum, $zone);
		$result = pg_query($conn, $query);
		if  (!$result) {
			echo "Query $query did not execute\n";
			continue;
		}
		// Add record to status log
		//? $query = sprintf("insert into statuslog (status, moment, unit, loc) values ('%d', to_timestamp('%s','Mon DD YYYY HH12:MI:SS:000PM'), %d, %d)",
		//? 		$eventtype, $riscot[$id], $unitNum, $zone);
		$query = sprintf("insert into statuslog (status, moment, unit, loc) values ('%d', current_timestamp, %d, %d)",
				$zcode, $unitNum, $zone);
		$result = pg_query($conn, $query);
		if  (!$result) {
			echo "Query $query did not execute\n";
			continue;
		}
		// Update current status
		$query = sprintf("update statuscurr set (status, moment) = ('%d', current_timestamp) where unit=%d", $zcode, $unitNum);
		$result = pg_query($conn, $query);
		if  (!$result) {
			echo "Query $query did not execute\n";
			continue;
		}
	}
	else {
		while ($col_value = pg_fetch_array($result))
		{
			//? echo "Alarm $id at " . $col_value['appt'] . " code " . $col_value['rcde'] . " (" . $col_value['descrptn'] . ") processed on " . $col_value['dt'] . "\n";
			//? echo "Alarm $id processed on " . $col_value['dt'] . "\n";
			;
		}
	}
}
pg_close($conn);
?>
