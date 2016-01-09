<!DOCTYPE html>
<html>
    <?php

    function get_client_ip() {
        $ipaddress = '';
        if ($_SERVER['HTTP_CLIENT_IP'])
            $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
        else if($_SERVER['HTTP_X_FORWARDED_FOR'])
            $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
        else if($_SERVER['HTTP_X_FORWARDED'])
            $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
        else if($_SERVER['HTTP_FORWARDED_FOR'])
            $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
        else if($_SERVER['HTTP_FORWARDED'])
            $ipaddress = $_SERVER['HTTP_FORWARDED'];
        else if($_SERVER['REMOTE_ADDR'])
            $ipaddress = $_SERVER['REMOTE_ADDR'];
        else
            $ipaddress = 'UNKNOWN';

        if(strpos($ipaddress,',')){
            $ipaddress=explode(',', $ipaddress);
            $ipaddress=$ipaddress[0];
        }
        return $ipaddress;
    }

    function degtodir($deg){
        $compass = array("N","NNE","NE","ENE","E","ESE","SE","SSE","S","SSW","SW","WSW","W","WNW","NW","NNW");

        $compcount = round($deg / 22.5);
        $compdir = $compass[$compcount];
        return $compdir;

    }
    //WHERE
        //Ta = Observed Temperature
        //e = weater vapour pressure
        //ws = wind speed
        //Q = net radiation absorbed by human body (wtf)
    function apparent_temperature($Ta, $ws, $Q, $rh){
        $e = e($Ta, $rh);
        $at = $Ta+0.348*$e-0.7*$ws+0.7*($Q/($ws+10))-4.25;
        return $at;
    }
    function e($Ta, $rh){
        $x = $rh/100;
        $y = 117.27*$Ta;
        $z = 237.7+$Ta;
        $e = $x*pow(6.105*M_EULER, $y/$z);
        return $e;
    }

    function heat_index($T, $H){
        $T = $T*(9/5)+32;
        $hi = -42.379+2.04901523*$T+10.14333127*$H-0.22475541*$T*$H-6.83783*10**-3*$T**2-5.481717*10**-2*$H**2+1.22874*10**-3*$T**2*$H+8.5282*10**-4*$T*$H**2-1.99*10**-6*$T**2*$H**2;
        $hi = round(($hi-32)*5/9);
        return $hi;
    }

    function humidex($temp, $humidity){
        $dewpoint=($temp-((100 - $humidity)/5));

        $a = 8.07131;
        $b = 1730.63;
        $c = 233.426;
        $x = (log(2)+log(5))*($a*$c+$a*$temp-$b);
        $y = $c+$temp;
        $pressure = pow(M_EULER, $x/$y)*10;

        $humidex = $temp + (0.5555 * ($pressure-10));
        return $humidex;
    }

    function windchill($Ta,$ws){
        $Ta = $Ta*(9/5)+32;
        $ws = $ws/1.609344;
        $wc=35.74+0.6215*$Ta-35.75*$ws**0.16+0.4275*$Ta*$ws**0.16;
        $wc=($wc-32)*(5/9);
        return $wc;
    }

    //$url = "http://api.openweathermap.org/data/2.5/forecast";
    $key = getenv('key');

    //---------------GEO IP DATA---------------//

    $locapiurl = "http://ipinfo.io/".get_client_ip()."/json";
    $locjson = file_get_contents($locapiurl);
    $locdata = json_decode($locjson, true);

    $loc = explode(",", $locdata['loc']);
    $region = $locdata['region'];
    if(isset($region))
        $region = ', '.$region;

        $gapikey=getenv('gapi');
    //--------------TIME ZONE DATA--------------//
    $timezoneurl='https://maps.googleapis.com/maps/api/timezone/json?location='.$loc[0].','.$loc[1].'&timestamp='.time().'&sensor=false&key='.$gapikey;
    $timezonejson=file_get_contents($timezoneurl);
    $timedata=json_decode($timezonejson,true);

    $zone=$timedata['timeZoneId'];
    date_default_timezone_set($zone);

    //--------------5 DAY FORECAST--------------//
    $forecastapiurl = "http://api.openweathermap.org/data/2.5/forecast/daily?lat=".$loc[0]."&lon=".$loc[1]."&cnt=6&APPID=".$key;
    $forecastjson = file_get_contents($forecastapiurl);
    $forecastdata = json_decode($forecastjson, true);

    for($i=1;$i<6;$i++){
        ${'tmp'.$i}=round($forecastdata['list'][$i]['temp']['max']-273.15);
        ${'weatherimg'.$i}='img/'.$forecastdata['list'][$i]['weather'][0]['main'].'.jpg';
        ${'date'.$i}=date("l, F j",$forecastdata['list'][$i]['dt']);
    }

    //---------------CURRENT DATA---------------//
    $currentapiurl = "http://api.openweathermap.org/data/2.5/weather?lat=".$loc[0]."&lon=".$loc[1]."&APPID=".$key;
    $currentjson = file_get_contents($currentapiurl);
    $currentdata = json_decode($currentjson, true);

    $nowtmp = round($currentdata['main']['temp']-273.15);
    $nowhum = $currentdata['main']['humidity'];
    $nowpres = $currentdata['main']['pressure'];
    $nowweather = $currentdata['weather'][0]['main'];
    $nowwind = $currentdata['wind']['speed'];
    $nowwinddeg = $currentdata['wind']['deg'];
    $nowsunrise = date("g:i A",$currentdata['sys']['sunrise']);
    $nowsunset = date("g:i A",$currentdata['sys']['sunset']);

    $city = $currentdata['name'];
    $country = $currentdata['sys']['country'];

/*    if(windchill($nowtmp,$nowwind)>$nowtmp)
        $nowfeel=round(humidex($nowtmp, $nowhum));
    else
        $nowfeel=round(windchill($nowtmp,$nowwind));*/
    $nowfeel=round(windchill($nowtmp,$nowwind));

    $nowimg = 'img/'.$currentdata['weather'][0]['main'].'.jpg';

    //-------------DICTIONARY REPLACE-----------//
    $search = array('Clear', 'Clouds', 'Rain', 'Snow', 'Thunderstorm');
	$replace = array('clear', 'cloudy', 'raining', 'snowing', 'thunderstorms');
    $nowweather = str_replace($search, $replace, $nowweather);



    ?>

    <head>
        <link href='css/weather.css' rel='stylesheet'>
        <link href='css/styles.css' rel='stylesheet'>
        <link href='http://fonts.googleapis.com/css?family=Open+Sans:300' rel='stylesheet' type='text/css'>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/fullPage.js/2.6.7/jquery.fullPage.min.css" rel='stylesheet'>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.0.0-alpha1/jquery.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery-backstretch/2.0.4/jquery.backstretch.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/fullPage.js/2.6.7/jquery.fullPage.min.js"></script>
    </head>
    <body>
        <div class="dim"></div>
        <div id="fullpage">
            <div class="section">
                <div class='os top'>
                    <span id='location'><?=$city.$region?></span>
                </div>
                <div class='current'>
                    <center>
                        <p class='os now'>it's
                            <span id='temp'><?=$nowtmp?></span>&deg;<span class='unit'>C</span> now, <?=$nowweather?></br>
                        </p>
                        <p class='os feels'>feels like <span id='feeltemp'><?=$nowfeel?></span>&deg;<span class='unit'>C</span></p>
                    </center>
                </div>
                <div class='os extra'>
                    <p>
                        <span>wind: <?=degtodir($nowwinddeg).' at '.round($nowwind*3.6)?> km/h</span>
                        <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        <span>humidity: <?=round($nowhum)?>%</span>
                        <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        <span>pressure: <?=round($nowpres/10)?> kPa</span>
                        <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        <span>sunrise: <?=$nowsunrise?></span>
                        <span>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span>
                        <span>sunset: <?=$nowsunset?></span>
                    </p>
                </div>
            </div>
            <div class="section os white">
                <?php

                for($i=1;$i<6;$i++){
                    echo
                    "<div class='slide'>
                        <center>
                            <p class='os white' style='font-size:2.5em;'>${'date'.$i}</p>
                            <p class='os now'>it'll be about
                                <span id='temp'>${'tmp'.$i}</span>&deg;<span class='unit'>C</span></br>
                            </p>
                        </center>
                    </div>";
                }

                ?>
            </div>
        </div>

        <script>
        var sloc=0;
        var slides=['<?=$weatherimg1?>','<?=$weatherimg2?>','<?=$weatherimg3?>','<?=$weatherimg4?>','<?=$weatherimg5?>'];
        $(document).ready(function() {
            $('#fullpage').fullpage({
                //Navigation
                slidesNavigation: true,
                slidesNavPosition: 'bottom',
                //Scrolling
                scrollingSpeed: 700,
                scrollBar: true,
                easing: 'easeInOutCubic',
                loopHorizontal: true,
                //Accessibility
                recordHistory: false,
                verticalCentered: true,
                //Design
                controlArrows: false,

                //Hooks
                onLeave: function(index, nextIndex, direction){
                    var leavingSection = $(this);

                    if(nextIndex == 1){
                        backstretch('<?=$nowimg?>');
                    }
                    else if(nextIndex == 2){
                       backstretch(slides[sloc]);
                    }


                },
                onSlideLeave: function( anchorLink, index, slideIndex, direction, nextSlideIndex){
                    var leavingSlide = $(this);

                    if(nextSlideIndex == 0){
                         sloc=0;
                         backstretch(slides[0]);
                    }
                    else if (nextSlideIndex == 1) {
                         sloc=1;
                         backstretch(slides[1]);
                    }
                    else if (nextSlideIndex == 2) {
                         sloc=2;
                         backstretch(slides[2]);
                    }
                    else if (nextSlideIndex == 3) {
                        sloc=3;
                        backstretch(slides[3]);
                    }
                    else if (nextSlideIndex == 4) {
                        sloc=4;
                        backstretch(slides[4]);
                    }

                }
            });
        });
        function backstretch(img){
             $.backstretch(img, {speed: 700});
        }

        function farenheight(){
            x=document.getElementsByClassName("demo");  // Find the elements
                for(var i = 0; i < x.length; i++){
                x[i].innerText="Hello JavaScript!";    // Change the content
            }
        }

        $.backstretch("<?=$nowimg?>");
        console.log("<?=$forecastapiurl?>");
        console.log("<?=$currentapiurl?>");
        </script>
    </body>
</html>

<!--http://codepen.io/joshbader/pen/EjXgqr-->
