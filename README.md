# Wer-ist-Wo-Liste-Erweiterung
Erweitert die Wer-ist-Wo/Online-Liste um die Information von eigenen Seiten.

# Benutzergruppen-Berechtigungen setzen
Damit alle Admin-Accounts Zugriff auf die Verwaltung der Online Locations haben im ACP, müssen unter dem Reiter Benutzer & Gruppen » Administrator-Berechtigungen » Benutzergruppen-Berechtigungen die Berechtigungen einmal angepasst werden. Die Berechtigungen für die Online Locations befinden sich im Tab 'Tools & Verwaltung'.

# Online Location hinzufügen
Ich erkläre das Prinzip der Felder anhand von Listen.<br>
Wir haben die PHP Datei lists dank action unterteilt in die Bereiche: charaview, faceclaims, names. Und in der Online Liste sollen die Hauptseite (lists) und die Unterseiten dargestellt werden. Wir müssen nun für alle vier Seiten eine Online Location im ACP hinzufügen.<br>
Für die Hauptseite: lists<br>
<b>Name:</b> Listen<br>
<b>PHP Datei:</b> lists<br>
<b>Parameter:</b><br>
<b>Seitenbezeichnung:</b><br>
<b>Anzeige in der Wer-ist-Wo/Online-Liste:</b> Sieht sich die automatischen Listen an.<br><br>

Für die Charakterübersicht: lists.php?action=charaview<br>
<b>Name:</b> Listen - Charakterübersicht<br>
<b>PHP Datei:</b> lists<br>
<b>Parameter:</b> action<br>
<b>Seitenbezeichnung:</b> charaview<br>
<b>Anzeige in der Wer-ist-Wo/Online-Liste:</b> Sieht sich die Charakterübersicht an.<br><br>

Für die vergebenen Avatarpersonen: lists.php?action=faceclaims<br>
<b>Name:</b> Listen - vergebenen Avatarpersonen<br>
<b>PHP Datei:</b> lists<br>
<b>Parameter:</b> action<br>
<b>Seitenbezeichnung:</b> faceclaims<br>
<b>Anzeige in der Wer-ist-Wo/Online-Liste:</b> Sieht sich die vergebenen Avatarpersonen an.<br><br>

Für die vergebenen Namen: lists.php?action=names<br>
<b>Name:</b> Listen - vergebenen Namen<br>
<b>PHP Datei:</b> lists<br>
<b>Parameter:</b> action<br>
<b>Seitenbezeichnung:</b> names<br>
<b>Anzeige in der Wer-ist-Wo/Online-Liste:</b> Sieht sich die vergebenen Namen an.<br><br>

Selbst Seiten, wo die Seitenbezeichnung unterschiedlich ist können eingetragen werden. Ein Beispiel dafür ist die Spielerübersicht mit Statistiken von sparks fly. Der Link für die Seite ist players.php?id=X. Die PHP-Datei wäre players. Der Parameter id und die Seitenbezeichnung wird freigelassen. Dann erscheint, egal welche ID schlussendlich aufgerufen wird in der Online-Liste das was im Feld "Anzeige in der Wer-ist-Wo/Online-Liste" eingetragen wurde.

# Links
<b>ACP</b><br>
index.php?module=tools-online_location

# Demo
# ACP
<img src="https://stormborn.at/plugins/onlinelocation_acp.png">
<img src="https://stormborn.at/plugins/onlinelocation_acp_add.png">

# Wer ist online - Liste
<img src="https://stormborn.at/plugins/onlinelocation_online.png">
