<?php
// epg_collector.php – varianta corectată pentru GitHub Actions

// setări de siguranță
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300'); // 5 minute
date_default_timezone_set("Europe/Chisinau");

// sursa EPG (dezarhivare automată)
$sources = [
    "compress.zlib://http://epg.it999.ru/epg.xml.gz"
];

// citește canalele din channels.txt
$channels = file("channels.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$channels = array_map(function($line) {
    $id = trim(strtok($line, "#")); // doar partea dinainte de #
    return strtolower($id);
}, $channels);

// deschide fișierul de output incremental
$out = fopen("epg.xml", "w");
fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tv>\n");

// funcție pentru procesarea EPG
function fetchEPG($url, $channels, $out) {
    $reader = new XMLReader();
    if (!$reader->open($url)) {
        fwrite(STDERR, "Nu pot deschide sursa: $url\n");
        return;
    }

    $now = time();

    while ($reader->read()) {
        if ($reader->nodeType == XMLReader::ELEMENT) {
            // <channel>
            if ($reader->name == "channel") {
                $id = strtolower($reader->getAttribute("id"));
                if (in_array($id, $channels)) {
                    fwrite($out, $reader->readOuterXML() . "\n");
                }
            }

            // <programme>
            if ($reader->name == "programme") {
                $id = strtolower($reader->getAttribute("channel"));
                if (in_array($id, $channels)) {
                    $start = $reader->getAttribute("start");
                    $stop  = $reader->getAttribute("stop");
                    $stopTime = DateTime::createFromFormat("YmdHis O", $stop);

                    if ($stopTime && $stopTime->getTimestamp() >= $now) {
                        $xml = new SimpleXMLElement($reader->readOuterXML());
                        $title = htmlspecialchars((string)$xml->title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                        fwrite($out, "<programme channel=\"$id\" start=\"$start\" stop=\"$stop\">\n");
                        fwrite($out, "  <title>$title</title>\n");
                        fwrite($out, "</programme>\n");
                    }
                }
            }
        }
    }
    $reader->close();
}

// rulează pentru fiecare sursă
foreach ($sources as $src) {
    fetchEPG($src, $channels, $out);
}

// finalizează fișierul
fwrite($out, "</tv>\n");
fclose($out);

echo "EPG generat cu succes.\n";
