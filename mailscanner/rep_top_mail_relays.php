<?php

/*
 MailWatch for MailScanner
 Copyright (C) 2003-2011  Steve Freegard (steve@freegard.name)
 Copyright (C) 2011  Garrod Alwood (garrod.alwood@lorodoes.com)

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with this program; if not, write to the Free Software
 Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

// Include of necessary functions
require_once("./functions.php");
require_once("./filter.inc");

// Authentication checking
session_start();
require('login.function.php');

// add the header information such as the logo, search, menu, ....
$filter = html_start("Top Mail Relays", 0, false, true);

// File name
$filename = "" . CACHE_DIR . "/top_mail_relays.png." . time() . "";

$sql = "
SELECT
 clientip,
 count(*) AS count,
 sum(virusinfected) AS total_viruses,
 sum(isspam) AS total_spam,
 sum(size) AS size
FROM
 maillog
WHERE
 1=1
" . $filter->CreateSQL() . "
GROUP BY
 clientip
ORDER BY
 count DESC
LIMIT 10";

// Check permissions to see if apache can actually create the file
if (is_writable(CACHE_DIR)) {

    // JpGraph functions
    include_once("./jpgraph/src/jpgraph.php");
    include_once("./jpgraph/src/jpgraph_pie.php");
    include_once("./jpgraph/src/jpgraph_pie3d.php");

    $result = dbquery($sql);
    if (!mysql_num_rows($result) > 0) {
        die("Error: no rows retrieved from database\n");
    }

    $relay_array = array();

    while ($row = mysql_fetch_object($result)) {
        $data[] = $row->count;
        $hostname = gethostbyaddr($row->clientip);
        if ($hostname == $row->clientip) {
            $data_names[] = "(Hostname lookup failed)";
        } else {
            $data_names[] = $hostname;
        }
        $data_ip[] = $row->clientip;
        if ($geoip = return_geoip_country($row->clientip)) {
            $data_geoip[] = $geoip;
        } else {
            $data_geoip[] = "(GeoIP lookup failed)";
        }
        $data_virus[] = $row->total_viruses;
        $data_spam[] = $row->total_spam;
        $data_size[] = $row->size;
    }

    $graph = new PieGraph(800, 385, 0, false);
    $graph->SetShadow();
    $graph->img->SetAntiAliasing();
    $graph->title->Set("Top 10 Mail Relays");
    //$graph->legend->SetLayout(LEGEND_HOR);
    $graph->legend->Pos(0.52, 0.87, 'center');

    $p1 = new PiePlot3d($data);
    $p1->SetTheme('sand');
    $p1->SetLegends($data_names);

    $p1->SetCenter(0.73, 0.4);
    $graph->legend->SetLayout(LEGEND_VERT);
    $graph->legend->Pos(0.25, 0.20, 'center');

    $graph->Add($p1);
    $graph->Stroke($filename);
}


// HTML code to display the graph
echo "<TABLE BORDER=\"0\" CELLPADDING=\"10\" CELLSPACING=\"0\" WIDTH=\"100%\">";
echo "<TR>";
echo " <TD ALIGN=\"CENTER\"><IMG SRC=\"" . IMAGES_DIR . "mailscannerlogo.gif\" ALT=\"MailScanner Logo\"></TD>";
echo "</TR>";
echo "<TR>";

//  Check Permissions to see if the file has been written and that apache to read it.
if (is_readable($filename)) {
    echo " <TD ALIGN=\"CENTER\"><IMG SRC=\"" . $filename . "\" ALT=\"Graph\"></TD>";
} else {
    echo "<TD ALIGN=\"CENTER\"> File isn't readable. Please make sure that " . CACHE_DIR . " is readable and writable by Mailwatch.";
}

echo "</TR>";
echo "<TR>";
echo "<TD ALIGN=\"CENTER\">";
echo "<TABLE WIDTH=\"500\">";
echo "<TR BGCOLOR=\"#F7CE4A\">";
echo "    <TH>Hostname</TH>";
echo "    <TH>IP Address</TH>";
echo "    <TH>Country</TH>";
echo "    <TH>Messages</TH>";
echo "    <TH>Viruses</TH>";
echo "    <TH>Spam</TH>";
echo "    <TH>Volume</TH>";
echo "   </TR>";

for ($i = 0; $i < count($data_names); $i++) {
    echo "
   <TR BGCOLOR=\"#EBEBEB\">
    <TD>$data_names[$i]</TD>
    <TD>$data_ip[$i]</TD>
    <TD>$data_geoip[$i]</TD>
    <TD ALIGN=\"RIGHT\">" . number_format($data[$i]) . "</TD>\n
    <TD ALIGN=\"RIGHT\">" . number_format($data_virus[$i]) . "</TD>\n
    <TD ALIGN=\"RIGHT\">" . number_format($data_spam[$i]) . "</TD>\n
    <TD ALIGN=\"RIGHT\">" . format_mail_size($data_size[$i]) . "</TD></TR>\n";
}
echo "
  </TABLE>
 </TD>
</TR>
</TABLE>";

// Add footer
html_end();
// Close any open db connections
dbclose();
