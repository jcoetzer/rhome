<?php
error_reporting(E_ERROR);

printf("Connect to PostgreSQL\n");
$conn = pg_pconnect("host=localhost user=defidev dbname=adomis password=p");
if (!$conn)  
{
        exit("Something went wrong while connecting to PostgreSQL\n");
}

echo "Movement report for units\n";

$localtime = localtime();
$localtime_assoc = localtime(time(), true);
$hr = $localtime_assoc['tm_hour'];

//echo "<!-- Hour is " . $hr . " -->\n";
$he = $hr;
$hd = $he - 1;
if ($hd < 0) $hd += 24;
$hc = $hd - 1;
if ($hc < 0) $hc += 24;
$hb = $hc - 1;
if ($hb < 0) $hb += 24;
$ha = $hb - 1;
if ($ha < 0) $ha += 24;
printf ("From %02dh00 to %02dh59\n", $ha, $he);

$conn = pg_pconnect("host=localhost user=defidev dbname=adomis password=p");
if (!$conn) dberror('pg_pconnect');

$query = "select a.unit, udescrptn, " .
	"status, pirevnt[$ha] ha, pirevnt[$hb] hb, pirevnt[$hc] hc, pirevnt[$hd] hd, pirevnt[$he] he, " .
	"snote,  extract(epoch from nstart) startn, extract(epoch from nend) endn " .
	"from statuscurr s, unitstatus u, adomis_box a where status=ustatus and a.unit=s.unit and a.nmenabl=true order by unit";

//echo "<!-- " . $query . " -->\n";
$minv1 = 5;
$minv2 = 10;

$istr = '';
$i = 1;

$result = pg_query($conn, $query);
if (!$result)  dberror($query);

$ctime = time();
$i = 0;
$alarm = array();
$zone=4;
while($col_value = pg_fetch_array($result))
{
	$unit = $col_value['unit'];
	$st = $col_value['status'];
	$ud = $col_value['udescrptn'];
	$ha = $col_value['ha'];
	$hb = $col_value['hb'];
	$hc = $col_value['hc'];
	$hd = $col_value['hd'];
	$he = $col_value['he'];
	if ($hb == 0 && $hc == 0 && $hd == 0 && $he == 0)
	{
		$descr = "No movement at " . $unit; // . " (" . $ud . ")";
		$bg= 0; // "yellow"  
		$zcode = 1;
	}
	elseif ($hb <= $minv1 && $hc <= $minv1 && $hd <= $minv1 && $he <= $minv1)
	{
		$descr = "Very little movement at " . $unit; // . " (" . $ud . ")";
		$bg= 1; // "red" 
		$zcode = 1;
	}
	elseif ($hb <= $minv2 && $hc <= $minv2 && $hd <= $minv2 && $he <= $minv2)
	{
		$descr = "Little movement at " . $unit; // . " (" . $ud . ")";
		$bg= 2; // "orange"
		$zcode = 1;
	}
	else
	{
		$bg=3;
		$descr = "OK";
		$zcode = 0;
	}
	echo "Unit ".$col_value['unit'];
	echo "\tCondition " . $bg . " (" . $descr . ")";
	if ($bg<=0)
	{
		echo "\tALARM ";
		// Add record to event log
		$query = sprintf("insert into eventlog (name, eventtype, unit, loc, moment) values ('%s', '%d', %d, %d, current_timestamp)",
				$descr, $zcode, $unit, $zone); 
		$result2 = pg_query($conn, $query);
		if  (!$result2) {
			echo "query $query did not execute";
			continue;
		}
		// Add record to status log
		$query = sprintf("insert into statuslog (status, moment, unit, loc) values ('%d', current_timestamp, %d, %d)",
				$zcode, $unit, $zone);
		$result2 = pg_query($conn, $query);
		if  (!$result2) {
			echo "query $query did not execute";
			continue;
		}
		// Update current status
		$query = sprintf("update statuscurr set (status, moment) = ('%d', current_timestamp) where unit=%d", $zcode, $unit);
		$result2 = pg_query($conn, $query);
		if  (!$result2) {
			echo "query $query did not execute";
			continue;
		}
	}
	echo "\n";
	$i++;
}
pg_close($conn);
exit("Done\n");
?>

