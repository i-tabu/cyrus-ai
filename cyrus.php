<?php

function train($corpus, $max_layer = 100, $chunk_size = 10000, $min_score = 2) {
    $layers = array_fill(0, $max_layer, []);

    $length = strlen($corpus);
    for ($i = 0; $i < $length; $i++) {
        for ($l = 1; $l <= $max_layer; $l++) {
            if ($i + $l > $length) break;
            $seq = substr($corpus, $i, $l);
            if (!isset($layers[$l - 1][$seq])) {
                $layers[$l - 1][$seq] = 1;
            } else {
                $layers[$l - 1][$seq]++;
            }
        }

        // prune every chunk_size steps
        if ($i > 0 && $i % $chunk_size == 0) {
            foreach ($layers as $li => &$layer) {
                foreach ($layer as $key => $count) {
                    if ($count < $min_score) {
                        unset($layer[$key]);
                    }
                }
            }
        }
    }

    return $layers;
}

function save_layers($layers, $dir = "layers") {
    if (!is_dir($dir)) mkdir($dir);
    foreach ($layers as $i => $layer) {
        file_put_contents("$dir/layer_" . ($i + 1) . ".json", json_encode($layer));
    }
}

function load_layers($dir = "layers", $max_layer = 100) {
    $layers = [];
    for ($i = 1; $i <= $max_layer; $i++) {
        $file = "$dir/layer_$i.json";
        if (file_exists($file)) {
            $layers[] = json_decode(file_get_contents($file), true);
        } else {
            break;
        }
    }
    return $layers;
}

function predict($layers, $prefix, $max_len = 100) {
    $current = $prefix;

    while (strlen($current) < $max_len) {
        $layer_idx = strlen($current);
        if (!isset($layers[$layer_idx])) break;

        $layer = $layers[$layer_idx];
        $candidates = [];

        foreach ($layer as $k => $v) {
            if (str_starts_with($k, $current)) {
                $candidates[$k] = $v;
            }
        }

        if (empty($candidates)) break;

        // get best match
        arsort($candidates);
        $next_seq = array_key_first($candidates);
        $current = $next_seq;
    }

    return $current;
}

// === RUN CYRUS ===

$corpus = file_get_contents("corpus.txt");
$corpus = strtolower($corpus);

// Train & Save
$layers = train($corpus, 100);
save_layers($layers);

// Predict
$layers = load_layers();
echo "Cyrus is ready. Type a prefix:\n";
$handle = fopen("php://stdin", "r");
while (true) {
    echo "Cyrus> ";
    $line = trim(fgets($handle));
    if ($line === "exit") break;
    $result = predict($layers, $line, 100);
    echo "Cyrus says: $result\n";
}

