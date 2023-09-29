<?php

require_once '../../php/database.php';

function error($message) {
  if ($message[0] != '{')
    $message = '"'.$message.'"';
  die("{\"error\":$message}");
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

if (!isset($_GET['fingerprint']))
  error("Missing fingerprint parameter");

if (isset($_GET['corpus']))
  $corpus = ($_GET['corpus'] === '1');
else
  $corpus = false;

$fingerprint = $mysqli->escape_string($_GET['fingerprint']);
$query = "SELECT title FROM proposal "
        ."INNER JOIN publication ON publication.id=proposal.id AND SHA1(REPLACE(TO_BASE64(publication.signature), '\\n', ''))='${fingerprint}'";
$result = $mysqli->query($query) or error($query . " - " . $mysqli->error);
$title = $result->fetch_assoc();
$result->free();
if (!$title)
  error("Proposal not found");
$answer = array();
$answer['title'] = $title['title'];
$query = "SELECT pc.signature, citizen.givenNames, citizen.familyName, CONCAT('data:image/jpeg;base64,', REPLACE(TO_BASE64(citizen.picture), '\\n', '')) AS picture";
if (!$corpus)
  $query .= ", UNIX_TIMESTAMP(ps.published) AS published";
$query .= " FROM citizen"
         ." INNER JOIN publication AS pc ON pc.id=citizen.id"
         ." INNER JOIN publication AS pp ON SHA1(pp.signature)='$fingerprint'"
         ." INNER JOIN proposal ON proposal.id=pp.id";
if ($corpus)
  $query .= " INNER JOIN webservice AS judge ON judge.`type`='judge' AND judge.url=proposal.judge"
         ." INNER JOIN publication AS pe ON pe.`key`=judge.`key`"
         ." INNER JOIN endorsement ON endorsement.id=pe.id AND endorsement.latest=1 AND endorsement.endorsedSignature=pc.signature"
         ." INNER JOIN publication AS pa ON proposal.area=pa.`signature`"
         ." INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home)))"
         ." WHERE endorsement.`revoke`=0 OR (endorsement.`revoke`=1 AND"
         ." EXISTS(SELECT pep.id FROM publication AS pep"
         ." INNER JOIN endorsement AS e ON e.id=pep.id AND SHA1(e.endorsedSignature)='$fingerprint' AND e.accepted=1"
         ." WHERE pep.`key`=pc.`key`))";
else
  $query .= " INNER JOIN endorsement AS signature ON SHA1(signature.endorsedSignature)='$fingerprint' AND signature.accepted=1"
           ." INNER JOIN publication AS ps ON ps.id=signature.id AND ps.`key`=pc.`key`";
$query .= " ORDER BY citizen.familyName, citizen.givenNames";

$result = $mysqli->query($query) or error($mysqli->error);
$participants = array();
while ($participant = $result->fetch_assoc()) {
  if ($corpus)
    settype($participant['published'], 'int');
  $participants[] = $participant;
}
$result->free();
if ($corpus) {
  $count = sizeof($participants);
  $query = "UPDATE proposal "
         ." INNER JOIN publication ON publication.id=proposal.id AND SHA1(REPLACE(TO_BASE64(publication.signature), '\\n', ''))='$fingerprint'"
         ." SET corpus=$count";
  $mysqli->query($query) or error($mysqli->error);
}
$answer['participants'] = $participants;
echo json_encode($answer, JSON_UNESCAPED_SLASHES);
$mysqli->close();
?>