<?php

  date_default_timezone_set("UTC");

  include_once("truetype_fonts.php");  //- Must come before jpgraph

  include_once("jpgraph.php");
  include_once("jpgraph_line.php");
  include_once("jpgraph_plotline.php");

  include_once("ParameterData.php");
  include_once("TransectInfo.php");
  include_once("NdarParameterData.php");
  include_once("get_dar_data.php");
  include_once("init_input_vars.php");
  include_once("tide_utils.php");
  include_once("time_utils.php");
  include_once("truetype_fonts.php");

  $start_date = get_current_utc();
  $end_date = $start_date + (3*24*60*60);
  $stime = strtotime(date("Y/m/d H:00", $start_date));
  $etime = strtotime(date("Y/m/d H:00", $end_date));

  if ($argc >= 0) $mop = $argv[0];
  $web = false;
  if ($argc > 1 && $argv[1] == "web") $web = true;

  if (! isset($mop)) $mop = "D0681";
  if (! isset($mop)) die("ERROR: mop must be set");
  if (! isset($tz)) $tz = "PST";
  if (! isset($units)) $units = "english";
  if (! isset($pub)) $pub = 'public';

  $home_dir = "/project/dbase/root/cdip/recent/forecast";
  $tide_dir = "/project/data05/TIDE_DATA";
  $product_label = $mop;

  $mop_name = `grep $mop $home_dir/wlevel_sites/*_County.dat | cut -f2`;
  if (strlen($mop_name) < 2) 
  { $mop_name = $mop;
    $effects_mult = -1;
    $advisory_thresh = -1;
    $warning_thresh = -1; }
  else
  {
    $advisory_thresh = `grep $mop $home_dir/wlevel_sites/*_County.dat | cut -f3`;
    $advisory_thresh = trim($advisory_thresh);
    $warning_thresh = `grep $mop $home_dir/wlevel_sites/*_County.dat | cut -f4`;
    $warning_thresh = trim($warning_thresh);
    $effects_mult = `grep $mop $home_dir/wlevel_sites/*_County.dat | cut -f5`;
    if (strlen($mop_name) < 3) $mop_name = $mop;
  }
  if ($effects_mult == -1) $effects_mult = 0.043;

//- Load the forecast data

  $tInfo = new Transect($mop);
  $seq_offset = 2;
  $tList = new TransectList(max(1,$tInfo->sequence-$seq_offset),
    $tInfo->sequence+$seq_offset);

  $ftotData = new ParameterData();
  $ftotData->field_in_use = array('time','hs','ta');
  $t_count = 0;
  foreach ($tList->tEntries as $tEntry)
  { 
    $fcData = new NdarParameterData("{$tEntry->id} 2-+6 fp");
    $t_add = false;
    for ($i = 0; $i<count($fcData->time)-1; $i++)
    { 
      if (! isset($ftotData->time[$i]))
      { $ftotData->time[$i] = $fcData->time[$i];
        $ftotData->hs[$i] = $fcData->hs[$i];
        $ftotData->ta[$i] = $fcData->ta[$i]; 
        $t_add = true; }
      else if ($fcData->time[$i] == $ftotData->time[$i])
      { $ftotData->hs[$i] = $ftotData->hs[$i] + $fcData->hs[$i];
        $ftotData->ta[$i] = $ftotData->ta[$i] + $fcData->ta[$i]; 
        $t_add = true; }
    }
    if ($t_add) $t_count = $t_count + 1;
  }

//print_r($ftotData);
//die("$t_count \n");

  for ($i = 0; $i<count($ftotData->time)-1; $i++)
  { $ftotData->hs[$i] = $ftotData->hs[$i] / $t_count;
    $ftotData->ta[$i] = $ftotData->ta[$i] / $t_count; }

  $foreData = new ParameterData();
  $foreData->field_in_use = array('time','hs','ta');
  for ($i = 0; $i<count($ftotData->time)-1; $i++)
  { $foreData->time[$i*3] = $ftotData->time[$i];
    $foreData->time[$i*3+1] = $ftotData->time[$i]+3600;
    $foreData->time[$i*3+2] = $ftotData->time[$i]+7200;
    $foreData->hs[$i*3] = $ftotData->hs[$i];
    $foreData->hs[$i*3+1] = (2*$ftotData->hs[$i]+$ftotData->hs[$i+1])/3.0;
    $foreData->hs[$i*3+2] = ($ftotData->hs[$i]+2*$ftotData->hs[$i+1])/3.0;
    $foreData->ta[$i*3] = $ftotData->ta[$i];
    $foreData->ta[$i*3+1] = (2*$ftotData->ta[$i]+$ftotData->ta[$i+1])/3.0;
    $foreData->ta[$i*3+2] = ($ftotData->ta[$i]+2*$ftotData->ta[$i+1])/3.0; }


//- Load the tide data

  $tide_prefix = substr($mop,0,2);
  if ($tide_prefix[1] == "1") $tide_prefix[1] = "0";
  $tide_file = "$tide_dir/xtide_data/{$tide_prefix}_1990-2030.dat";

  $pmend_str = date("YmdH00", $end_date);
  $pmstart_str = date("YmdH00", $start_date);
  $pmend_month = substr($pmend_str,0,4)."-".substr($pmend_str,4,2);
  $pmstart_month = substr($pmstart_str,0,4)."-".substr($pmstart_str,4,2);

  $tide_grep = shell_exec("grep $pmstart_month $tide_file");
  $tides = explode("\n", trim($tide_grep));
  if ($pmstart_month != $pmend_month)
  { $tide_grep = shell_exec("grep $pmend_month $tide_file");
    $tides_add = explode("\n", trim($tide_grep));
    for ($i = 0; $i<count($tides_add); $i++)
    { array_push($tides, $tides_add[$i]); } }

  $msl_offset = get_monthly_MSL_offset($tide_prefix);

  $tideData = new ParameterData();
  $tideData->field_in_use = array('time','hs');
  foreach ($tides as $tide)
  { $fields = explode(' UTC ', $tide);
    $temp_time = strtotime($fields[0]);
    if ($temp_time >= $stime && $temp_time <= $etime && fmod($temp_time,3600)==0)
    { $tideData->hs[] = trim($fields[1]) + $msl_offset;
      $tideData->time[] = $temp_time; }
  }


//- Add the tide to the parameter data where the times match

  $waveAndTide = new ParameterData(NULL, NULL, NULL); 
  $waveAndTide->field_in_use = array('time','hs');
  $sindex = 0;
  $windex = 0;
  foreach ($foreData->time as $wtime)
  { 
    $tindex = 0;
    foreach ($tideData->time as $ttime)
    { if ($ttime == $wtime)
      { 
        $L_o = (9.8) * pow($foreData->ta[$windex],2) / (2 * 3.14159);
        $wave_effects = $effects_mult * pow($foreData->hs[$windex]*$L_o,0.5);
//      echo "{$foreData->hs[$windex]}  {$foreData->ta[$windex]}  $wave_effects\n";
        $waveAndTide->hs[$sindex] = 
          $wave_effects + $tideData->hs[$tindex];
        $waveAndTide->time[$sindex] = $tideData->time[$tindex];
        if ($waveAndTide->hs[$sindex] < 0) $waveAndTide->hs[$sindex] = 0;
        $sindex++;
      }
      $tindex++;
    }
    $windex++;
  }

  $tindex = 0;
  foreach ($tideData->time as $ttime)
  { if ($tideData->hs[$tindex] < -0.1) $tideData->hs[$tindex] = -0.1;
    $tindex++; }

  $maxval = max($waveAndTide->hs);

  if ($units == 'english') 
  { $unit = 'ft';
    $tideData->hs = array_map("m_to_ft", $tideData->hs);
    $waveAndTide->hs = array_map("m_to_ft", $waveAndTide->hs);
    $maxval = m_to_ft($maxval);
    $maxy = 20.0; }
  else 
  { $unit = 'm';
    $maxy = 6.5; }

  while ($maxval > $maxy)
  { $maxy = $maxy * 2; }

//- Calculate day locations for tick marks, vertical lines

  $tick_labels = array();
  $tick_vals = array();
  if ($tz != 'UTC') $tz_off = adjust_time(0,$tz)/3600;
  else $tz_off = 0;
  $ftick = (floor($stime / (60*60*24)) + 1/2 - $tz_off/24) * 60*60*24;
  for ($i=$ftick; $i<=$etime; $i=$i+60*60*24) 
  {
    $tick_vals[] = $i;
    $hr_label = date('  H', adjust_time($i,$tz))." $tz";
    $day_label = date('D m/d', adjust_time($i,$tz));
    $tick_labels[] = $hr_label."\n".$day_label;
  }

  $fvert = (floor($stime / (60*60*24)) - $tz_off/24) * 60*60*24;
  $verts = array();
  for ($i=$fvert; $i<=$etime; $i=$i+60*60*24) 
  { if ($i >= $stime) $verts[] = $i; }

  $width = 560;
  $height = 420;
  $top_margin = 100;
  $bottom_margin = 60;
  $left_margin = 60;
  $right_margin = 30;


  $im = @imagecreate($width, $height);
  $white = imagecolorallocate($im, 255, 255, 255);


      $graph = new Graph($width,$height,"auto");
      $graph->SetScale("intlin",0,$maxy,$stime,$etime);

      $graph->SetMargin($left_margin,$right_margin,$top_margin,$bottom_margin);
      $graph->setMarginColor("white");
      $graph->SetBox(true);
      $graph->SetFrame(false);

      $graph->yaxis->title->SetFont(FF_VERDANA,FS_NORMAL,11);
      $graph->yaxis->SetTitleMargin(30);

//    $graph->xaxis->SetTitle("Time ($tz)",'middle');
      $graph->xaxis->title->SetFont(FF_VERDANA,FS_BOLD,10);
      $graph->xaxis->SetFont(FF_VERDANA,FS_NORMAL,10);
      $graph->xaxis->SetTitleMargin(20);
      $graph->xaxis->SetTextLabelInterval(1);
      $graph->xaxis->SetPos('min');
      $graph->xaxis->SetTickPositions($tick_vals, NULL, $tick_labels);
      $graph->xaxis->scale->ticks->SetColor('black','black'); 

      if ($units == "metric") $graph->yscale->ticks->Set(2, 1);
      else $graph->yscale->ticks->Set(5, 1);
      $graph->yaxis->SetFont(FF_VERDANA,FS_NORMAL,11);
      $graph->yaxis->SetLabelMargin(4);

      $yAxisLabel = "Maximum water elevation ($unit)";
      $graph->yaxis->SetTitle($yAxisLabel,'middle');

      $graph->title->Set("Potential Flooding Index - $mop_name");
      $graph->title->SetFont(FF_VERDANA,FS_NORMAL,14);
      $graph->title->SetMargin(55);

      $lineplot1 =new LinePlot($waveAndTide->hs, $waveAndTide->time);
      $lineplot1->SetColor("black");
      $lineplot1->SetWeight(1);
      $lineplot1->SetFillColor("#99CCFF@0.2");
      $graph->Add($lineplot1);

      $lineplot2 =new LinePlot($tideData->hs, $tideData->time);
      $lineplot2->SetColor("black");
      $lineplot2->SetWeight(1);
      $lineplot2->SetFillColor("#333399@0.2");
      $graph->Add($lineplot2);

      foreach ($verts as $vert)
      { $pline = new PlotLine(VERTICAL,$vert,"gray",1);
        $graph->AddLine($pline); }

      $ltxt = new Text("CDIP/SIO");
      $ltxt->SetPos(20, 17);
      $ltxt->SetColor("black");
      $ltxt->SetFont(FF_VERDANA,FS_NORMAL,16);
      $graph->AddText($ltxt);

      if ($advisory_thresh != -1)
      { if ($units == "metric") $advisory_thresh = ft_to_m($advisory_thresh);
        $tline1 = new PlotLine(HORIZONTAL,$advisory_thresh,"orange",2);
        $graph->AddLine($tline1); }
      if ($warning_thresh != -1)
      { if ($units == "metric") $warning_thresh = ft_to_m($warning_thresh);
        $tline2 = new PlotLine(HORIZONTAL,$warning_thresh,"red",2);
        $graph->AddLine($tline2); }

      $hs_img = $graph->Stroke(_IMG_HANDLER);


  imagecopy($im,$hs_img,0,0,0,0,$width,$height);
  imagedestroy($hs_img);
  $black = imagecolorallocate($im, 0, 0, 0);
  imagefilledrectangle($im, $left_margin, $height-$bottom_margin+1,
    $width-$right_margin, $height-$bottom_margin+3, $white);

  $blue = imagecolorallocatealpha($im, 51, 51, 153, 25);
  $lightblue = imagecolorallocate($im, 153, 204, 255);
  $orange = imagecolorallocate($im, 255, 153, 0);
  $red = imagecolorallocatealpha($im, 255, 0, 0, 25);
  $xoff1 = 160;
  $yoff1 = 80;
  $xoff2 = $xoff1 + 100;
  imagerectangle($im,$xoff1,$yoff1,$xoff1+22,$yoff1+12,$black);
  imagefilledrectangle($im,$xoff1+1,$yoff1+1,$xoff1+21,$yoff1+11,$blue);
  imagettftext($im, 11, 0, $xoff1+27, $yoff1+12, $black, $ttf_arial, "Tide");
  imagerectangle($im,$xoff2,$yoff1,$xoff2+22,$yoff1+12,$black);
  imagefilledrectangle($im,$xoff2+1,$yoff1+1,$xoff2+21,$yoff1+11,$lightblue);
  imagettftext($im, 11, 0, $xoff2+27, $yoff1+12, $black, $ttf_arial, 
    "Tide + wave effects");

  $warning = "Water level elevation (relative to MLLW) forecasts use\n".
    "Stockdon (2006), are HIGHLY experimental, and should not\n".
    "be used as your primary forecast information.";
  imagettftext($im, 10, 0, 130, 20, $red, $ttf_arial, $warning);

  if ($advisory_thresh != -1)
  { $advisory_text = "Empirical mild flood threshold";
//  $advisory_text = "NWS flood advisory threshold";
    imagettftext($im, 10, 0, 315, 130, $orange, $ttf_arial, $advisory_text); }
  
  if ($warning_thresh != -1)
  { $warning_text = "Empirical moderate flood threshold";
//  $warning_text = "NWS flood warning threshold";
    imagettftext($im, 10, 0, 315, 115, $red, $ttf_arial, $warning_text); }
  
  if ($web)
  { header ("Content-type: image/png");
    imagepng($im);      }
  else
  { $ofile = "/project/dbase/root/cdip/recent/model_images/fcast_wlevel_".
      trim($product_label).".png";
    imagepng($im, $ofile); }

?>
