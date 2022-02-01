<?php

$ini = parse_ini_file("config.ini", true)["rtr"];
$google_key = $ini['google_key'];
$weather_key = $ini['weather_key'];

$origin = urlencode($_POST['origin']);
$destination = urlencode($_POST['destination']);
date_default_timezone_set('America/New_York');
$departure = time();

// create curl resource
$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_URL, "https://maps.googleapis.com/maps/api/directions/json?origin=$origin&destination=$destination&key=$google_key&departure_time=$departure");

$output = curl_exec($ch);
$output = json_decode($output);

$weatherConditions = [];

$currTime = $departure;

foreach ($output->routes[0]->legs[0]->steps as $step) {
    $lat = ($step->end_location->lat + $step->start_location->lat)/2;
    $lng = ($step->end_location->lng + $step->start_location->lng)/2;

    curl_setopt($ch, CURLOPT_URL, "http://api.weatherapi.com/v1/forecast.json?key=$weather_key&days=2&aqi=no&alerts=no&q=" . $lat . "," . $lng);

    $output = curl_exec($ch);
    $output = json_decode($output);

    $hours = array_merge($output->forecast->forecastday[0]->hour, $output->forecast->forecastday[1]->hour);

    $diff = PHP_INT_MAX;
    foreach ($hours as $hour) {
        if (abs($hour->time_epoch - $currTime) < $diff) {
            $diff = abs($hour->time_epoch - $currTime);

            $weatherConditions[$lat . "," . $lng] = [
                "condition" => $hour->condition->text,
                "temp" => $hour->temp_f,
                "rain" => $hour->chance_of_rain,
                "snow" => $hour->chance_of_snow,
                "time" => $currTime
            ];
        }
    }

    $currTime += $step->duration->value;
}

curl_close($ch);
?>

<html lang="en">
<head>
    <title>Road Trip Report</title>
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $google_key; ?>"></script>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" integrity="sha384-ggOyR0iXCbMQv3Xipma34MD+dH/1fQ784/j6cY/iJTQUOhcWr7x9JvoRxT2MZw1T" crossorigin="anonymous">
    <script src="https://code.jquery.com/jquery-3.3.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.14.6/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.2.1/js/bootstrap.min.js"></script>
</head>
<body>
<div style="float: right;"><a href="index.html">New Report</a></div>
<h1>Road Trip Forecast:</h1>
<h2><?php echo $_POST['origin']; ?> to <?php echo $_POST['destination']; ?> leaving now</h2>

<div id="map" style="width: 100%; height: 80%; position: absolute;"></div>

<script>
    var map_parameters = { center: {lat: 47.490, lng: -117.585}, zoom: 8 };
    var map = new google.maps.Map(document.getElementById('map'), map_parameters);
    var bounds  = new google.maps.LatLngBounds();
    var flag = 'https://maps.google.com/mapfiles/ms/icons/red-dot.png';

    var info = new google.maps.InfoWindow();
    function marker_clicked()
    {
        info.setContent(this.getTitle());
        info.open(map, this);
    }

    <?php
    $count = 1;
    foreach ($weatherConditions as $key => $value) {
        $coords = explode(",", $key);
        $content = date('h:i a', $value['time']) . ": " . $value['condition'] . '; ' . round($value['temp']) . '°';
        if ($value['rain'] > 0) {
            $content .= "; " . $value['rain'] . "% rain";
        }
        if ($value['snow'] > 0) {
            $content .= "; " . $value['snow'] . "% snow";
        }

        echo 'var position' . $count . ' = { position: {lat: ' . $coords[0] . ', lng: '  . $coords[1] . '}, map: map, icon: flag, title: "' . $content . '" };';
        echo 'var marker' . $count . ' = new google.maps.Marker(position' . $count . ');';
        echo 'marker' . $count . '.addListener("click", marker_clicked);';
        echo 'loc = new google.maps.LatLng(marker' . $count . '.position.lat(), marker' . $count . '.position.lng());';
        echo 'bounds.extend(loc);';
        $count++;
    }
    ?>
    map.fitBounds(bounds);
    map.panToBounds(bounds);
</script>
</body>
</html>