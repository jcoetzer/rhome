<?php
error_reporting(E_ERROR);
date_default_timezone_set('Africa/Johannesburg');

try {
	$dt = $argv[1];
	if ($dt!='')
		$date = new DateTime($dt);
	else
		$date = new DateTime();
} catch (Exception $e) {
	echo $e->getMessage();
	exit(1);
}

$curd = $date->format("M j Y h:i:00:000A");
//       Jul 16 2013 10:06:00:000AM

$cmon = array("Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec");

$today =  localtime(time(), true);
$curd = sprintf("%3s %2d %4d %02d:%02d:00:000", $cmon[$today["tm_mon"]], $today["tm_mday"], $today["tm_year"]+1900, $today["tm_hour"], $today["tm_min"]);
if ($today["hours"] > 12)
	$curd .= "PM";
else
	$curd .= "AM";
echo "Timestamp is " . $curd . "\n";

$nowtime = $date->getTimestamp();
$ltime = localtime($nowtime, TRUE);
$whr = $ltime["tm_wday"]*24 + $ltime["tm_hour"];

$conn = pg_pconnect("host=localhost user=defidev dbname=adomis password=p");
if (!$conn)  
{
	exit("Something went wrong while connecting to PostgreSQL\n");
}

$query = 'select a.unit aunit, modbip, nmaid from adomis_box a, statuscurr s where nmenabl=true and a.unit=s.unit';
$result = pg_query($conn, $query);
if  (!$result) {
	exit("query $query did not execute");
}

$i=0;
$ghomei=array();
$ghomea=array();
while($col_value = pg_fetch_array($result))
{
	$unit=$col_value['aunit'];
	$modbip=$col_value['modbip'];
	$nmaid=$col_value['nmaid'];
	$ghomei[$unit]=$nmaid;
	$ghomea[$unit]=$modbip;
}

// Server in the this format: <computer>\<instance name> or 
// <server>,<port> when using a non default port number
$server = '10.255.181.252\RISCOIPRCV';

// Connect to MSSQL
printf("Connect to MSSQL\n");
$link = mssql_connect($server, 'test', 'password123');

if (!$link) 
{
        exit("Something went wrong while connecting to MSSQL\n");
}

foreach($ghomea as $unit => $ip)
{
	$id=$ghomei[$unit];
	printf("\nCheck unit %d IP address %s (last record ID %d)\n", $unit, $ip, $id);

	//? $query = "select top 100 [id], [datetime] from ipreceiverng.dbo.ut_queue where [code]=308 and [group]=4 and [identifiera]='" . $ip . "' and [id]>" . $id . " order by [id] desc;";
	$query = "select top 100 [id], [datetime] from ipreceiverng.dbo.ut_queue where [group]=4 and [identifiera]='" . $ip . "' and [id]>" . $id . " order by [id] desc;";
	$lid = $id;
	echo $query . "\n";
	$result = mssql_query($query);
	if ($result!=false) 
	{
		$n = 0;
		$nid = 0;
		$fdt = '';
		while ( $record = mssql_fetch_array($result) )
		{
			$id = $record['id'];
			$dt = $record['datetime'];
			if ($fdt == '') $fdt = $dt;
			if (strncmp($dt, $curd, 14)==0)
			{
				if ($nid==0) $nid=$id;
				printf("%s %s\n", $id, $dt);
				$n++;
			}
		}
		if ($n!=0 && $nid!=0)
		{
			printf("Set unit %d ID %d NMA<%d> to <%d>\n", $unit, $nid, $whr, $n);
			$query = sprintf("update statuscurr set(nmaid, pirevnt[%d]) = (%d,%d) where unit=%d", $whr, $nid, $n, $unit);
			printf("%s\n", $query);
			$result = pg_query($conn, $query);
			if  (!$result) {
				printf("query $query did not execute\n");
			}
		}
		else
		{
			if ($fdt == '' && $lid == 0) 
				printf("No data\n");
			elseif ($fdt == '') 
				printf("No new data\n");
			else
				printf("No new data since %s\n", $fdt);
		}
	}
	else
	{
		exit("MSSQL query failed\n");
	}
	printf("\n");
}

// Close the link to PostgreSQL
pg_close($conn);
printf("Disconnected from PostgreSQL\n");

// Close the link to MSSQL
mssql_close($link);
printf("Disconnected from MSSQL\n");

exit("Done\n");
?>
