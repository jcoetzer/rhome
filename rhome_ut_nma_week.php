<?php
date_default_timezone_set('Africa/Johannesburg');

$ndate = new DateTime('now');
$nd = $ndate->format('Y-m-d H:i:s');
$udate = $ndate;
$interval = new DateInterval('P7D');
$udate->sub($interval);
$ud = $udate->format('Y-m-d H:i:s');
printf("NMA from %s to %s\n", $ud, $nd);

$conn = pg_pconnect("host=localhost user=defidev dbname=adomis password=p");
if (!$conn)  
{
	exit("Something went wrong while connecting to PostgreSQL\n");
}

$ghomei=array();
$ghomea=array();

if ($argc == 2)
{
	$query = "select a.unit aunit, modbip, nmaid from adomis_box a, statuscurr s where a.unit=" . $argv[1] . " and nmenabl=true and a.unit=s.unit";
}
else
{
	$query = 'select a.unit aunit, modbip, nmaid from adomis_box a, statuscurr s where nmenabl=true and a.unit=s.unit';
}
echo $query . "\n";

$result = pg_query($conn, $query);
if  (!$result) {
	exit("query $query did not execute");
}

$i=0;
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
	$nmarec=array_fill(0, 168, 0);
	$id=$ghomei[$unit];
	printf("Check unit %d IP address %s (last %d)\n\n", $unit, $ip, $id);

	//? $query = "select DateTime, Code, Group, IdentifierA from IPReceiverNG.dbo.ut_Queue;";
	$query = "select top 5000 [id], [datetime] from ipreceiverng.dbo.ut_queue where [code]=308 and [group]=4 and [identifiera]='" . $ip . "' order by [id] desc;";
	echo $query . "\n";
	$result = mssql_query($query);
	if ($result!=false) 
	{
		$n = 0;
		$nid = 0;
		while ( $record = mssql_fetch_array($result) )
		{
			$id = $record['id'];
			if (! $nid) $nid=$id;
			$dt = $record['datetime'];
			printf("%d\t%s\t", $id, $dt);
			// Jul 16 2013 10:06:00:000AM
			//   Jul 20 2013 11:11:00:000PM
			$qdate = date_create_from_format('M j Y h:i:00:000A', $dt);
			if (! $qdate)
			{
				printf("Could not format date/time %s\n", $dt);
				print_r(DateTime::getLastErrors());
				exit();
			}
			$interval = $ndate->diff($qdate);
			printf("%d\t[%s", $id, $ndate->format('Y-m-d H:i:s'));
			$ihr = intval($interval->format("%d"));
			printf("\t%d hours:\t", $ihr);
			if ($ihr >= 168)
			{
				printf("\tStop]\n");
				break;
			}
			$uts = $qdate->getTimestamp();
			$ltime = localtime($uts, TRUE);
			$whr = $ltime["tm_wday"]*24 + $ltime["tm_hour"];
			printf("\tDay %d Hour %d Index %d]\n", $ltime["tm_wday"], $ltime["tm_hour"], $whr);
			++$nmarec[$whr];
			$n++;
		}
		printf("\t%d results:\n", $n);
		for ($day=0; $day<7; $day++)
		{
			printf("\t");
			$idx1=$day*24;
			$idx2=$idx1+24;
			$query = sprintf("update statuscurr set(nmaid,pirevnt[%d]", $idx1);
			for ($hour=$idx1+1; $hour<$idx2; $hour++)
				$query .= sprintf(",pirevnt[%d]", $hour);
			$query .= sprintf(") = (%d,%d", $nid, $nmarec[$idx1]);
			for ($hour=$idx1+1; $hour<$idx2; $hour++)
				$query .= sprintf(",%d ", $nmarec[$hour]);
			$query .= sprintf(") where unit=%d", $unit);
			printf("\t%s\n", $query);
			$result = pg_query($conn, $query);
			if  (!$result) {
				exit("query $query did not execute");
			}
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
