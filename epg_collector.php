<?php
// epg_collector.php – varianta finală

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
date_default_timezone_set("Europe/Chisinau");

$sources = [
    "compress.zlib://http://epg.it999.ru/epg.xml.gz"
];

$channels = file("channels.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$channels = array_map(function($line) {
    $id = trim(strtok($line, "#"));
    return strtolower($id);
}, $channels);

$out = fopen("epg.xml", "w");
fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tv>\n");

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
                $id = strtolower($reader->getAttribute("id"));
                if (in_array($id, $channels)) {
                    fwrite($out, $reader->readOuterXML() . "\n");
                }
            }

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

foreach ($sources as $src) {
    fetchEPG($src, $channels, $out);
}

fwrite($out, "</tv>\n");
fclose($out);

echo "EPG generat cu succes.\n";
