<?
//Enthält die "globale" Konfiguration der Speedport-Anbindung und wird von den anderen IPS-Speedport-Scripten aufgerufen

$password = "deinPasswort"; //Kennwort für den Zugriff auf den Router
$url = "http://192.168.1.1/"; //IP-Adresse des Speedport-Routers (häufig auch "speedport.ip")
$parentId = 18172 /*[System\Skripte\Speedport\Variables]*/; //Speicherort für zu erstellende Speedport Variablen.

/** OPTIONALE ANPASSUNGEN **/
$debug = false; //Debug-Informationen auf Konsole ausgeben
$variable_profile_prefix = "Speedport_"; //Prefix für anzulegende Variablenprofile
$call_sort = SORT_DESC; //Sortier-Reihenfolge für Anruflisten. SORT_DESC => neueste zuerst, SORT_ASC => älteste zuerst.

//Intervall in Minuten in dem eine Firmware-Updateprüfung erfolgen soll
//(aufwändige Funktion; nicht so oft durchführen. Bsp.: 1 mal im Monat => ca. 43200 Minuten)
$fw_update_interval = 43200;
?>