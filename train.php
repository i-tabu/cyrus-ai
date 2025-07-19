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

function train($file, $trust = 1, $max_layer = 100) {
    $ssdb = getSSDB();

    $handle = fopen($file, "r");
    if (!$handle) {
        echo "Failed to open $file\n";
        exit(1);
    }

    $count = 0;
    while (($line = fgets($handle)) !== false) {
        $line = strtolower(trim($line));
        $length = strlen($line);
        for ($i = 0; $i < $length; $i++) {
            for ($l = 1; $l <= $max_layer; $l++) {
                if ($i + $l > $length) break;
                $seq = substr($line, $i, $l);
                $key = "cyrus_l$l";
                $ssdb->zincr($key, $seq, $trust);
            }
        }

        if (++$count % 1000 === 0) {
            echo "Processed $count lines...\n";
        }
    }

    fclose($handle);
    echo "Training complete on $file\n";
}

// --- Entry Point ---
$args = $argv;
$trust = 1;

if (count($args) < 2) {
    echo "Usage: php train.php <corpus.txt> [--trust=10]\n";
    exit;
}

if (isset($args[2]) && preg_match('/--trust=(\d+)/', $args[2], $m)) {
    $trust = intval($m[1]);
}

train($args[1], $trust);

