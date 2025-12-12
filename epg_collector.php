<?php
// epg_collector.php – colectare EPG simplă

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

// funcție pentru procesarea EPG
function fetchEPG($url, $channels) {
    $reader = new XMLReader();
    if (!$reader->open($url)) {
        fwrite(STDERR, "Nu pot deschide sursa: $url\n");
        return "";
    }

    $out = "";
    $now = time();

    while ($reader->read()) {
        if ($reader->nodeType == XMLReader::ELEMENT) {
            // <channel>
            if ($reader->name == "channel") {
                $id = strtolower($reader->getAttribute("id"));
                if (in_array($id, $channels)) {
                    $out .= $reader->readOuterXML() . "\n";
                }
            }

            // <programme>
            if ($reader->name == "programme") {
                $id = strtolower($reader->getAttribute("channel"));
                if (in_array($id, $channels)) {
                    $start = $reader->getAttribute("start");
                    $stop  = $reader->getAttribute("stop");
                    $stopTime = DateTime::createFromFormat("YmdHis O", $stop);

                    // dacă vrei să vezi tot, scoate filtrul de timp
                    if ($stopTime && $stopTime->getTimestamp() >= $now) {
                        $xml = new SimpleXMLElement($reader->readOuterXML());
                        $title = (string)$xml->title;
                        $out .= "<programme channel=\"$id\" start=\"$start\" stop=\"$stop\">\n";
                        $out .= "  <title>$title</title>\n";
                        $out .= "</programme>\n";
                    }
                }
            }
        }
    }
    $reader->close();
    return $out;
}

// output final
echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tv>\n";
foreach ($sources as $src) {
    echo fetchEPG($src, $channels);
}
echo "</tv>\n";
