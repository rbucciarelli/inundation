<?php
//-----------------------------------------------------------------------------
// NDARPARAMETERDATA is an object holding output from the cdip 'ndar' utility
// in ParameterData format.
//-----------------------------------------------------------------------------
  include_once("ParameterData.php");

  date_default_timezone_set("UTC");
 
  class NdarParameterData extends ParameterData
  {


//-----------------------------------------------------------------------------
// CONSTRUCTOR - creates a new ParameterData object
//   Args:  1 - the commands to be passed to ndar
//          2 - Boolean TRUE for 'sea' or FALSE 'swell' (default) if format='ss'
//-----------------------------------------------------------------------------
    function NdarParameterData($ndar_args, $use_seas=false)
    {
      $args = explode(" ", $ndar_args);
      $this->station = $args[0];
      $this->stream = NULL;

      $format = $args[2];
      if ($format == 'st' && $format == 'ft')
      { echo "ERROR: NdarParameterData.php does not support spectral formats.\n";
        return; }

      if (($format == 'ss' || $format == 'fs') && is_null($use_seas))
      { echo "ERROR: NdarParameterData.php improper sea/swell invocation.\n";
        return; }

      $this->fields = array("time","hs","tp","dp","ta","sst","depth",
              "windspeed","winddir","pressure","airtemp","midtemp","bottemp",
              "sxy", "sxx", "dm", "vm", "hb", "acmspeed", "acmdir");

      foreach ($this->fields as $field)
      { $this->$field = array(); }

      $ndata = shell_exec("/project/f90_bin/ndar $ndar_args");
      $lines = explode("\n", trim($ndata,$character_mask="\n\r\0\x0B"));

      if ($lines != NULL)
      {
        if ($format == 'pm' || $format == 'p4') parent::__construct($this->station, $this->stream, $lines);
        else
        { foreach ($lines as $line)
          {  
            $fields = explode("\t", $line);
            if (count($fields) <= 1) $fields = explode(" ", $line);

            if (count($fields) > 1)
            { $time = strtotime($fields[0]);
              $hs = '';
              $tp = '';
              $dp = '';
              $ta = '';
              $sst = '';
              $depth = '';
              $windspeed = '';
              $winddir = '';
              $pressure = '';
              $airtemp = '';
              $midtemp = '';
              $bottemp = '';
              $sxy = '';
              $sxx = '';
              $dm = '';
              $vm = '';
              $hb = '';
              $acmspeed = '';
              $acmdir = '';

              if ($format == 'mp' || $format == 'fp')
              { $hs = $fields[1];
                $tp = $fields[2];
                $dp = $fields[3];
                $ta = $fields[4];
                if (count($fields) > 5)
                { $sxy = $fields[5];
                  $sxx = $fields[6];
                  $dm = $fields[7]; 
                  if (count($fields) > 8)
                  { $vm = $fields[8]; 
                    $hb = $fields[9]; } }
              }
              else if ($format == 'te' || $format == 'dt')
              { $sst = $fields[1]; }
              else if ($format == 'ac')
              { $acmspeed = $fields[1]; 
                $acmdir = $fields[2]; }
              else if (substr($format,0,2) == 'ss' || substr($format,0,2) == 'fs')
              { 
                if (! $use_seas)
                { $hs = $fields[1];
                  $tp = $fields[2];
                  $dp = $fields[3];
                  $ta = $fields[4]; }
                else
                { $hs = $fields[5];
                  $tp = $fields[6];
                  $dp = $fields[7];
                  $ta = $fields[8]; } 
              }

              foreach ($this->fields as $field)
              { if (trim(${$field}) == '') array_push($this->$field, NULL);
                else array_push($this->$field, trim(${$field})); }
            }
          }
        }
      }
      
      $this->fields_in_use = array();

      foreach ($this->fields as $field)
      {  if (abs(array_sum($this->$field)) > 0) 
           array_push($this->fields_in_use,$field); }

      $this->initLabels();
    }

  }
?>
