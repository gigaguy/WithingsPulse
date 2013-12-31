<?php

//Withings API
require_once("conf/defines.php");
require_once("lib/OAuth.php");
require_once("lib/WBSApi.php");
require_once("lib/Log.php");

define ('pound',0.453592);
define ('inch', 0.0254);

$WBSApi = new WBSApi();

$userInfo = $WBSApi->getAccessToken();

//MYSQL connect
$con=mysqli_connect("server","user","password","db");
// Check connection
if (mysqli_connect_errno())
  {
  echo "Failed to connect to MySQL: " . mysqli_connect_error();
  }

//Manual withings oauth token
//$token = "oauthtoken";
//$secret = "oauthsecret";

//User ID from withings, this is a number
$userid = "withingsuserid";

//This grabs the oauth token from a db.  You could also just manuall assign it as a var.
$query = "select * from credentials where UserID=$userid";
$result = mysqli_query($con,$query);
$row = mysqli_fetch_array($result);
$token=$row['oauth_token'];
$secret=$row['oauth_secret'];
echo "<br>";


//Some date conversions. Allows me to set a start date.
$startepoch=strtotime($_GET['start']);
$endepoch=strtotime(date("Y-m-d"));
$startfmt=$_GET['start'];
$endfmt=date("Y-m-d");

//Logon using oauth
$WBSApi->setToken($token,$secret);

//set paramater attay
$params = array('userid'=>$userid,'startdateymd' => $startfmt,'enddateymd' => $endfmt);
//pulse data
$measure1 = $WBSApi->api('measure','getactivity',$params);

//$params = array('limit' => 60,'startdate'=>$startepoch,'enddate'=>$endepoch);
$params = array('startdate'=>$startepoch,'enddate'=>$endepoch);
//scale data
$measure2 = $WBSApi->api('measure','getmeas',$params);

$cnt=0;

//loop through results
$json_o = json_decode($measure1,true);
foreach ($json_o['body']['activities'] as $act){
  $dt=$act['date'];
  $steps=round($act['steps'],0);
  $distance=round($act['distance']* 0.00062137119,2);
  $calories=round($act['calories'],0);
  $elevation=round($act['elevation'] *3.2808399,0);
$cnt = $cnt+1;

/*
//This was when I was trying to output as xml

  echo "<Date>" . $dt . "</Date>";
  echo "<steps>" . $steps. "</steps>";
  echo "<Distance>" . $distance . "</Distance>";
  echo "<Calories>" . $calories . "</Calories>";
  echo "<Elevation>" . $elevation . "</Elevation>";
*/
//echo $act['value'];

//store the pulse data in the db
 mysqli_query($con,"insert into activity (dt, steps, distance, calories, elevation,userid) values (STR_TO_DATE('$dt','%Y-%m-%d'),$steps,$distance,$calories,$elevation, $userid) on duplicate key update steps='$steps',distance='$distance',calories='$calories',elevation='$elevation',userid='$userid'");


//echo "--------------<br>";

}
#print_r($measure2);
//now get scale data
$json_o = json_decode($measure2,true);

$result1 = array();
//loop scale data
foreach ($json_o['body']['measuregrps'] as $measuregrp){
 foreach ($measuregrp['measures'] as $measure){
  #$string = date('r',$measuregrp['date'])."\n";
  $string = date("Y-m-d",$measuregrp['date']);
  if ($measure['type'] == 1){
         #$result1 []= $measure['value'] ;
         #$Weight = $measure['value'];
         $Weight = floatval ( $measure['value'] ) * floatval ( "1e".$measure['unit'] );
         $Weight = $Weight * 2.20462;
         $Weight = round ( $Weight , 2);
         }
  elseif ($measure['type'] == 5) {
        $FatFree = floatval ( $measure['value'] ) * floatval ( "1e".$measure['unit'] );
        }
  elseif ($measure['type'] == 6) {
        $FatPct = floatval ( $measure['value'] ) * floatval ( "1e".$measure['unit'] );
        }
  elseif ($measure['type'] == 8) $FatMass = $measure['value'] . "\n";
  elseif ($measure['type'] == 9) {
        $Diastolic = floatval ( $measure['value'] ) * floatval ( "1e".$measure['unit'] );
        }
  elseif ($measure['type'] == 10) {
        $Systolic = floatval ( $measure['value'] ) * floatval ( "1e".$measure['unit'] );
        }
  elseif ($measure['type'] == 11) $HR = $measure['value'] . "\n";



}
//store scale data in db
 mysqli_query($con,"insert into activity (dt, weight, hr, fatpct,userid) values ('$string','$Weight','$HR','$FatPct','$userid') on duplicate key update weight='$Weight', hr='$HR', fatpct='$FatPct',userid='$userid'");

if ($HR){
        $HR = null;
}

$cnt = $cnt+1;
}

//output amount of data retreived
echo "$cnt rows";
mysqli_close($con);

