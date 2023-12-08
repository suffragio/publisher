<?php

require_once '../../vendor/autoload.php';
require_once '../../php/database.php';
require_once '../../php/endorsements.php';
require_once '../../php/corpus.php';
require_once '../../php/sanitizer.php';
require_once '../../php/public_key.php';
require_once '../../php/blind-sign.php';

$PRODUCTION_APP_KEY = // public key of the genuine app
  'vD20QQ18u761ean1+zgqlDFo6H2Emw3mPmBxeU24x4o1M2tcGs+Q7G6xASRf4LmSdO1h67ZN0sy1tasNHH8Ik4CN63elBj4ELU70xZeYXIMxxxDqis'.
  'FgAXQO34lc2EFt+wKs+TNhf8CrDuexeIV5d4YxttwpYT/6Q2wrudTm5wjeK0VIdtXHNU5V01KaxlmoXny2asWIejcAfxHYSKFhzfmkXiVqFrQ5BHAf'.
  '+/ReYnfc+x7Owrm6E0N51vUHSxVyN/TCUoA02h5UsuvMKR4OtklZbsJjerwz+SjV7578H5FTh0E0sa7zYJuHaYqPevvwReXuggEsfytP/j2B3IgarQ';
$TEST_APP_KEY = // public key of the test app
  'nRhEkRo47vT2Zm4Cquzavyh+S/yFksvZh1eV20bcg+YcCfwzNdvPRs+5WiEmE4eujuGPkkXG6u/DlmQXf2szMMUwGCkqJSPi6fa90pQKx81QHY8Ab4'.
  'z69PnvBjt8tt8L8+0NRGOpKkmswzaX4ON3iplBx46yEn00DQ9W2Qzl2EwaIPlYNhkEs24Rt5zQeGUxMGHy1eSR+mR4Ngqp1LXCyGxbXJ8B/B5hV4QI'.
  'or7U2raCVFSy7sNl080xNLuY0kjHCV+HN0h4EaRdR2FSw9vMyw5UJmWpCFHyQla42Eg1Fxwk9IkHhNe/WobOT1Jiy3Uxz9nUeoCQa5AONAXOaO2wtQ';

use Opis\JsonSchema\{
  Validator, Errors\ErrorFormatter
};

function get_type($schema) {
  $p = strrpos($schema, '/', 13);
  return substr($schema, $p + 1, strlen($schema) - $p - 13);  # remove the .schema.json suffix
}

function check_app($publication, $vote=false) {
  global $mysqli;

  $appKey = sanitize_field($publication->appKey, 'base64', 'appKey');
  $result = $mysqli->query("SELECT id FROM webservice WHERE `type`='app' and `key`=FROM_BASE64('$appKey==')");
  if ($result->num_rows === 0)
    error("unknown app");
  $appSignature = sanitize_field($publication->appSignature, 'base64', 'appSignature');
  $publication->appSignature = '';
  if ($vote) {
    $voteBytes = base64_decode("$publication->referendum==");
    $voteBytes .= pack('J', $publication->number);
    $voteBytes .= base64_decode("$publication->ballot");
    $voteBytes .= $publication->answer;
    $publicKey = openssl_pkey_get_public(public_key($appKey));
    $details = openssl_pkey_get_details($publicKey);
    $n = gmp_import($details['rsa']['n'], 1, GMP_BIG_ENDIAN | GMP_MSW_FIRST);
    $e = gmp_import($details['rsa']['e'], 1, GMP_BIG_ENDIAN | GMP_MSW_FIRST);
    $error = blind_verify($n, $e, $voteBytes, base64_decode("$appSignature=="));
    if ($error !== '')
      error("failed to verify app signature: $error");
  } else {
    $verify = openssl_verify($publication->signature, base64_decode("$appSignature=="), public_key($appKey), OPENSSL_ALGO_SHA256);
    if ($verify != 1) {
      $type = get_type(sanitize_field($publication->schema, 'url', 'schema'));
      error("wrong app signature for $type: $appKey");
    }
  }
  # restore original signature
  $publication->appSignature = $appSignature;
  return array($appKey, $appSignature);
}

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: content-type");

$publication = json_decode(file_get_contents("php://input"));
if (!$publication)
  error("unable to parse JSON post");
if (!isset($publication->schema))
  error("unable to read schema field");
$schema = sanitize_field($publication->schema, 'url', 'schema');
$key = sanitize_field($publication->key, 'base64', 'key');
$published = sanitize_field($publication->published, 'positive_int', 'published');
$signature = sanitize_field($publication->signature, 'base64', 'signature');
if (isset($publication->blindKey))
  $blindKey = sanitize_field($publication->blindKey, 'base64', 'signature');
if (isset($publication->encryptedVote))
  $encryptedVote = sanitize_field($publication->encryptedVote, 'base64', 'signature');

# validate from json-schema
$schema_file = file_get_contents($schema);
$validator = new Validator();
$result = $validator->validate($publication, $schema_file);
if (!$result->isValid()) {
  $error = $result->error();
  $formatter = new ErrorFormatter();
  error(implode('. ', $formatter->formatFlat($error)) . '.');
}

# check field order (important for signature)
$schema_json = json_decode($schema_file, true);
$properties = array_keys((array)$schema_json['properties']);
$keys = array_keys((array)$publication);
$property_counter = 0;
$property_count = count($properties);
$count = count($keys);
$break = false;
for($i = 0; $i < $count; $i++) {
  while ($properties[$property_counter++] !== $keys[$i]) {
    if ($property_counter >= $property_count) {
      $break = true;
      break;
    }
  }
  if ($break)
    break;
}
if ($break && $i < $count)
  error("wrong property order for '$keys[$i]' property");
 
$now = time();  # UNIX time stamp (seconds)
$type = get_type($schema);
if ($type !== 'vote' && $published > $now + 60)  # allowing a 1 minute (60 seconds) error
  error("publication date in the future for $type: $published > $now");
if ($type === 'citizen') {
  $citizen = &$publication;
  $citizen_picture = substr($citizen->picture, strlen('data:image/jpeg;base64,'));
  $data = base64_decode($citizen_picture);
  try {
    $size = @getimagesizefromstring($data);
    if ($size['mime'] != 'image/jpeg')
      error("wrong picture MIME type: '$size[mime]' (expecting 'image/jpeg')");
    if ($size[0] != 150 || $size[1] != 200)
      error("wrong picture size: $size[0]x$size[1] (expecting 150x200)");
  } catch(Exception $e) {
    error("cannot determine picture size");
  }
}

$publication->signature = '';
if ($type !== 'vote' && isset($publication->appSignature)) {
  $appSignature = $publication->appSignature;
   $publication->appSignature = '';
}
$data = json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$verify = openssl_verify($data, base64_decode("$signature=="), public_key($key), OPENSSL_ALGO_SHA256);
if ($verify != 1)
  error("wrong signature for $type: key=$key\n" . json_encode($publication, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
# restore original signatures if needed
$publication->signature = $signature;
if ($type !== 'vote' && isset($appSignature))
  $publication->appSignature = $appSignature;

$version = intval(explode('/', $schema)[4]);
$query = "INSERT IGNORE INTO publication(`version`, `type`, `key`, signature, published) "
        ."VALUES($version, '$type', FROM_BASE64('$key=='), FROM_BASE64('$signature=='), FROM_UNIXTIME($published))";
$mysqli->query($query) or error($mysqli->error);
if ($mysqli->affected_rows === 0)
  error("already existing publication");
$id = $mysqli->insert_id;

if ($type === 'citizen') {
  list($appKey, $appSignature) = check_app($citizen);
  $familyName = $mysqli->escape_string($publication->familyName);
  $givenNames = $mysqli->escape_string($publication->givenNames);
  $latitude = sanitize_field($citizen->latitude, 'float', 'latitude');
  $longitude = sanitize_field($citizen->longitude, 'float', 'longitude');
  $query = "INSERT INTO citizen(id, appKey, appSignature, familyName, givenNames, picture, home) "
          ."VALUES($id, FROM_BASE64('$appKey=='), FROM_BASE64('$appSignature=='), \"$familyName\", \"$givenNames\", "
          ."FROM_BASE64('$citizen_picture'), POINT($longitude, $latitude))";
} elseif ($type === 'endorsement') {
  $endorsement = &$publication;
  if (isset($endorsement->appKey))
    list($appKey, $appSignature) = check_app($endorsement);
  else {
    $appKey = '';
    $appSignature = '';
  }
  if (!property_exists($endorsement, 'revoke'))
    $endorsement->revoke = false;
  if (!property_exists($endorsement, 'message'))
    $endorsement->message = '';
  if (!property_exists($endorsement, 'comment'))
    $endorsement->comment = '';
  $endorsedSignature = sanitize_field($endorsement->endorsedSignature, 'base64', 'endorsedSignature');
  $query = "SELECT id, `type`, REPLACE(REPLACE(TO_BASE64(signature), '\\n', ''), '=', '') AS signature FROM publication "
          ."WHERE signature = FROM_BASE64('$endorsedSignature==')";
  $result = $mysqli->query($query) or error($mysqli->error);
  $endorsed = $result->fetch_assoc();
  $result->free();
  if (!$endorsed)
    error("endorsed signature not found: $endorsedSignature");
  if ($endorsed['signature'] != $endorsedSignature)
    error("endorsed signature mismatch.");
  # mark other endorsements of the same participant by the same endorser as not the latest
  $mysqli->query("UPDATE endorsement INNER JOIN publication ON publication.id = endorsement.id"
                ." SET endorsement.latest = 0"
                ." WHERE endorsement.endorsedSignature = FROM_BASE64('$endorsedSignature==')"
                ." AND publication.`key` = FROM_BASE64('$key==')") or error($mysli->error);
  if ($endorsed['type'] == 'proposal') {  # signing a petition
    # increment the number of participants in a petition if the citizen is located inside the petition area and is endorsed by the petition judge
    $endorsed_id = $endorsed['id'];
    $query = "UPDATE proposal "
            ."INNER JOIN publication AS pc ON pc.`key`=FROM_BASE64('$key==') "
            ."INNER JOIN citizen ON citizen.id=pc.id "
            ."INNER JOIN publication AS pa ON pa.`signature`=proposal.area "
            ."INNER JOIN area ON area.id=pa.id AND ST_Contains(area.polygons, POINT(ST_X(citizen.home), ST_Y(citizen.home))) "
            ."INNER JOIN publication AS pp ON pp.id=proposal.id "
            ."INNER JOIN webservice AS judge ON judge.`type`='judge' AND judge.`key`=pp.`key` "
            ."INNER JOIN publication AS pe ON pe.`key`=pp.`key` "
            ."INNER JOIN endorsement ON endorsement.id = pe.id AND endorsement.`revoke`=0 AND endorsement.latest=1 AND endorsement.endorsedSignature=pc.signature "
            ."SET participants=participants+1 "
            ."WHERE proposal.id=$endorsed_id AND proposal.`secret`=0";
    $mysqli->query($query) or error($msqli->error);
    $accepted = $mysqli->affected_rows;
  } else
    $accepted = 0;
  $revoke = $endorsement->revoke ? 1 : 0;
  $message = $mysqli->escape_string($endorsement->message);
  $comment = $mysqli->escape_string($endorsement->comment);
  if ($appKey) {
    $appFields = " appKey, appSignature,";
    $appValues = " FROM_BASE64('$appKey=='), FROM_BASE64('$appSignature=='),";
  } else {
    $appFields = '';
    $appValues = '';
  }
  $query = "INSERT INTO endorsement(id,$appFields `revoke`, `message`, comment, endorsedSignature, latest, accepted) "
          ."VALUES($id,$appValues $revoke, \"$message\", \"$comment\", "
          ."FROM_BASE64('$endorsedSignature=='), "
          . "1, $accepted)";
} elseif ($type === 'proposal') {
  $proposal =&$publication;
  if (!isset($proposal->website))  # optional
    $website = '';
  else
    $website = sanitize_field($publication->website, 'url', 'website');

  if (!isset($proposal->question))  # optional
    $question = '';
  else
    $question = $mysqli->escape_string($publication->question);

  if (!isset($proposal->answers))  # optional
    $answers = array();
  else
    $answers = $publication->answers;
  $answers = implode("\n", $answers);
  $answers = $mysqli->escape_string($answers);
  $secret = ($proposal->secret) ? 1 : 0;
  $area = sanitize_field($publication->area, 'base64', 'area');
  $title = $mysqli->escape_string($publication->title);
  $description = $mysqli->escape_string($publication->description);
  $deadline = sanitize_field($publication->deadline, 'positive_int', 'deadline');
  $query = "INSERT INTO proposal(id, area, title, description, question, answers, secret, deadline, website, participants, corpus) "
          ."VALUES($id, FROM_BASE64('$area=='), \"$title\", \"$description\", "
          ."\"$question\", \"$answers\", $secret, FROM_UNIXTIME($deadline), \"$website\", 0, 0)";
} elseif ($type === 'participation') {
  $participation =&$publication;
  list($appKey, $appSignature) = check_app($participation);
  $query = "INSERT INTO participation(id, appKey, appSignature, referendum, encryptedVote) "
          ."VALUES($id, FROM_BASE64('$appKey=='), FROM_BASE64('$appSignature=='), FROM_BASE64('$participation->referendum=='), FROM_BASE64('$encryptedVote'))";
} elseif ($type === 'vote') {
  $vote = &$publication;
  list($appKey, $appSignature) = check_app($vote, true);
  $referendum = sanitize_field($vote->referendum, 'base64', 'referendum');
  $number = sanitize_field($vote->number, 'positive_int', 'number');
  $ballot = sanitize_field($vote->ballot, 'base64', 'ballot');
  $answer = $mysqli->escape_string($vote->answer);
  $query = "INSERT INTO vote(id, appKey, appSignature, referendum, number, ballot, answer) VALUES($id, "
          ."FROM_BASE64('$appKey=='), "
          ."FROM_BASE64('$appSignature=='), "
          ."FROM_BASE64('$referendum=='), "
          ."$number, "
          ."FROM_BASE64('$ballot'), "
          ."\"$answer\") "
          ."ON DUPLICATE KEY UPDATE appSignature=FROM_BASE64('$appSignature=='), number=$number, answer=\"$answer\";";
} elseif ($type === 'area') {
  $polygons = 'ST_GeomFromText("MULTIPOLYGON(';
  $t1 = false;
  foreach($publication->polygons as $polygon1) {
    if ($t1)
      $polygons .= ', ';
    $polygons .= '(';
    $t1 = true;
    $t2 = false;
    foreach($polygon1 as $polygon2) {
      if ($t2)
        $polygons .= ', ';
      $polygons .= '(';
      $t2 = true;
      $t3 = false;
      foreach($polygon2 as $coordinates) {
        if ($t3)
          $polygons .= ', ';
        $t3 = true;
        $polygons .= $coordinates[0] . ' ' . $coordinates[1];
      }
      $polygons .= ')';
    }
    $polygons .= ')';
  }
  $polygons .= ')")';
  $name = implode("\n", $publication->name);
  $name = $mysqli->escape_string($name);
  $query = "INSERT INTO area(id, name, polygons) VALUES($id, \"$name\", $polygons)";
} else
  error("unknown publication type.");
$mysqli->query($query) or error($mysqli->error);
if ($type === 'proposal')
  update_corpus($mysqli, $id);
if ($type === 'vote') {
  $query = "SELECT id FROM publication WHERE signature=FROM_BASE64('$referendum==')";
  $i = $mysqli->query($query) or error($mysqli->error);
  $j = $i->fetch_assoc();
  $id = intval($j['id']);
  $query = "INSERT INTO results(referendum, answer, `count`) VALUES($id, \"$answer\", 1) "
          ."ON DUPLICATE KEY UPDATE `count`=`count`+1";
  $mysqli->query($query) or error($mysqli->error);
  $query = "UPDATE proposal SET participants=participants+1 WHERE id=$id";
  $mysqli->query($query) or error($mysqli->error);
}
if ($type === 'endorsement')
  echo json_encode(endorsements($mysqli, $key), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
else
  echo("{\"signature\":\"$signature\"}");
$mysqli->close();
?>
