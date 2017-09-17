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

// host=localhost user=defidev dbname=adomis password=p
$dbhost = "localhost";
$dbuser = "defidev";
$dbpass = "p";
$dbname = "adomis";

// Use my for MySQL(default), pg for PostgreSQL, sl for SQLite
$dbtype = "pg";
//Create and move this outside of webroot, as it must be writeable by the webserver user.
$dbpath = "/path/to/sqlite.db"; 

$siteaddress = "http://localhost/limph"; //No trailing slash
$sitetitle = "Limph: MES";

//Host agent options

$disable=1; //Set to 0 to activate.  Not for use on webserver.

$secret = ""; //Set this to match all remote host agents

$limph = "http://localhost/limph/"; //Your Limph install, with trailing slash

$partitions = array(""); //List of partitions to check free space on
//Leave empty to omit disk check

$load = "1"; //*nix system load, set to 0 to omit

//Graphviz options

$graphviz_enable = "1"; //Set to 0 if graphviz is not installed
$graphviz = "/usr/bin/twopi"; //Path to desired Grahpviz binary

?>
