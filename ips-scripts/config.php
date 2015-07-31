<?
//Enth�lt die "globale" Konfiguration der Speedport-Anbindung und wird von den anderen IPS-Speedport-Scripten aufgerufen

$password = "deinPasswort"; //Kennwort f�r den Zugriff auf den Router
$url = "http://192.168.1.1/"; //IP-Adresse des Speedport-Routers (h�ufig auch "speedport.ip")
$parentId = 18172 /*[System\Skripte\Speedport\Variables]*/; //Speicherort f�r zu erstellende Speedport Variablen.

/** OPTIONALE ANPASSUNGEN **/
$debug = false; //Debug-Informationen auf Konsole ausgeben
$variable_profile_prefix = "Speedport_"; //Prefix f�r anzulegende Variablenprofile
$call_sort = SORT_DESC; //Sortier-Reihenfolge f�r Anruflisten. SORT_DESC => neueste zuerst, SORT_ASC => �lteste zuerst.

//Intervall in Minuten in dem eine Firmware-Updatepr�fung erfolgen soll
//(aufw�ndige Funktion; nicht so oft durchf�hren. Bsp.: 1 mal im Monat => ca. 43200 Minuten)
$fw_update_interval = 43200; //in Minuten

//status update interval: f�r das Aktualisieren der Routervariablen (empfohlen 10 Minuten)
$status_update_interval = 10; //in Minuten
?>