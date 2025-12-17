<?php
// epg_collector.php – colectează EPG și ajustează timpii în funcție de sezon

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');

date_default_timezone_set("Europe/Chisinau");

// sursa EPG (comprimată)
$sourceUrl = "compress.zlib://http://epg.it999.ru/epg.xml.gz";

// citește canalele din channels.txt
$channels = file("channels.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$channels = array_map(function($line) {
    // ia doar prima parte numerică din linie
    $id = preg_replace('/\D.*$/', '', trim($line));
    return $id;
}, $channels);

// deschide fișierul de ieșire
$out = fopen("epg.xml", "w");
fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tv>\n");

// funcție pentru ajustarea timpului în funcție de sezon
function adjustTimeSeason($epgTime) {
    $dt = DateTime::createFromFormat("YmdHis O", $epgTime, new DateTimeZone("UTC"));
    if (!$dt) return $epgTime;

    // verifică dacă e DST (ora de vară)
    $tz = new DateTimeZone("Europe/Chisinau");
    $offset = $tz->getOffset($dt);

    // Moldova: UTC+2 iarna, UTC+3 vara
    // iarna → +1h înainte, vara → -1h înapoi
    if ($offset == 2 * 3600) {
        $dt->modify("+1 hour");
    } elseif ($offset == 3 * 3600) {
        $dt->modify("-1 hour");
    }

    return $dt->format("YmdHis O");
}

// funcție de procesare EPG
function fetchEPG($url, $channels, $out) {
    $reader = new XMLReader();
    if (!$reader->open($url)) {
        fwrite(STDERR, "Nu pot deschide sursa: $url\n");
        return;
    }

    $now = time();

    while ($reader->read()) {
        if ($reader->nodeType == XMLReader::ELEMENT) {
            if ($reader->name == "channel") {
                $id = $reader->getAttribute("id");
                if (in_array($id, $channels)) {
                    fwrite($out, $reader->readOuterXML() . "\n");
                }
            }

            if ($reader->name == "programme") {
                $id = $reader->getAttribute("channel");
                if (in_array($id, $channels)) {
                    $start = $reader->getAttribute("start");
                    $stop  = $reader->getAttribute("stop");

                    // aplică diferența de timp sezonieră
                    $start = adjustTimeSeason($start);
                    $stop  = adjustTimeSeason($stop);

                    $stopTime = DateTime::createFromFormat("YmdHis O", $stop);
                    if ($stopTime && $stopTime->getTimestamp() >= $now) {
                        fwrite($out, "<programme channel=\"$id\" start=\"$start\" stop=\"$stop\">\n");
                        $xml = new SimpleXMLElement($reader->readOuterXML());
                        $title = htmlspecialchars((string)$xml->title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                        fwrite($out, "  <title>$title</title>\n");
                        fwrite($out, "</programme>\n");
                    }
                }
            }
        }
    }
    $reader->close();
}

// rulează colectorul
fetchEPG($sourceUrl, $channels, $out);

// finalizează fișierul
fwrite($out, "</tv>\n");
fclose($out);

echo "EPG filtrat și scris în epg.xml cu ajustare sezonieră.\n";
