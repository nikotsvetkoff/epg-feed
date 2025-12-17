<?php
// epg_collector.php – colectează EPG, filtrează canale și normalizează timpii în Europe/Chisinau
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');


// sursa EPG (comprimată)
$sourceUrl = "compress.zlib://http://epg.it999.ru/epg.xml.gz";

// citește canalele din channels.txt (doar partea numerică)
$channels = file("channels.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$channels = array_map(function($line) {
    return preg_replace('/\D.*$/', '', trim($line));
}, $channels);

// deschide fișierul comprimat pentru scriere
$out = gzopen("epg.xml.gz", "w9");
gzwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tv>\n");



// procesează EPG-ul
function fetchEPG($url, $channels, $out) {
    $reader = new XMLReader();
    if (!$reader->open($url)) {
        fwrite(STDERR, "Nu pot deschide sursa: $url\n");
        return;
    }

    $now = time();

    while ($reader->read()) {
        if ($reader->nodeType == XMLReader::ELEMENT) {
            if ($reader->name === "channel") {
                $id = $reader->getAttribute("id");
                if (in_array($id, $channels)) {
                    gzwrite($out, $reader->readOuterXML() . "\n");
                }
            }

            if ($reader->name === "programme") {
                $id = $reader->getAttribute("channel");
                if (in_array($id, $channels)) {
                    $start = normalizeTime($reader->getAttribute("start"));
                    $stop  = normalizeTime($reader->getAttribute("stop"));

                    $stopTime = DateTime::createFromFormat("YmdHis O", $stop);
                    if ($stopTime && $stopTime->getTimestamp() >= $now) {
                        $xml = new SimpleXMLElement($reader->readOuterXML());
                        $title = htmlspecialchars((string)$xml->title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                        gzwrite($out, "<programme channel=\"$id\" start=\"$start\" stop=\"$stop\">\n");
                        gzwrite($out, "  <title>$title</title>\n");
                        gzwrite($out, "</programme>\n");
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
gzwrite($out, "</tv>\n");
gzclose($out);

echo "EPG generat cu timpi corectați și salvat în epg.xml.gz\n";
