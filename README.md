![Alt text](docs/logo.png?raw=true "logo")


# Contao CSV Table Merger

Mit dem Modul lassen sich in einem Rutsch über eine CSV Datei massenhaft Datensätze mit einer Contao Tabelle mergen.
Die Textdatei agiert dabei als Master und die Contao Tabelle als Slave. Es besteht die Möglichkeit,dass in der Text-Datei nicht mehr vorhandene Datensätze in der Zieltabelle ebenfalls gelöscht werden.
Die CSV Datei wird am besten in einem Tabellenkalkulationsprogramm  (MS-EXCEL o.ä.) erstellt
und dann als kommaseparierte Datei (CSV) abgespeichert.

## Warnung!
**Achtung! Das Modul sollte nur genutzt werden, wenn man sich seiner Sache sehr sicher ist. Gelöschte Daten können nur wiederhergestellt werden, wenn vorher ein Datenbank-Backup erstellt worden ist. Der Autor dieser Extension übernimmt keinerlei Haftung.**

## Aufbau CSV-Importdatei
Mit MS-Excel oder einem Texteditor lässt sich eine (kommaseparierte) Textdatei anlegen (csv).
In die erste Zeile gehören zwingend die Feldnamen. Die einzelnen Felder müssen durch ein Trennzeichen
(üblicherweise das Semikolon ";") abgegrenzt werden. Feldinhalt, der in der Datenbank als serialisiertes
Array abgelegt wird (z.B. Gruppenzugehörigkeiten, Newsletter-Abos, etc.), muss durch zwei aufeinanderfolgende
Pipe-Zeichen abgegrenzt werden z.B. "2||5". Feldbegrenzer, Feldtrennzeichen, Arraytrennzeichen können individuell festgelegt werden.

Wichtig! Jeder Datensatz gehört in eine neue Zeile. Zeilenumbrüche im Datensatz verunmöglichen den Import.
Die erstellte csv-Datei muss über die Dateiverwaltung auf den Webserver geladen werden und kann nachher bei
der Import-Konfiguration ausgewählt werden.

Im Klartext vorliegende Passwörter werden verschlüsselt. Beim Importvorgang werden die Inhalte validiert. Als Grundlage dienen die DCA-Settings (rgxp) der Zieltabelle.

```
firstname;lastname;dateOfBirth;gender;company;street;postal;city;state;country;phone;mobile;fax;email;website;language;login;username;password;groups
Hans;Meier;1778-05-22;male;Webdesign AG;Ringelnatterweg 1;6208;Oberkirch;Kanton Luzern;ch;041 921 99 97;079 620 99 91;045 789 56 89;h-meier@me.ch;www.hans-meier.ch;de;1;hansmeier;topsecret;1||2
Fritz;Nimmersatt;1978-05-29;male;Webdesign AG;Entenweg 10;6208;Oberkirch;Kanton Luzern;ch;041 921 99 98;079 620 99 92;046 789 56 89;f-nimmersatt@me.ch;www.fritz-nimmersatt.ch;de;1;fritznimmer;topsecret2;1||2
Annina;Meile;1878-05-29;female;Webdesign AG;Nashornstrasse 2;6208;Oberkirch;Kanton Luzern;ch;043 921 99 99;079 620 93 91;047 789 56 89;a-meile@me.ch;www.annina-meile.ch;de;1;anninameile;topsecret3;1
```

### Zeilenumbrüche
Alle [NEWLINE] tags in der CSV Datei werden beim Import-Vorgang in \r\n bzw. \n umgewandelt.


### Backend

![Alt text](docs/backend.png?raw=true "Backend")


### Danksagung!
Grossen Dank an https://www.mockaroo.com/
