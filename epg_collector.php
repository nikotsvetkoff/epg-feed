<?php
// epg_collector.php – varianta finală

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '300');
 $channels = array_map(function($line) {
    return strtolower($id);
}, $channels);

$out = fopen("epg.xml", "w");
fwrite($out, "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<tv>\n");


function fetchEPG($url, $channels, $out) {
    $reader = new XMLReader();

 function fetchEPG($url, $channels, $out) {
            if ($reader->name == "channel") {
                $id = strtolower($reader->getAttribute("id"));
                if (in_array($id, $channels)) {
                    fwrite($out, $reader->readOuterXML() . "\n");
                }
            }


 function fetchEPG($url, $channels, $out) {
                        $xml = new SimpleXMLElement($reader->readOuterXML());
                        $title = htmlspecialchars((string)$xml->title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

                        fwrite($out, "<programme channel=\"$id\" start=\"$start\" stop=\"$stop\">\n");
                        fwrite($out, "  <title>$title</title>\n");
                        fwrite($out, "</programme>\n");
                    }
    }
