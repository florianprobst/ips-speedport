# ips-speedport
Telekom Speedport Hybrid Anbindung für IP-Symcon

## Aufgabe des Skripts
Dieses Skript greift auf die Weboberfläche des Telekom Speedport Hybrid Routers zu und liest dabei alle möglichen Informationen aus. Dazu gehören u.a. DSL-Status, IP-Adresse, Anruflisten, DSL-Informationen, Leitungsqualität, LTE-Verbindungsqualität, WLAN-Informationen, etc. 

## Weiterführende Informationen
Das Skript legt selbstständig benötigte IPS-Variablen und Variablenprofile unterhalb des Skriptes an.
Derzeit sind dies 29-Variablen und 9 Variablenprofile.
Durch das Speichern der Werte in IPS-Variablen wird Logging und das Anbinden von IPS-Events ermöglicht.
Zur besseren Auffindbarkeit und eindeutigen Zuordnung werden alle Variablenprofile mit einem Präfix angelegt. 
Standardmässig lautet das "Speedport_".

Die in den Variablenprofilen festgelegten Wertungen (was ist gut und was schlecht) von Dämpfungswerten, etc. basieren auf Daten aus meiner Internetrecherche. Dafür keine Gewähr :-)

Es ist zu berücksichtigen, dass jeder Aufruf dieses Skripts andere Benutzer aus der Weboberfläche des Routers herauswirft.
Desweiteren benötigt das Skript je nach Rechenkapazität und Verbindung des IPS-Hosts zum Router einen Moment zur Ausführung.
Bei mir sind es bis zu 5 Sekunden. 

Getestet wurde das Skript bei mir mit einem Speedport Hybrid auf Firmware 050124.01.00.057

Ältere Speedportmodelle verfügen über ein anderes Webinterface / Anmeldeverfahren und funktionieren voraussichtlich nicht. Eventuell funktioniert es mit aktuellen Non-Hybrid-Speedports. 

## Installation

1. In IPS ein neues Skript anlegen, diesen Code einfügen
2. die fünf Konfigurationsparameter anpassen (zumindest IP und Passwort müssen angepasst werden)
3. Skript ausführen. Fertig.

zur schnelleren Deinstallation gibt's ganz unten eine auskommentierte Funktion '$sp->cleanup();'.
Wenn diese Funktion ausgeführt wird, werden alle erstellten Variablen und Variablenprofile wieder gelöscht.
(Achtung: Variablenprofile werden anhand des Präfix gesucht. Wenn der geändert wurde und noch alte Profile existieren, werden diese nicht automatisch gelöscht.) 


##Externe Quellen

Das Script setzt die Klasse "speedport" von Jan Altensen voraus. Diese ist in diesem Skript inkludiert. [Quelle Speedport-Klasse von Jan Altensen] (https://github.com/Stricted/speedport-hybrid-php-api/).
Da diese Klasse Funktionen von PHP 5.5 voraussetzt (PBKDF2) und IPS 3.x auf einer älteren PHP Version arbeitet, ist die Login-Funktion modifiziert, dass bei PHP < 5.5 die Funktion compat_pbkdf2() aufgerufen wird.
Diese habe ich von hier bezogen: [Quelle compat_pbkdf2](https://gist.github.com/rsky/5104756)

Auch wenn es schöner aussieht, die einzelnen Klassen und Funktionen in "includes / requires" zu packen habe ich in diesem Fall darauf verzichtet um eine einfache Installation zu ermöglichen. Ihr könnt das gerne auseinandernehmen :-)
