<?php

//- Uses the API from tidesandcurrents.noaa.gov to return the latest monthly
//- offset between observed MSL and the MSL datum. Uses the following stations:
//-   County          Tide station
//-   San Diego (D0)  9410230     NOTE: If SIO sensor down, use 9410170
//-   OC and LA       9410660 
//-   VE and SB (B0)  9411340
//-   SLO (SL)        9412110
//-   MO, SC, and SM  9413450
//-   San Fran (SF)   9414290 
//-   Marin (MA)      9415020
//-   SN, Mendo (M0)  9416841
//-   HU and DN       9418767

  function get_NOAA_tide_station($county_prefix)
  {
    if ($county_prefix == "D0") $station = "9410230";
    else if ($county_prefix == "L0" || $county_prefix == "OC") $station = "9410660";
    else if ($county_prefix == "VE" || $county_prefix == "B0") $station = "9411340";
    else if ($county_prefix == "SL") $station = "9412110";
    else if ($county_prefix == "MO" || $county_prefix == "SC" || $county_prefix == "SM") $station = "9413450";
    else if ($county_prefix == "SF") $station = "9414290";
    else if ($county_prefix == "MA") $station = "9415020";
    else if ($county_prefix == "SN" || $county_prefix == "M0") $station = "9416841";
    else if ($county_prefix == "HU" || $county_prefix == "DN") $station = "9418767";
    else $station = "9410660";
    return $station;
  }


  function get_monthly_MSL_offset($county_prefix="L0")
  { 
    date_default_timezone_set("UTC");

    $station = get_NOAA_tide_station($county_prefix);

    $startstring = date('Ymd', time()-3600*24*31*2);
    $endstring = date('Ymd');

    $tides_url = "http://tidesandcurrents.noaa.gov/api/datagetter?begin_date=$startstring&end_date=$endstring&station=$station&product=monthly_mean&datum=msl&units=metric&time_zone=gmt&application=web_services&format=csv";

//  $wdata = file($tides_url);
    $wdata = false;
    if ($wdata === false) 
    { return 0.0; }
      
//  copy($tides_url,'latest_offsets.dat');

    $first = $wdata[0];
    $fields = explode(',', $first);
    $findex = array_search(' MSL', $fields);
    if ($findex === false)
    { return 0.0; }

    $last = array_pop($wdata);
    $fields = explode(',', $last);
    $offset = trim($fields[$findex]);
    return $offset; 
  }

?>
