<?php

require_once("bootstrap.php");

function getSSDB() {
    $config = json_decode(file_get_contents("config.json"), true);
    $ssdb = new SimpleSSDB($config['ssdb_host'], $config['ssdb_port']);
    if (!empty($config['ssdb_auth'])) {
        $ssdb->auth($config['ssdb_auth']);
    }
    return $ssdb;
}

function parse_input($line) {
    $line = preg_replace('/\\\\#/', '<<ESC_HASH>>', $line);
    $line = preg_replace('/\\\\\\\\/', '<<ESC_BACK>>', $line);
    $rand = 0;
    if (preg_match('/#rand=(\d+)/', $line, $m)) {
        $rand = intval($m[1]);
        $line = preg_replace('/#rand=\d+/', '', $line);
    }
    $line = str_replace('<<ESC_HASH>>', '#', $line);
    $line = str_replace('<<ESC_BACK>>', '\\', $line);
    return [trim($line), $rand];
}

function predict_ssdb($ssdb, $prefix, $max_len = 100, $stop_chars = ['.', '?', '!'], $rand = 0) {
    $current = $prefix;

    while (strlen($current) < $max_len) {
        $layer_idx = strlen($current) + 1; // layer names are 1-based
        $layer_key = "cyrus_l$layer_idx";

        //$res = $ssdb->zscan($layer_key, $current, $current . "z", 1000); // get all with matching prefix
        $res = $ssdb->zscan($layer_key, $current, "", "", 1000); // empty end = till end

        if (!$res || count($res) === 0) break;

        // Filter exact matches
        $matches = [];
        foreach ($res as $k => $v) {
            if (str_starts_with($k, $current)) {
                $matches[$k] = $v;
            }
        }

        if (empty($matches)) break;

        arsort($matches); // sort by score descending
        $keys = array_keys($matches);
        $index = 0;

        if ($rand > 0) {
            $index = rand(0, min($rand, count($keys) - 1));
        }

        $next_seq = $keys[$index];
        $current = $next_seq;

        $last_char = substr($current, -1);
        if (in_array($last_char, $stop_chars)) break;
    }

    return $current;
}

// ==== Run Chat ====

$ssdb = getSSDB();
echo "Cyrus is listening...\n";

$stdin = fopen("php://stdin", "r");
while (true) {
    echo "You> ";
    $line = trim(fgets($stdin));
    if ($line === "exit" || $line == "quit") break;

    [$prefix, $rand] = parse_input($line);
    $response = predict_ssdb($ssdb, strtolower($prefix), 100, ['.', '?', '!'], $rand);
    echo "Cyrus> $response\n";
}

