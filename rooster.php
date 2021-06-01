<?php

/**
* PHP script om meerdere tabbladen van het rooster op Google Docs rooster voor chrch.app samen te voegen (alternatief voor overview-tabblad)
* Auteur: Kaj ten Voorde
*/

$sheet_sources = explode("\n", file_get_contents('rooster_sheets.txt'));

$totaal_sheet_array = array();
$totaal_sheet_array['kolommen'] = array();

$aantal_maanden_tonen = 3;

// door google sheet url's heen lopen
foreach($sheet_sources as $sheet_source) {

	if($sheet_source[0] == "#") {
		// commentaar in bestand negeren
		continue;
	}
	
	$row = 1;
	if (($handle = fopen($sheet_source, "r")) !== FALSE) {
		// de google sheet uitlezen als csv-bestand
		while (($sheet_line_csv = fgetcsv($handle, 1000, ",")) !== FALSE) {
			
			if($row == 1) {
				// een index met kolomnr => kolomlabel
				$idx_by_nr = $sheet_line_csv;
				// een index met kolomlabel => kolomnr
				$idx_by_label = array_flip($sheet_line_csv);
			
			} else {

				$event_date = $sheet_line_csv[$idx_by_label['date']];

				// toon de gebeurtenissen t/m X maanden vooruit
				if(date( "m", strtotime($event_date)) - $aantal_maanden_tonen <= date("m")) {

					// regels die bij elkaar horen hebben dezelfde 'date' en dezelfde waarde voor 'groeperen'
					$groeperen = $sheet_line_csv[$idx_by_label['groeperen']];
					$event_label = $sheet_line_csv[$idx_by_label['label']];

					// elk uniek event krijgt een idx
					$event_idx = $event_date . '-' . ($groeperen ? $groeperen : $event_label);
					
					foreach($idx_by_nr as $column_idx => $column_label) {

						if($column_label != 'groeperen') {
							// vul de eind array met kolomwaarde per event
							$totaal_sheet_array[$event_idx][$column_label] = $sheet_line_csv[$column_idx];

							// houd ook bij welke kolommen er in totaal zijn
							if(!in_array($column_label, $totaal_sheet_array['kolommen'])) {
								array_push($totaal_sheet_array['kolommen'], $column_label);
							}
						}
					}
				}				
			}
			$row++;
		}
		fclose($handle);
	}
}

header("Content-type: text/csv");
header("Content-Disposition: attachment; filename=rooster.csv");
header("Pragma: no-cache");
header('Date: '.gmdate('D, d M Y H:i:s \G\M\T', time()));
header('Last-Modified: '.gmdate('D, d M Y H:i:s \G\M\T', time()));
header('Expires: '.gmdate('D, d M Y H:i:s \G\M\T', time())); 

// gebruik php://memory zodat we fputcsv kunnen toepassen
$handle_output = fopen("php://memory", "rw");

fputcsv($handle_output, $totaal_sheet_array['kolommen']);

// hier de "\n" overschrijven door "\r\n" want anders werkt het csv-bestand niet goed met de chrch-app
fseek($handle_output, -1, SEEK_CUR);

fwrite($handle_output, "\r\n");

foreach($totaal_sheet_array as $event_idx => $event_values) {
	
	if($event_idx == 'kolommen') {
		continue;
	}
	
	$output_row = array();
	
	foreach($totaal_sheet_array['kolommen'] as $column_idx => $column_label) {
		
		array_push($output_row, $event_values[$column_label]);
	}
	
	fputcsv($handle_output, $output_row);

	// hier de "\n" overschrijven door "\r\n" want anders werkt het csv-bestand niet goed met de chrch-app
	fseek($handle_output, -1, SEEK_CUR);
	
	fwrite($handle_output, "\r\n");
}

// de stream terugzetten naar 0 en outputten
fseek($handle_output, 0);

echo stream_get_contents($handle_output);

fclose($handle_output);

?>
