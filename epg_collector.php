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
                    gzwrite($out, $reader->readOuterXML() . "\n");
                }
            }

            if ($reader->name == "programme") {
                $id = strtolower($reader->getAttribute("channel"));
                if (in_array($id, $channels)) {
                    $start = $reader->getAttribute("start");
                    $stop  = $reader->getAttribute("stop");

                    // ajustează timpii cu +45 minute
                    $startDt = DateTime::createFromFormat("YmdHis O", $start);
                    $stopDt  = DateTime::createFromFormat("YmdHis O", $stop);

                    if ($startDt) $startDt->modify("+45 minutes");
                    if ($stopDt)  $stopDt->modify("+45 minutes");

                    $start = $startDt ? $startDt->format("YmdHis O") : $start;
                    $stop  = $stopDt ? $stopDt->format("YmdHis O") : $stop;

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
