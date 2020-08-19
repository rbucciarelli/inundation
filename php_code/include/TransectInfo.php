<?php
//-----------------------------------------------------------------------------
// TransectInfo contains the definitions of the transects used for bor's
// nearshore work and related info/routines.
//-----------------------------------------------------------------------------
  
include_once("submit_query.php");
include_once("Locations.php");


//-----------------------------------------------------------------------------
// TransectList is an object holding a collection Transects
//-----------------------------------------------------------------------------
  class TransectList
  {
    var $tEntries;
    var $count;

//-----------------------------------------------------------------------------
// Constructor - creates a new TransectList object
//   Args:  1 (optional) - start transect id or sequence number
//          2 (optional) - end transect id or seq; must lie north of 1
//-----------------------------------------------------------------------------
    function TransectList($start=NULL, $end=NULL, $orig=false)
    {
      if ($orig) $table = "orig_transect_defs";
      else $table = "transect_defs";

      $this->tEntries = array();

      if (is_null($start))
        $query = "SELECT * FROM $table ORDER BY sequence ASC;";
      else if ($end == "COUNTY")
      {
        $clength = strlen($start);
        $query = "SELECT * FROM $table WHERE SUBSTR(id,1,$clength)=\"$start\" ".
          " AND SUBSTR(id,$clength+1,1) BETWEEN '0' AND '9' ORDER BY sequence ASC;";
      }
      else
      { 
        if (! is_numeric($start))
        { $query = "SELECT sequence FROM $table WHERE id=\"$start\";";
          $result = submit_query($query, "nearshore_v1"); 
          $vals = mysqli_fetch_array($result);
          $start_index = $vals['sequence']; }
        else $start_index = $start;

        if (! is_numeric($end))
        { $query = "SELECT sequence FROM $table WHERE id=\"$end\";";
          $result = submit_query($query, "nearshore_v1"); 
          $vals = mysqli_fetch_array($result);
          $end_index = $vals['sequence']; }
        else $end_index = $end;

        $query = "SELECT * FROM $table WHERE sequence>=$start_index ".
          "AND sequence<=$end_index ORDER BY sequence ASC;";
      }

      $result = submit_query($query, "nearshore_v1"); 

      while ($line=mysqli_fetch_array($result))
      { $trans = new Transect($line);
        array_push($this->tEntries, $trans); }

      $this->count = count($this->tEntries);
    }

  }



//-----------------------------------------------------------------------------
// TRANSECT is an object holding the definition of a transect, as set
// in the MySQL nearhore.transect_defs table
//-----------------------------------------------------------------------------
  class Transect
  {
    var $id, $sequence;
    var $backbeach, $nearshore;
    var $bb_lat, $bb_lon, $ns_lat, $ns_lon;
    var $shore_normal, $depth;
    var $modified;


//-----------------------------------------------------------------------------
// Constructor - creates a new Transect object
//   Args:  1 - vals, either a transect id, a lat/lon pair, or an array of 
//     values returned from mysql
//-----------------------------------------------------------------------------
    function Transect($vals, $orig=false)
    {
      if ($orig) $table = "orig_transect_defs";
      else $table = "transect_defs";
   
      if (is_object($vals) && get_class($vals) == "Location")
      { $lat = $vals->latitude;
        $lon = $vals->longitude;
        $query = "SELECT id, SQRT(POWER(ns_lat-$lat,2)+POWER((ns_lon-$lon)*COS($lat/57.3),2)) ".
          "AS dist_factor FROM $table ORDER BY 2 ASC LIMIT 1;";
        $result = submit_query($query, "nearshore_v1");
        $vals = mysqli_fetch_array($result); 
        $query = "SELECT * FROM $table WHERE id=\"".$vals["id"]."\";";
        $result = submit_query($query, "nearshore_v1");
        $vals = mysqli_fetch_array($result); 
      }
      else if (count($vals) == 1)
      { $query = "SELECT * FROM $table WHERE id=\"$vals\";";
        $result = submit_query($query, "nearshore_v1");
        $vals = mysqli_fetch_array($result); 
      }

      $fields = array('sequence','id','bb_lat','bb_lon','ns_lat','ns_lon',
        'modified');
      foreach ($fields as $field)
      { $this->$field = $vals[$field]; }

//    $query = "SELECT depth, normal FROM nearshore_v1.transect_info WHERE ".
//      "id = \"".$this->id."\";";
//    $result = submit_query($query, "nearshore_v1");
//    $vals = mysql_fetch_array($result); 
//    $this->depth = $vals['depth'];
//    $this->shore_normal = $vals['normal'];

      $this->backbeach = new Location($this->bb_lat, $this->bb_lon);
      $this->nearshore = new Location($this->ns_lat, $this->ns_lon);
    }


//-----------------------------------------------------------------------------
// getRealtimeParameters - returns the latest parameter readings for the
//   Transect by querying nearshore_v1.coast_realtime
//-----------------------------------------------------------------------------
    function getRealtimeParameters()
    {
      $fields = array("pmTime", "Hs", "Tp", "Dp", "Ta", "Sxx", "Sxy", "Dm", "Vm", "Hb");
      $query = "SELECT * from nearshore_v1.coast_realtime WHERE id=\"".
        $this->id."\";";
      $result = submit_query($query);
      $vals = mysqli_fetch_array($result); 
      $latest = array();
      foreach ($fields as $field)
      { $latest[$field] = $vals[$field]; }
      return $latest;
    }

    public static function getRealtimeRange($param)
    { 
      $query = "SELECT MAX($param) AS max, MIN($param) AS min FROM ".
        "nearshore_v1.coast_realtime;";
      $result = submit_query($query);
      $vals = mysqli_fetch_array($result); 
      $latest = array($vals["min"], $vals["max"]);
      return $latest;
    }


//-----------------------------------------------------------------------------
// nextTransect - returns the id of the upcoast transect
// prevTransect - returns the id of the downcoast transect
//-----------------------------------------------------------------------------
    function nextTransect($step=1)
    {
      $next_seq = $this->sequence + $step;
      $query = "SELECT id FROM transect_defs WHERE sequence=$next_seq;";
      $result = submit_query($query, "nearshore_v1");
      $vals = mysqli_fetch_array($result); 
      return $vals['id'];
    }


    function prevTransect($step=1)
    {
      $prev_seq = $this->sequence - $step;
      $query = "SELECT id FROM transect_defs WHERE sequence=$prev_seq;";
      $result = submit_query($query, "nearshore_v1");
      $vals = mysqli_fetch_array($result); 
      return $vals['id'];
    }

  }

//-----------------------------------------------------------------------------
// get_county_tag - returns the 1 or 2 char tag of the county
//-----------------------------------------------------------------------------
  function get_county_tag($label)
  {
    if (preg_match("/^[A-Z][A-Z]/", $label) > 0) $county = substr($label,0,2);
    else $county = substr($label,0,1);
    return $county;
  }

?>
