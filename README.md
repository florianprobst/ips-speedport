# ips-speedport
Telekom Speedport Hybrid Anbindung für IP-Symcon

## Aufgabe des Skripts
Dieses Skript greift auf die Weboberfläche des Telekom Speedport Hybrid Routers zu und liest dabei alle möglichen Informationen aus. Dazu gehören u.a. DSL-Status, IP-Adresse, Anruflisten, DSL-Informationen, Leitungsqualität, LTE-Verbindungsqualität, WLAN-Informationen, etc. 

### Unterstützte Firmware
Getestet wurde das Skript mit dem jeweils zum Testdatum aktuellen Entwicklungsstand. Spätere Weiterentwicklungen sind nicht mit den älteren Firmware-Versionen getestet.

* 29.07.2015 => Firmware 050124.01.00.057
* 29.07.2015 => Firmware 050124.02.00.009

## Weiterführende Informationen
Das Skript legt selbstständig benötigte IPS-Variablen und Variablenprofile unterhalb des Skriptes an.
Derzeit sind dies 30-Variablen und 10 Variablenprofile. (Je nach IP-Symcon Lizenz bitte berücksichtigen)
Durch das Speichern der Werte in IPS-Variablen wird Logging und das Anbinden von IPS-Events ermöglicht.
Zur besseren Auffindbarkeit und eindeutigen Zuordnung werden alle Variablenprofile mit einem Präfix angelegt. 
Standardmässig lautet das `Speedport_`.

Die in den Variablenprofilen festgelegten Wertungen (was ist gut und was schlecht) von Dämpfungswerten, etc. basieren auf Daten aus meiner Internetrecherche. Dafür keine Gewähr :-)

Es ist zu berücksichtigen, dass jeder Aufruf dieses Skripts andere Benutzer aus der Weboberfläche des Routers herauswirft.
Desweiteren benötigt das Skript je nach Rechenkapazität und Verbindung des IPS-Hosts zum Router einen Moment zur Ausführung.
Bei mir sind es bis zu 5 Sekunden. 

Ältere Speedportmodelle verfügen über ein anderes Webinterface / Anmeldeverfahren und funktionieren voraussichtlich nicht. Eventuell funktioniert es mit aktuellen Non-Hybrid-Speedports. 

## Installation

1. In IPS ein neues Skript anlegen, diesen Code einfügen
2. die fünf Konfigurationsparameter anpassen (zumindest IP und Passwort müssen angepasst werden)
3. Skript ausführen. Fertig.

zur schnelleren Deinstallation gibt's ganz unten eine auskommentierte Funktion ```php $sp->cleanup();```.
Wenn diese Funktion ausgeführt wird, werden alle erstellten Variablen und Variablenprofile wieder gelöscht.
(Achtung: Variablenprofile werden anhand des Präfix gesucht. Wenn der geändert wurde und noch alte Profile existieren, werden diese nicht automatisch gelöscht.) 


##Externe Quellen

Das Script setzt die Klasse "speedport" von Jan Altensen voraus. Diese ist in diesem Skript inkludiert. [Quelle Speedport-Klasse von Jan Altensen] (https://github.com/Stricted/speedport-hybrid-php-api/).

###Screenshots
![dsl/lte-router information](assets/dsl-lte-router-infos.jpg)
![ips variables](assets/ips-variables-speedport.jpg)
![anruferlisten](assets/anruflisten-speedport.jpg)
