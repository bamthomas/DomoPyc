<?php
setlocale(LC_ALL , "fr_FR" );
date_default_timezone_set("Europe/Paris");

// Adapté du code de Domos.
// cf . http://vesta.homelinux.net/wiki/teleinfo_papp_jpgraph.html

// Base de donnée Téléinfo:
/*
Format de la table:
timestamp   rec_date   rec_time   adco     optarif isousc   hchp     hchc     ptec   inst1   inst2   inst3   imax1   imax2   imax3   pmax   papp   hhphc   motdetat   ppot   adir1   adir2   adir3
1234998004   2009-02-19   00:00:04   700609361116   HC..   20   11008467   10490214   HP   1   0   1   18   23   22   8780   400   E   000000     00   0   0   0
1234998065   2009-02-19   00:01:05   700609361116   HC..   20   11008473   10490214   HP   1   0   1   18   23   22   8780   400   E   000000     00   0   0   0
1234998124   2009-02-19   00:02:04   700609361116   HC..   20   11008479   10490214   HP   1   0   1   18   23   22   8780   390   E   000000     00   0   0   0
1234998185   2009-02-19   00:03:05   700609361116   HC..   20   11008484   10490214   HP   1   0   0   18   23   22   8780   330   E   000000     00   0   0   0
1234998244   2009-02-19   00:04:04   700609361116   HC..   20   11008489   10490214   HP   1   0   0   18   23   22   8780   330   E   000000     00   0   0   0
1234998304   2009-02-19   00:05:04   700609361116   HC..   20   11008493   10490214   HP   1   0   0   18   23   22   8780   330   E   000000     00   0   0   0
1234998365   2009-02-19   00:06:05   700609361116   HC..   20   11008498   10490214   HP   1   0   0   18   23   22   8780   320   E   000000     00   0   0   0
*/

// Config : Connexion MySql et requête. et prix du kWh 
include_once("config.php");


/****************************************************************************************/
/*    Graph consomation w des 24 dernières heures + en parrallèle consomation d'Hier    */
/****************************************************************************************/
function daily () {
  global $table, $tarif_type;
  
  $courbe_titre[0]="Heures de Base";
  $courbe_min[0]=5000;
  $courbe_max[0]=0;
  $courbe_titre[1]="Heures Pleines";
  $courbe_min[1]=5000;
  $courbe_max[1]=0;
  $courbe_titre[2]="Heures Creuses";
  $courbe_min[2]=5000;
  $courbe_max[2]=0;

  $courbe_titre[3]="Intensité";
  $courbe_min[3]=45;
  $courbe_max[3]=0;

  $date = isset($_GET['date'])?$_GET['date']:null;
  
  if (isset($date)) {
    $timestampheure = min($date, time());
  }
  else {
    $heurecourante = date('H') ;
    $timestampheure = mktime($heurecourante+1,0,0,date("m"),date("d"),date("Y"));
  }

  $timestampfin = $timestampheure;
  $periodesecondes = 24*3600 ;              // 24h.
  $timestampdebut = $timestampheure - $periodesecondes ;        // Recule de 24h.

  $timestampdebut2 = $timestampdebut;
  $timestampdebut = $timestampdebut - $periodesecondes ;        // Recule de 24h.

  $query = querydaily($timestampdebut, $timestampfin);
  
  $result=mysql_query($query) or die ("<b>Erreur</b> dans la requète <b>" . $query . "</b> : "  . mysql_error() . " !<br>");

  $nbdata=0;
  $nbenreg = mysql_num_rows($result);
  $nbenreg--;
  $date_deb=0; // date du 1er enregistrement
  $date_fin=time();

  $array_BASE = array();
  $array_HP = array();
  $array_HC = array();
  $array_I = array();
  $array_Temp = array();
  $array_JPrec = array();
  $navigator = array();

  $row = mysql_fetch_array($result);
  $ts = intval($row["timestamp"]);

  while (($ts < $timestampdebut2) && ($nbenreg>0) ){
    $ts = ( $ts + 24*3600 ) * 1000;
    $val = floatval(str_replace(",", ".", $row["papp"]));
    array_push ( $array_JPrec , array($ts, $val ));
    $row = mysql_fetch_array($result);
    $ts = intval($row["timestamp"]);
    $nbenreg--;
  }

  while ($nbenreg > 0 ){
    if ($date_deb==0) {
      $date_deb = $row["timestamp"];
    }
    $ts = intval($row["timestamp"]) * 1000;
    if ( $row["ptec"] == "TH.." )      // Test si heures de base.
    {
      $val = floatval(str_replace(",", ".", $row["papp"]));
      array_push ( $array_BASE , array($ts, $val ));
      array_push ( $array_HP , array($ts, null ));
      array_push ( $array_HC , array($ts, null ));
      array_push ( $navigator , array($ts, $val ));
      if ($courbe_max[0]<$val) {$courbe_max[0] = $val; $courbe_maxdate[0] = $ts;};
      if ($courbe_min[0]>$val) {$courbe_min[0] = $val; $courbe_mindate[0] = $ts;};
    }
    elseif ( $row["ptec"] == "HP" )      // Test si heures pleines.
    {
      $val = floatval(str_replace(",", ".", $row["papp"]));
      array_push ( $array_BASE , array($ts, null ));
      array_push ( $array_HP , array($ts, $val ));
      array_push ( $array_HC , array($ts, null ));
      array_push ( $navigator , array($ts, $val ));
      if ($courbe_max[1]<$val) {$courbe_max[1] = $val; $courbe_maxdate[1] = $ts;};
      if ($courbe_min[1]>$val) {$courbe_min[1] = $val; $courbe_mindate[1] = $ts;};
    }
    elseif ( $row["ptec"] == "HC" )      // Test si heures creuses.
    {
      $val = floatval(str_replace(",", ".", $row["papp"]));
      array_push ( $array_BASE , array($ts, null ));
      array_push ( $array_HP , array($ts, null ));
      array_push ( $array_HC , array($ts, $val ));
      array_push ( $navigator , array($ts, $val ));
      if ($courbe_max[2]<$val) {$courbe_max[2] = $val; $courbe_maxdate[2] = $ts;};
      if ($courbe_min[2]>$val) {$courbe_min[2] = $val; $courbe_mindate[2] = $ts;};
    }
    array_push ( $array_Temp, array($ts, floatval($row["temperature"]))) ;
    $val = floatval(str_replace(",", ".", $row["iinst1"])) ;
    array_push ( $array_I , array($ts, $val ));
    if ($courbe_max[3]<$val) {$courbe_max[3] = $val; $courbe_maxdate[3] = $ts;};
    if ($courbe_min[3]>$val) {$courbe_min[3] = $val; $courbe_mindate[3] = $ts;};
    // récupérer prochaine occurence de la table
    $row = mysql_fetch_array($result);
    $nbenreg--;
    $nbdata++;
  }
  mysql_free_result($result);

  $date_fin = $ts/1000;

  $plotlines_max = max($courbe_max[0], $courbe_max[1], $courbe_max[2]);
  $plotlines_min = min($courbe_min[0], $courbe_min[1], $courbe_min[2]);

  $ddannee = date("Y",$date_deb);
  $ddmois = date("m",$date_deb);
  $ddjour = date("d",$date_deb);
  $ddheure = date("G",$date_deb); //Heure, au format 24h, sans les zéros initiaux
  $ddminute = date("i",$date_deb);

  $ddannee_fin = date("Y",$date_fin);
  $ddmois_fin = date("m",$date_fin);
  $ddjour_fin = date("d",$date_fin);
  $ddheure_fin = date("G",$date_fin); //Heure, au format 24h, sans les zéros initiaux
  $ddminute_fin = date("i",$date_fin);

  $date_deb_UTC=$date_deb*1000;

  //$datetext = "$ddjour/$ddmois/$ddannee  $ddheure:$ddminute au $ddjour_fin/$ddmois_fin/$ddannee_fin  $ddheure_fin:$ddminute_fin";
  $datetext = "$ddjour/$ddmois  $ddheure:$ddminute au $ddjour_fin/$ddmois_fin  $ddheure_fin:$ddminute_fin";

  $seuils = array (
    'min' => $plotlines_min,
    'max' => $plotlines_max,
  );
  
  return array(
    'title' => "Graph du $datetext",
    'subtitle' => "",
    'debut' => $date_deb_UTC,
    'BASE_name' => $courbe_titre[0]." / min ".$courbe_min[0]." max ".$courbe_max[0],
    'BASE_data'=> $array_BASE,
    'HP_name' => $courbe_titre[1]." / min ".$courbe_min[1]." max ".$courbe_max[1],
    'HP_data' => $array_HP,
    'HC_name' => $courbe_titre[2]." / min ".$courbe_min[2]." max ".$courbe_max[2],
    'HC_data' => $array_HC,
    'I_name' => $courbe_titre[3]." / min ".$courbe_min[3]." max ".$courbe_max[3],
    'I_data' => $array_I,
    'Temp_name' => "Température",
    'Temp_data' => $array_Temp,
    'JPrec_name' => 'Période précédente', //'Hier',
    'JPrec_data' => $array_JPrec,
    'navigator' => $navigator,
    'seuils' => $seuils,
    'tarif_type' => $tarif_type
    );
}

/*************************************************************/
/*    Graph cout sur période [8jours|8semaines|8mois|1an]    */
/*************************************************************/
function history() {
  global $table;
  global $abo_annuel;
  global $prixBASE;
  global $prixHP;
  global $prixHC;
  global $tarif_type;

  $periode = isset($_GET['periode'])?$_GET['periode']:"8jours";
  
  switch ($periode) {
    case "8jours":
      $nbjours = 7 ;                // nb jours.
      $xlabel = "8 jours" ;
      $periodesecondes = $nbjours*24*3600 ;          // Periode en secondes.
      $timestampheure = gmmktime(0,0,0,date("m"),date("d"),date("Y"));    // Timestamp courant.
      $timestampdebut = $timestampheure - $periodesecondes ;      // Recule de $periodesecondes.
      $dateformatsql = "%a %e" ;
      $abonnement = $abo_annuel / 365;
      break;
    case "8semaines":
      $timestampdebut = gmmktime(0,0,0, date("m")-2, date("d"), date("Y"));
      $nbjour=1 ;
      while ( date("w", $timestampdebut) != 1 )  // Avance d'un jour tant que celui-ci n'est pas un lundi.
      {
        $timestampdebut = gmmktime(0,0,0, date("m")-2, date("d")+$nbjour, date("Y"));
        $nbjour++ ;
      }
      $xlabel = "8 semaines" ;
      $dateformatsql = "sem %v" ;
      $abonnement = $abo_annuel / 52;
      break;
    case "8mois":
      $timestampdebut = gmmktime(0,0,0, date("m")-7, 1, date("Y"));
      $xlabel = "8 mois" ;
      $dateformatsql = "%b" ;
      $abonnement = $abo_annuel / 12;
      break;
    case "1an":
      $timestampdebut = gmmktime(0,0,0, date("m")-11, 1, date("Y"));
      $xlabel = "1 an" ;
      $dateformatsql = "%b" ;
      $abonnement = $abo_annuel / 12;
      break;
    default:
      die("Periode erronée, valeurs possibles: [8jours|8semaines|8mois|1an] !");
      break;
  }

  $query="SET lc_time_names = 'fr_FR'" ;  // Pour afficher date en français dans MySql.
  mysql_query($query) ;
  
  $query = queryhistory($timestampdebut, $dateformatsql); 
  
  $result=mysql_query($query) or die ("<b>Erreur</b> dans la requète <b>" . $query . "</b> : "  . mysql_error() . " !<br>");
  $num_rows = mysql_num_rows($result) ;
  $no = 0 ;
  $date_deb=0; // date du 1er enregistrement
  $date_fin=time();

  while ($row = mysql_fetch_array($result))
  {
    if ($date_deb==0) {
      $date_deb = strtotime($row["rec_date"]);
    }
    $date[$no] = $row["rec_date"] ;
    $timestp[$no] = $row["periode"] ;
    /*
    $kwhbase[$no]=floatval(str_replace(",", ".", $row[2]));
    $kwhhp[$no]=floatval(str_replace(",", ".", $row[3]));
    $kwhhc[$no]=floatval(str_replace(",", ".", $row[4]));
    */
    $kwhbase[$no]=floatval(str_replace(",", ".", $row["base"]));
    $kwhhp[$no]=floatval(str_replace(",", ".", $row["hp"]));
    $kwhhc[$no]=floatval(str_replace(",", ".", $row["hc"]));
    $no++ ;
  }
  $date_digits_dernier_releve=explode("-", $date[count($date) -1]) ;
  $date_dernier_releve =  Date('d/m/Y', gmmktime(0,0,0, $date_digits_dernier_releve[1] ,$date_digits_dernier_releve[2], $date_digits_dernier_releve[0])) ;

  mysql_free_result($result);

  $ddannee = date("Y",$date_deb);
  $ddmois = date("m",$date_deb);
  $ddjour = date("d",$date_deb);
  $ddheure = date("G",$date_deb); //Heure, au format 24h, sans les zéros initiaux
  $ddminute = date("i",$date_deb);

  $date_deb_UTC=$date_deb*1000;

  $datetext = "$ddjour/$ddmois/$ddannee  $ddheure:$ddminute";
  $ddmois=$ddmois-1; // nécessaire pour Date.UTC() en javascript qui a le mois de 0 à 11 !!!

  $mnt_kwhbase = 0;
  $mnt_kwhhp = 0;
  $mnt_kwhhc = 0;
  $mnt_abonnement = 0;
  $i = 0;
  while ($i < count($kwhhp))
  {
    $mnt_kwhbase += $kwhbase[$i] * $prixBASE;
    $mnt_kwhhp += $kwhhp[$i] * $prixHP;
    $mnt_kwhhc += $kwhhc[$i] * $prixHC;
    $mnt_abonnement += $abonnement;
    $i++ ;
  }

  $mnt_total = $mnt_abonnement + $mnt_kwhbase + $mnt_kwhhp + $mnt_kwhhc;

  $prix = array (
    'abonnement' => $abonnement,
    'BASE' => $prixBASE,
    'HP' => $prixHP,
    'HC' => $prixHC,
  );

  if ($tarif_type == "HCHP") {
    $subtitle = "Coût sur la période ".round($mnt_total,2)." Euro<br />( Abonnement : ".round($mnt_abonnement,2)." + HP : ".round($mnt_kwhhp,2)." + HC : ".round($mnt_kwhhc,2)." )";
  } else {
    $subtitle = "Coût sur la période ".round($mnt_total,2)." Euro<br />( Abonnement : ".round($mnt_abonnement,2)." + BASE : ".round($mnt_kwhbase,2)." + HP : ".round($mnt_kwhhp,2)." + HC : ".round($mnt_kwhhc,2)." )";
  }
  
  return array(
    'title' => "Consomation sur $xlabel",
    'subtitle' => $subtitle,
    'debut' => $date_deb_UTC,
    'BASE_name' => 'Heures de Base',
    'BASE_data'=> $kwhbase,
    'HP_name' => 'Heures Pleines',
    'HP_data' => $kwhhp,
    'HC_name' => 'Heures Creuses',
    'HC_data' => $kwhhc,
    'categories' => $timestp,
    'prix' => $prix,
    'tarif_type' => $tarif_type
    );
}

$query = isset($_GET['query'])?$_GET['query']:"daily";

if (isset($query)) {
  mysql_connect($serveur, $login, $pass) or die("Erreur de connexion au serveur MySql");
  mysql_select_db($base) or die("Erreur de connexion a la base de donnees $base");
  mysql_query("SET NAMES 'utf8'");

  switch ($query) {
  case "daily":
    $data=daily();
    break;
  case "history":
    $data=history();
    break;
  default:
    break;
  };
  echo json_encode($data);

  mysql_close() ;
}

?>
