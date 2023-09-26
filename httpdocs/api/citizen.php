<?php
require_once '../../php/database.php';
require_once '../../php/endorsements.php';

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (isset($_POST['key']))
  $condition = "publication.`key`=FROM_BASE64('" . $mysqli->escape_string($_POST['key']) . "')";
else if (isset($_POST['fingerprint']))
  $condition = "SHA1(publication.signature)='" . $mysqli->escape_string($_POST['fingerprint']) . "'";
else
  die("{\"error\":\"missing key or fingerprint POST argument\"}");
$query = "SELECT TO_BASE64(publication.`key`) AS `key`, UNIX_TIMESTAMP(publication.published), TO_BASE64(publication.signature) AS signature, "
        ."citizen.familyName, citizen.givenNames, CONCAT('data:image/jpeg;base64,', TO_BASE64(citizen.picture)) AS picture, "
        ."ST_Y(citizen.home) AS latitude, ST_X(citizen.home) AS longitude "
        ."FROM publication INNER JOIN citizen ON publication.id = citizen.id "
        ."WHERE $condition";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
$citizen = $result->fetch_assoc() or die("{\"error\":\"citizen not found: $condition\"}");
$result->free();
settype($citizen['published'], 'int');
settype($citizen['latitude'], 'float');
settype($citizen['longitude'], 'float');
$endorsements = endorsements($mysqli, $citizen['key']);
$query = "SELECT TO_BASE64(pc.signature) AS signature, UNIX_TIMESTAMP(pe.published) AS published, e.`revoke`, "
        ."c.familyName, c.givenNames, CONCAT('data:image/jpeg;base64,', TO_BASE64(c.picture)) AS picture "
        ."FROM publication pe "
        ."INNER JOIN endorsement e ON e.id = pe.id "
        ."INNER JOIN publication pc ON pc.`key` = pe.`key` "
        ."INNER JOIN citizen c ON pc.id = c.id "
        ."WHERE e.endorsedSignature = FROM_BASE64('$citizen[signature]') AND e.latest = 1 "
        ."ORDER BY pe.published DESC";
$result = $mysqli->query($query) or die("{\"error\":\"$mysqli->error\"}");
if (!$result)
  die("{\"error\":\"$mysqli->error\"}");
$citizen_endorsements = array();
while($e = $result->fetch_assoc()) {
  settype($e['published'], 'int');  
  $e['revoke'] = (intval($e['revoke']) == 1);
  settype($e['latitude'], 'float');
  settype($e['longitude'], 'float');
  $citizen_endorsements[] = $e;
}
$result->free();
$mysqli->close();
$answer = array();
$answer['citizen'] = $citizen;
$answer['endorsements'] = $endorsements;
$answer['citizen_endorsements'] = $citizen_endorsements;
die(json_encode($answer, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
?>
