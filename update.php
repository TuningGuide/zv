<?php
/**
 *
 * User: velten
 * Date: 18/01/15 13:27
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors',1);

require_once __DIR__."/curl.php";
require_once __DIR__."/vendor/RedBeanPHP4_1_4/rb.php";

R::setup('mysql:host=localhost;dbname=zvsr', 'root','root');

function domToStringHelper($node) {
	return trim(strip_tags($node->ownerDocument->saveHTML($node)));
}

function getNextDomElement($node) {
	do {
		$node = $node->nextSibling;
		if($node instanceof DOMElement) {
			return $node;
		}
	}
	while($node !== null);
	return null;
}

function convertToFieldArray($childNodes) {
	$fields = ['Umkreis', 'Zwangsversteigerung', 'Adresse', 'Addresse', 'Objektart', 'Verkehrswert', 'Beschreibung'];

	$data = [];
	foreach ($childNodes as $key => $childNode) {
		$string = domToStringHelper($childNode);
		foreach ($fields as $field) {
			if (strpos($string, $field) === 0) {
				if ($field == 'Beschreibung') {
					$replace = "Objektbeschreibung (keine Gewähr für die Richtigkeit): ";
					$node = getNextDomElement($childNode);
					$string = str_replace($replace, '' ,domToStringHelper($node));
					$data[$field] = trim($string);
				}
				else {
					$len = strlen($field);
					$data[$field] = trim(substr($string, $len + ($string[$len] == ':' ? 2 : 1 )));
				}
				break;
			}
		}
	}

	return $data;
}

function commaColonSeparated($array) {
	$string = $sep = "";
	foreach($array as $name => $value) {
		$string .= $sep.$name.': '.$value;
		$sep = ', ';
	}

	return $string;
}

function RgetFromHash($type, $hash) {
	$bean = R::findOne($type, 'hash = ?', [$hash]);
	if (!$bean || !$bean->id) {
		$bean = R::dispense($type);
		//R::$writer->addUniqueIndex($type, ['hash']);
		$bean->hash      = $hash;
	}
	return $bean;
}

function i18nValueToValue($val) {
	return floatval(str_replace(',', '.', str_replace('.', '', $val)));
}

function findClassElement($dom, $class) {
	$xpath = new DomXpath($dom);
	return $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")]');
}

function main() {
	$baseUrl = "http://www.zwangsversteigerung.eu";
	$c = new CurlGetter($baseUrl);

	//$answer = $c->getPage("/logout");

	$loginData = ["user_session[email]" => "veltenheyn@web.de", "user_session[password]" => "leipzig01"];
	$answer = $c->getPage("/user_sessions", $loginData);
	die($answer);
	$c->setPrevious("/user_sessions");

	$answer = $c->getPage("/suchkriterien");
	$c->setPrevious("/suchkriterien");

	$dom = new DOMDocument;
	$dom->strictErrorChecking = false;
	$success = @$dom->loadHTML($answer);
	$xpath = new DomXpath($dom);
	$subscriptionUrl = '/subscriptions';
	$subscriptionLinks = $xpath->query("//a[starts-with(@href, '$subscriptionUrl')]");

	$data = [];

	if ($subscriptionLinks->length < 1) {
		echo "No subscription links found! Output is:";
		die($answer);
	}

	// get contents of each subscription / get list of possible flats
	foreach ($subscriptionLinks as $a) {
		// process subscriptionDetails
		/** @var DomNode $a */
		$fields = convertToFieldArray(getNextDomElement($a->parentNode)->firstChild->childNodes);

		$hash = md5($a->nodeValue.implode($fields));
		$subscription = RgetFromHash('subscription', $hash);
		$subscription->import($fields);

		$table = ['name' => $a->nodeValue, 'info' => commaColonSeparated($fields)];
		unset($fields);

		$url = $a->getAttribute('href');
		$answer = $c->getPage($url);

		$dom = new DOMDocument;
		$dom->strictErrorChecking = false;
		$success = $dom->loadHTML($answer);
		$xpath = new DomXpath($dom);
		$detailLinks = $xpath->query('//*[@class="ps"]/li/a');

		if($detailLinks->length < 1) {
			$table['content'] = "No detail link found for \"$url\"";
		}
		else {
			$table['content'] = [];

			// get details of each flat
			foreach ($detailLinks as $link) {
				$url = $link->getAttribute('href');
				$answer = $c->getPage($url);

				$dom = new DOMDocument;
				$dom->strictErrorChecking = false;
				$dom->recover = true;
				$success = @$dom->loadHTML($answer);

				$contentNode = $dom->getElementById('content');
				$childNodes = $contentNode->childNodes;
				$childs = [];
				foreach ($childNodes as $childNode) {
					$childs[] = $childNode;
				}
				$childNodes = $childs;
				$fields = convertToFieldArray($childNodes);

				$fields['url'] = $url;
				$fields['qm'] = '';
				$fields['qmsum'] = 0;
				if(isset($fields['Beschreibung']) && preg_match_all("/(\\d+)(?:.|,)?(\\d*)\\s*(m²|qm)/", $fields['Beschreibung'], $matches)) {
					$sep = '';
					for($i = 0; $i < count($matches[0]); $i++) {
						$concated = $matches[1][$i].($matches[2][$i] != '' ? '.'.$matches[2][$i] : '');
						$fields['qm'] .= $sep.$concated;
						$fields['qmsum'] += floatval($concated);
						$sep = ', ';
					}

					if(isset($fields['Verkehrswert'])) {
						$fields['eurProQm'] = round(i18nValueToValue($fields['Verkehrswert'])/$fields['qmsum']);
					}
				}

				$moreBox = findClassElement($dom, 'more-box')->item(0);
				if($moreBox) {
					$form = $moreBox->getElementsByTagName('form')->item(0);
					$fields['ZVGPortalLink'] = $form->ownerDocument->saveHTML($form);
				}
				else {
					$fields['ZVGPortalLink'] = null;
				}

				$hash = $fields['Zwangsversteigerung'];
				$object = RgetFromHash('object', $hash);
				if($object->hasChanged('hash')) {
					$fields['added_at'] = date('Y-m-d G:i:s');
					$object->import($fields);
					R::store($object);

					$fields['new'] = true;
				}

				if(isset($fields['qmsum'])) {
					unset($fields['qmsum']);
				}

				$table['content'][] = $fields;
				$subscription->ownObjectList[] = $object;
			}
		}

		R::store($subscription);

		$data[] = $table;
	}

	?>
	<!DOCTYPE html>
	<html>
	<head>
		<title>Zwangsversteigerungen-Übersicht</title>
		<style>
			html {
				font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
				color: #07c;
			}

			hr {
				border-color: #07c;
			}

			table td {
				color: black;
				border: 1px solid #07c;
			}

			.Zwangsversteigerung {
				width: 80px;
				overflow: hidden;
				display: inline-block;
				white-space: nowrap;
			}
		</style>
		<!-- DataTables CSS -->
		<link rel="stylesheet" type="text/css" href="bower_components/datatables/media/css/jquery.dataTables.css">

		<!-- jQuery -->
		<script type="text/javascript" charset="utf8" src="bower_components/jquery/dist/jquery.js"></script>

		<!-- DataTables -->
		<script type="text/javascript" charset="utf8" src="bower_components/datatables/media/js/jquery.dataTables.js"></script>
		<script>
			function addMap(query, obfuscatedQuery, id) {
				var apiKey = 'AIzaSyDAjZ928z9khTKRJb4siAFvCMh_RmjQ3nw';
				var backLink = '<a name="'+obfuscatedQuery+'" href="#backLink'+obfuscatedQuery+'">Zurück zu '+query+'</a>';
				var frameString = '<iframe width="100%" height="450" frameborder="0" style="border:0" src="https://www.google.com/maps/embed/v1/place?key='+apiKey+'&q='+encodeURIComponent(query.replace(/,/g, '').replace(/\s/g, '+'))+'&zoom=14"></iframe>';
				$('body').append('<div style="width: 80%; margin:auto;">'+backLink+frameString+'</div>');
			}

			var data = <?= json_encode($data) ?>;//[{"name":"Landgrafenstra\u00dfe 7, 10787 Berlin ","info":"Umkreis: \t\t\t4,5 km, Verkehrswert: \t\t\tzwischen 75.000 \u20ac und 120.000 \u20ac, Objektart: \t\t\tEigentumswohnung (1 bis 2 Zimmer), Eigentumswohnung (3 bis 4 Zimmer)","content":[{"Zwangsversteigerung":"0070 K 0014\/2014","Addresse":"\tRichard-Wagner-Str., Berlin","Objektart":"Eigentumswohnung (1 bis 2 Zimmer)","Verkehrswert":"104.000,00 EUR","Beschreibung":"Objektbeschreibung (keine Gew\u00e4hr f\u00fcr die Richtigkeit):\nEigentumswohnung Nr. W 19 in Richard-Wagner-Stra\u00dfe 7, 10585 Berlin,\ngelegen im 3. Obergeschoss postalisch Mitte links bzw. als mittlere\nWohnung innerhalb des Treppenhauses und bestehend aus 2 Zimmer mit\nK\u00fcche, Diele nebst Abstellnische, Bad und Wintergarten. Wegen aller\nEinzelheiten wird auf das hier ausliegende im Mai 2014 erstellte\nGutachten verwiesen.\nBaujahr: 1959 Wohnfl\u00e4che: 65,46 m\u00b2","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Charlottenburg\/0070-K-0014-2014","qm":"65.46","eurProQm":1589},{"Zwangsversteigerung":"0070 K 0057\/2014","Addresse":"\tZillestra\u00dfe, a., Berlin","Objektart":"Eigentumswohnung (2,5 Zimmer)","Verkehrswert":"85.000,00 EUR","Beschreibung":"Objektbeschreibung (keine Gew\u00e4hr f\u00fcr die Richtigkeit):\n2,5-Zimmer-Eigentumswohnung Nr. 18 in Zillestr. 44 \/ Krumme Str. 81a,\n10585 Berlin, gelegen im 5. OG links, des 6-geschossigen und voll\nunterkellerten Wohnhauses und bestehend aus 2,5 Zimmer mit K\u00fcche, Bad,\nFlur, Balkon, Abstellkammer. Wegen aller Einzelheiten wird auf das hier\nausliegende und im September 2014 erstellte Gutachten verwiesen.\nBaujahr: 1955 Wohnfl\u00e4che: 61,93 m\u00b2","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Charlottenburg\/0070-K-0057-2014","qm":"61.93","eurProQm":1373},{"Zwangsversteigerung":"0030 K 0033\/2013","Addresse":"\tWichmannstra\u00dfe,, Berlin, Tiergarten","Objektart":"Eigentumswohnung (1 bis 2 Zimmer)","Verkehrswert":"92.000,00 \u20ac","Beschreibung":"2 Zimmer, K\u00fcche, Diele, Bad und Terrasse (ca. 68,73 m\u00b2 gro\u00df, zum Zeitpunkt der Begutachtung vermietet), belegen im EG (Souterrain) rechts des Geb\u00e4udes Wichmannstra\u00dfe 10 (als Teil zweier 7-geschossigen Mehrfamilienwohnh\u00e4user), aufgeteilt in insgesamt 45 MEA (BJ um 1977).\nHinsichtlich der Objektbeschreibung wird auf das Verkehrswertgutachten vom 16.08.2013 Bezug genommen.","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Mitte\/0030-K-0033-2013","qm":"68.73","eurProQm":1339},{"Zwangsversteigerung":"0030 K 0033\/2013","Addresse":"\tWichmannstra\u00dfe,, Berlin, Tiergarten","Objektart":"Eigentumswohnung (1 bis 2 Zimmer)","Verkehrswert":"92.000,00 \u20ac","Beschreibung":"2 Zimmer, K\u00fcche, Diele, Bad und Terrasse (ca. 68,73 m\u00b2 gro\u00df, zum Zeitpunkt der Begutachtung vermietet), belegen im EG (Souterrain) rechts des Geb\u00e4udes Wichmannstra\u00dfe 10 (als Teil zweier 7-geschossigen Mehrfamilienwohnh\u00e4user), aufgeteilt in insgesamt 45 MEA (BJ um 1977).\nHinsichtlich der Objektbeschreibung wird auf das Verkehrswertgutachten vom 16.08.2013 Bezug genommen.","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Mitte\/0030-K-0033-2013","qm":"68.73","eurProQm":1339},{"Zwangsversteigerung":"0030 K 0052\/2014","Addresse":"\tLindenstr., Berlin","Objektart":"Eigentumswohnung (1 bis 2 Zimmer)","Verkehrswert":"87.000,00","Beschreibung":"Es handelt sich um eine vermietete 1 Zimmerwohnung mit K\u00fcche, Bad, Flur, Loggia und Abstellkammer. Die Wohnfl\u00e4che betr\u00e4gt 39,90m\u00b2. Die Wohnung ist im 2. OG. rechts gelegen.","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Tempelhof-Kreuzberg\/0030-K-0052-2014","qm":"39.90","eurProQm":2180},{"Zwangsversteigerung":"0070 K 0075\/2014","Addresse":"\tRognitzstr.-, Berlin","Objektart":"Eigentumswohnung (3 bis 4 Zimmer)","Verkehrswert":"100.000,00 EUR","Beschreibung":"Objektbeschreibung (keine Gew\u00e4hr f\u00fcr die Richtigkeit):\nEigentumswohnung Nr. 73 in Rognitzstra\u00dfe 17, 14059 Berlin,\ngelegen im 7. Obregeschoss links vorne und bestehend aus 3\nZimmer mit K\u00fcche, Bad, Flur und Balkon. Ferner ist das Sondernutzungsrecht\nan dem Kfz-Stellplatz S 65 in der Tiefgarage\nzugeordnet. Wegen aller Einzelheiten wird auf das hier\nausliegende im Oktober 2014 erstellte Gutachten verwiesen.\nBaujahr: ca. 1972 Wohnfl\u00e4che: 64,22 m\u00b2","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Charlottenburg\/0070-K-0075-2014","qm":"64.22","eurProQm":1557},{"Zwangsversteigerung":"0070 K 0024\/2014","Addresse":"\tRognitzstr.-, Berlin","Objektart":"Eigentumswohnung (3 bis 4 Zimmer)","Verkehrswert":"115.000,00 EUR","Beschreibung":"Objektbeschreibung (keine Gew\u00e4hr f\u00fcr die Richtigkeit):\nEigentumswohnung Nr. 12 in Rognitzstra\u00dfe 17, 14059 Berlin,\ngelegen im 1. Obergeschoss links vorne und bestehend aus\n3 Zimmer mit K\u00fcche, Bad, Flur, Abstellraum und Balkon. Ferner\nist das Sondernutzungsrecht am dem PKW-Stellplatz S 30 in\nder Tiefgarage zugeordnet. Wegen aller Einzelheiten wird auf\ndas hier ausliegende im Juli 2014 erstellte Gutachten verwiesen.\nBaujahr: 1972 Wohnfl\u00e4che: 71,69 m\u00b2","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Charlottenburg\/0070-K-0024-2014","qm":"71.69","eurProQm":1604},{"Zwangsversteigerung":"0070 K 0082\/2014","Addresse":"\tWeimarer Str., Berlin","Objektart":"Eigentumswohnung (1 bis 2 Zimmer)","Verkehrswert":"82.000,00 EUR","Beschreibung":"Objektbeschreibung (keine Gew\u00e4hr f\u00fcr die Richtigkeit):\nEigentumswohnung Nr. 4 in Weimarer Stra\u00dfe 28, 10625 Berlin,\ngelegen im Erdgeschoss rechts (von der Stra\u00dfe aus gesehen)\nund bestehend aus einem Zimmer, Flur mit Kammer, Bad und\nK\u00fcche. Wegen der Einzelheiten wird auf das hier ausliegende\nim September 2013 erstellte Gutachten verwiesen.\nBaujahr: ca. 1902 Wohnfl\u00e4che: 55,33 m\u00b2","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Charlottenburg\/0070-K-0082-2014","qm":"55.33","eurProQm":1482},{"Zwangsversteigerung":"0076 K 0128\/2013","Addresse":"\tWilhelm-Hauff-Stra\u00dfe, Berlin, Sch\u00f6neberg","Objektart":"Eigentumswohnung (1 bis 2 Zimmer), sonstiges Teileigentum (z.B. Keller, Hobbyraum)","Verkehrswert":"98.000,00","Beschreibung":"Die vermietete Wohnung ist im Erdgeschoss links gelegen und besteht bei einer Wohnfl\u00e4che von ca. 72 m\u00b2 aus 2 Zimmern, K\u00fcche, Wannenbad Diele und Balkon.\nEin Kellerraum ist schuldrechtlich zugeordnet. (Ohne Gew\u00e4hr).\n Betrifft Gl\u00e4ubiger zu 2) s.u. - AZ: KHM2011356-","url":"http:\/\/www.zwangsversteigerung.eu\/Deutschland\/Berlin\/AG-Sch%C3%B6neberg\/0076-K-0128-2013","qm":"72","eurProQm":1361}]},{"name":"Chausseestr. 61 10115 Berlin","info":"Umkreis: \t\t\t3,0 km, Verkehrswert: \t\t\tzwischen 75.000 \u20ac und 125.000 \u20ac, Objektart: \t\t\tEigentumswohnung (1 bis 2 Zimmer), Eigentumswohnung (3 bis 4 Zimmer)","content":"No detail link found for \"\/subscriptions\/5373\""}];

			//$fields = ['Umkreis', 'Zwangsversteigerung', 'Adresse', 'Addresse', 'Objektart', 'Verkehrswert', 'Beschreibung'];
			var columns = [
				{
					"title": "VersteigerungsID",
					"data": "Zwangsversteigerung",
					"render": function( data, type, row, meta ) {
						return '<a href="'+row['url']+'">'+data+'</a>';
					}
				},
				{
					"title": "Adresse",
					"data": "Adresse",
					"render": function( data, type, row, meta ) {
						if(data) {
							data = data.trim();
							name = data.replace(/[^a-zA-Z0-9-_]/g, '');
							return '<a href="#'+name+'" name="backLink'+name+'">'+data+'</a>';
						}
						return "";
					}
				},
				{
					"title": "Objektart",
					"data": "Objektart"
				},
				{
					"title": "Verkehrswert",
					"data": "Verkehrswert"
				},
				{
					"title": "Beschreibung",
					"data": "Beschreibung"
				},
				{
					"title": "m²",
					"data": "qm",
					"defaultContent": "0"
				},
				{
					"title": "€/m²",
					"data": "eurProQm",
					"defaultContent": "0"
				},
				{
					"title": "ZVG-Portal",
					"data": "ZVGPortalLink"
				}
			];

			var createdRowCallback = function( row, data, dataIndex ) {
				if(data['Adresse']) {
					var query = data['Adresse'].trim();
					var name = query.replace(/[^a-zA-Z0-9-_]/g, '');
					addMap(query, name);
				}
			}

			$(document).ready(function(){
				data.forEach(function(d) {
					var div = $('<div></div>');
					$("body").append(div);
					div.append('<h1>'+d['name']+'</h1><span>'+d['info']+'</span><hr>');

					if(d['content'].constructor === String) {
						div.append(d['content']);
					}
					else {
						var table = $('<table class="datatable"><thead></thead><tbody></tbody></table>');
						div.append(table);

						table.DataTable({
							paging: false,
							data: d['content'],
							columns: columns,
							createdRow: createdRowCallback
						});
					}
				});
			});
		</script>
	</head>
	<body>

	</body>
	</html>

<?php
}

main();