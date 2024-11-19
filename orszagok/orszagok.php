<?php

$servername = "localhost";
$username = "root";
$password = ""; 
$dbname = "orszag";

$conn = new mysqli($servername, $username, $password);

if ($conn->connect_error) {
    die("Kapcsolódási hiba: " . $conn->connect_error);
}

$sql = "CREATE DATABASE IF NOT EXISTS $dbname";
if ($conn->query($sql) === TRUE) {
    echo "Adatbázis sikeresen létrehozva.\n";
} else {
    echo "Hiba az adatbázis létrehozásában: " . $conn->error;
}

$conn->select_db($dbname);

$sql = "CREATE TABLE IF NOT EXISTS orszagok (
    id INT AUTO_INCREMENT PRIMARY KEY,
    orszag_neve VARCHAR(255) NOT NULL,
    nepesseg INT NOT NULL,
    kontinens VARCHAR(255) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
    echo "Táblázat sikeresen létrehozva.\n";
} else {
    echo "Hiba a táblázat létrehozásában: " . $conn->error;
}

$file = fopen("orszagok.txt", "r");
if ($file) {
    $lines = [];
    while (($line = fgets($file)) !== false) {
        $lines[] = trim($line);
    }
    fclose($file);

    for ($i = 0; $i < count($lines); $i += 3) {
        $orszag = $lines[$i];
        $nepesseg = (int)$lines[$i + 1];
        $kontinens = $lines[$i + 2];

        $stmt = $conn->prepare("INSERT INTO orszagok (orszag_neve, nepesseg, kontinens) VALUES (?, ?, ?)");
        $stmt->bind_param("sis", $orszag, $nepesseg, $kontinens);
        $stmt->execute();
        $stmt->close();
    }
}

function get_countries($conn) {
    $sql = "SELECT orszag_neve, nepesseg, kontinens FROM orszagok";
    $result = $conn->query($sql);

    $countries = [];
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $countries[] = $row;
        }
    }
    return $countries;
}

// Ensure the connection is not closed prematurely
// $conn->close();


function count_countries($conn, $condition) {
    $sql = ($condition == "big") 
        ? "SELECT COUNT(*) AS count FROM orszagok WHERE nepesseg > 10000000"
        : "SELECT COUNT(*) AS count FROM orszagok WHERE nepesseg <= 10000000";
    $result = $conn->query($sql)->fetch_assoc();
    return $result['count'];
}

function get_extreme_country($conn, $order) {
    $sql = ($order == "max") 
        ? "SELECT orszag_neve, nepesseg FROM orszagok ORDER BY nepesseg DESC LIMIT 1"
        : "SELECT orszag_neve, nepesseg FROM orszagok ORDER BY nepesseg ASC LIMIT 1";
    return $conn->query($sql)->fetch_assoc();
}

function get_population_average($conn) {
    $sql = "SELECT AVG(nepesseg) AS avg FROM orszagok";
    $result = $conn->query($sql)->fetch_assoc();
    return intval($result['avg']);
}

function save_european_countries($conn) {
    $sql = "SELECT orszag_neve FROM orszagok WHERE kontinens = 'Európa'";
    $result = $conn->query($sql);
    $countries = $result->fetch_all(MYSQLI_ASSOC);

    $file = fopen("europaiak.txt", "w");
    foreach ($countries as $country) {
        fwrite($file, $country['orszag_neve'] . "\n");
    }
    fclose($file);
    return "Az európai országok mentve az europaiak.txt fájlba.";
}

// Műveletek feldolgozása
$message = "";
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['count_big'])) {
        $message = "10 milliónál több népességű országok száma: " . count_countries($conn, "big");
    } elseif (isset($_POST['count_small'])) {
        $message = "Legfeljebb 10 millió népességű országok száma: " . count_countries($conn, "small");
    } elseif (isset($_POST['max_population'])) {
        $country = get_extreme_country($conn, "max");
        $message = "Legnagyobb ország: {$country['orszag_neve']} ({$country['nepesseg']} fő)";
    } elseif (isset($_POST['min_population'])) {
        $country = get_extreme_country($conn, "min");
        $message = "Legkisebb ország: {$country['orszag_neve']} ({$country['nepesseg']} fő)";
    } elseif (isset($_POST['average_population'])) {
        $message = "Átlag népesség: " . get_population_average($conn) . " fő";
    } elseif (isset($_POST['save_europe'])) {
        $message = save_european_countries($conn);
    }
}
?>

<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Országok Népsűrűsége</title>
    <style>
        table, th, td { border: 1px solid black; }
        th, td { padding: 8px; text-align: left; }
        .message { margin: 20px 0; font-weight: bold; color: green; }
        form button { margin: 5px; padding: 10px 15px; }
    </style>
</head>
<body>
<div class="container">
    <h1>Országok Népsűrűsége</h1>

    <form method="post">
        <button name="count_big">10 milliónál több népességű országok</button>
        <button name="count_small">Legfeljebb 10 millió népességű országok</button>
        <button name="max_population">Legnagyobb ország</button>
        <button name="min_population">Legkisebb ország</button>
        <button name="average_population">Átlag népesség</button>
        <button name="save_europe">Európai országok mentése</button>
    </form>

    <?php if (isset($message) && $message): ?>
        <div class="message"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <table>
        <thead>
            <tr>
                <th>Ország neve</th>
                <th>Népesség</th>
                <th>Kontinens</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach (get_countries($conn) as $country): ?>
                <tr>
                    <td><?= htmlspecialchars($country['orszag_neve']) ?></td>
                    <td><?= htmlspecialchars(number_format($country['nepesseg'])) ?></td>
                    <td><?= htmlspecialchars($country['kontinens']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>