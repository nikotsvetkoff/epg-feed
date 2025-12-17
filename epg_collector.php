<?php
// epg_timezone.php – ajustare fus orar
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
date_default_timezone_set("Europe/Chisinau");

// sursa EPG
$source = "compress.zlib://http://epg.it999.ru/epg.xml.gz";

// fus orar dorit
$targetTZ = new DateTimeZone("Europe/Chisinau");

// deschide fișierul comprimat pentru scriere
$out = gzopen("epg_fixed.xml.gz", "w9");
gzwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tv>\n");

$reader = new XMLReader();
if (!$reader->open($source)) {
    fwrite(STDERR, "Nu pot deschide sursa: $source\n");
    exit(1);
}

$now = time();

while ($reader->read()) {
    if ($reader->nodeType == XMLReader::ELEMENT) {
        if ($reader->name == "channel") {
            gzwrite($out, $reader->readOuterXML() . "\n");
        }

        if ($reader->name == "programme") {
            $start = $reader->getAttribute("start");
            $stop  = $reader->getAttribute("stop");
            $channel = $reader->getAttribute("channel");

            $startTime = DateTime::createFromFormat("YmdHis O", $start);
            $stopTime  = DateTime::createFromFormat("YmdHis O", $stop);

            if ($startTime && $stopTime) {
                // conversie la fusul orar dorit
                $startTime->setTimezone($targetTZ);
                $stopTime->setTimezone($targetTZ);

                if ($stopTime->getTimestamp() >= $now) {
                    $xml = new SimpleXMLElement($reader->readOuterXML());
                    $title = htmlspecialchars((string)$xml->title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                    gzwrite($out, "<programme channel=\"$channel\" start=\"".$startTime->format("YmdHis O")."\" stop=\"".$stopTime->format("YmdHis O")."\">\n");
                    gzwrite($out, "  <title>$title</title>\n");
                    gzwrite($out, "</programme>\n");
                }
            }
        }
    }
}
$reader->close();

gzwrite($out, "</tv>\n");
gzclose($out);

echo "EPG ajustat la fus orar Europe/Chisinau generat cu succes.\n";
