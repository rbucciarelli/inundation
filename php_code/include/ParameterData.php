<?php
//-----------------------------------------------------------------------------
// PARAMETERDATA is an object holding multiple records of CDIP parameter
// (pm) data.
//-----------------------------------------------------------------------------
  include_once('unit_utils.php');

  class ParameterData
  {
    var $station, $stream;
    var $time, $hs, $tp, $dp, $ta, $sst, $depth;
    var $windspeed, $winddir, $pressure, $airtemp, $midtemp, $bottemp;
    var $sxy, $sxx, $dm, $vm, $hb, $acmspeed, $acmdir;

//- Meta-data variables

    var $fields;
    var $fields_in_use;
    var $field_formats;
    var $field_labels;
    var $field_units;


//-----------------------------------------------------------------------------
// CONSTRUCTOR - creates a new ParameterData object
//   Args:  1 - lines, an array of parameter data
//-----------------------------------------------------------------------------
    function ParameterData($stn=NULL, $strm=NULL, $lines=NULL)
    {
      $this->station = $stn;
      $this->stream = $strm;

      $this->fields = array("time","hs","tp","dp","ta","sst","depth",
              "windspeed","winddir","pressure","airtemp","midtemp","bottemp",
              "sxy", "sxx", "dm", "vm", "hb", "acmspeed", "acmdir");

      foreach ($this->fields as $field)
      { $this->$field = array(); }


      if ($lines != NULL)
      {
        foreach ($lines as $line)
        {  
          $timestr = substr($line,0,16);
          $timestr = substr_replace($timestr,"-",4,1);
          $timestr = substr_replace($timestr,"-",7,1);
          $timestr = substr_replace($timestr,":",13,1);
          $time = strtotime($timestr);

          $hs = substr($line,17,5);
          $tp = substr($line,23,5);
          $dp = substr($line,29,3);
          $ta = substr($line,42,5);
          $sst = substr($line,74,4);
          $depth = substr($line,33,8);
          $windspeed = substr($line,55,6);
          $winddir = substr($line,62,3);
          $pressure = substr($line,48,6);
          $airtemp = substr($line,67,4);
          $midtemp = substr($line,81,4);
          $bottemp = substr($line,88,4);

          $sxy = '';
          $sxx = '';
          $dm = '';
          $vm = '';
          $hb = '';
          $acmspeed = '';
          $acmdir = '';

          foreach ($this->fields as $field)
          { if (trim(${$field}) == '') array_push($this->$field, NULL);
            else array_push($this->$field, trim(${$field})); }
        }
      }
      
      $this->fields_in_use = array();

      foreach ($this->fields as $field)
      {  if (array_sum($this->$field) > 0) 
           array_push($this->fields_in_use,$field); }

      $this->initLabels();
    }


//-----------------------------------------------------------------------------
// initLabels - sets the units, formats, and labels of the fields. Helper 
//   function for the constructor.
//-----------------------------------------------------------------------------
    function initLabels()
    {

//- Initialize labels and units

      $this->field_formats = array("time" => "%s",  
                                  "hs" => "%5.2f",
                                  "tp" => "%5.2f",
                                  "dp" => "%3d",
                                  "ta" => "%5.2f",
                                  "sst" => "%4.1f",
                                  "depth" => "%5.2f",
                                  "windspeed" => "%4.1f",
                                  "winddir" => "%4.1f",
                                  "pressure" => "%4.1f",
                                  "airtemp" => "%4.1f",
                                  "midtemp" => "%4.1f",
                                  "bottemp" => "%4.1f",
                                  "sxy" => "%7.4f",
                                  "sxx" => "%7.4f",
                                  "dm" => "%3d",
                                  "vm" => "%6.2f",
                                  "hb" => "%5.2f",
                                  "acmspeed" => "%4.2f",
                                  "acmdir" => "%3d");

      $this->field_labels = array("time" => "Date/Time",  
                                  "hs" => "Hs",
                                  "tp" => "Tp",
                                  "dp" => "Dp",
                                  "ta" => "Ta",
                                  "sst" => "SST",
                                  "depth" => "Depth",
                                  "windspeed" => "Wind sp",
                                  "winddir" => "Wind dir",
                                  "pressure" => "Air pres",
                                  "airtemp" => "Air temp",
                                  "midtemp" => "Mid-col temp",
                                  "bottemp" => "Bottom temp",
                                  "sxy" => "Sxy",
                                  "sxx" => "Sxx",
                                  "dm" => "Dm",
                                  "vm" => "Vm",
                                  "hb" => "Hb",
                                  "acmspeed" => "Current sp",
                                  "hb" => "Currect dir");

      $this->field_units = array(); 
      foreach ($this->fields as $field)
      { $this->field_units[$field] = get_label('metric', $field); }

    }

  }
?>
