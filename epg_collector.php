
<?php
// epg_collector.php – colectează EPG, scoate desc, normalizează id-uri

$sources = [
    "compress.zlib://http://epg.it999.ru/epg.xml.gz"
];

// citește canalele din channels.txt
$channels = file("channels.txt", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$channels = array_map('strtolower', $channels);

function fetchEPG($url, $channels) {
    $reader = new XMLReader();
    if (!$reader->open($url)) return "";

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

            // <programme> doar cu title + start/stop
            if ($reader->name == "programme") {
                $id = strtolower($reader->getAttribute("channel"));
                if (in_array($id, $channels)) {
                    $start = $reader->getAttribute("start");
                    $stop  = $reader->getAttribute("stop");
                    $stopTime = DateTime::createFromFormat("YmdHis O", $stop);
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

echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tv>\n";
foreach ($sources as $src) {
    echo fetchEPG($src, $channels);
}
echo "</tv>\n";
