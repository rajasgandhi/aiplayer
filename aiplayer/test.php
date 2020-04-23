<?php

// https://miningbusinessdata.com/creating-php-webhook-api-ai/
// https://github.com/DavidEbuka23/phpwebhook
//
// https://github.com/dialogflow/fulfillment-webhook-json/blob/master/responses/v2/ActionsOnGoogle/RichResponses/SimpleResponse.json
// https://developers.google.com/actions/build/json/dialogflow-webhook-json
// https://www.freeformatter.com/json-validator.html
//https://stackoverflow.com/questions/54379642/playing-mp3-with-mediaresponse-seems-to-be-broken
//https://stackoverflow.com/questions/56485464/actions-on-google-how-change-audio-volume-while-an-action-streams-mp3
//https://stackoverflow.com/questions/55212764/is-it-possible-to-add-a-direct-command-to-google-assistant-api/55247088#55247088

/*
 * https://developers.google.com/actions/conversation-api-playground
 * Find the radio buttons <Dialogflow> <Actions SDK> and compare the JSON structure!
 * !!!We are using Dialogflow!!!
 * 
 * Project-Page Google (Dialogflow): https://developers.google.com/actions/
 * https://developers.google.com/actions/deploy/release-environments
 */
include 'config.php';

// V: $update['queryResult']['action'] == 'sayPlay')
function do_sayPlay($update)
{
    /* Filesystem is case sensitive, speaking not. Find the correct directory */
    $sPlaylist = $update['queryResult']['parameters']['playlist'];
    
    $matches = glob(ROOT_MP3FILE . '*', GLOB_ONLYDIR);
    foreach ($matches as $value) {
        $dir = substr($value, strlen(ROOT_MP3FILE));
        if (strtolower($dir) == strtolower($sPlaylist)) {
            $sPlaylist = $dir;
            break;
        }
    }
    
    /* try continue */
    $iPos = getLastPlayed($sPlaylist) + 1;
    list ($sFile, $iCnt) = findFile($sPlaylist, $iPos);
    /* try from start */
    if (!isset($sFile)) {
        $iPos = 1;
        list ($sFile, $iCnt) = findFile($sPlaylist, $iPos);
    }
        
    if (isset($sFile)) {
        $res = array(
            'payload' => array(
                'google' => array(
                    'expectUserResponse' => true,
                    // 'conversationToken' An opaque token that is recirculated to the Action every conversation turn. => $update['originalDetectIntentRequest']['payload']['conversation']['conversationId'],
                    'userStorage' => "{$iPos},{$sPlaylist}", // https://developers.google.com/actions/reference/rest/Shared.Types/AppResponse#FIELDS.user_storage
                    'richResponse' => array(
                        'items' => array(
                            0 => array( // the simple text response MUST be in the answer and MUST be the first and MUST not empty
                                'simpleResponse' => array(
                                    'textToSpeech' => "Starte position $iPos"
                                )
                            ),
                            1 => array(
                                'mediaResponse' => array(
                                    'mediaType' => 'AUDIO',
                                    'mediaObjects' => array(
                                        0 => array(
                                            'name' => "$sFile",
                                            'contentUrl' => ROOT_MP3URL . $sFile
                                        )
                                    )
                                )
                            )
                        )
                        /* https://stackoverflow.com/questions/54519306/google-action-does-not-send-actions-intent-media-status
                         * If chips are missing we get an 'Ein Fehler ist aufgetreten. Versuche es noch einmal, wenn du bereit bist.'
                         */
                        ,'suggestions' => array(array( 
                           'title' => 'start over'
                         ))
                    )
                )
            )
        );
    } else {
        $res = [
            'payload' => [
                'google' => [
                    'expectUserResponse' => true,
                    'richResponse' => [
                        'items' => array(
                            [
                                'simpleResponse' => [
                                    'textToSpeech' => "Konnte Playlist $sPlaylist nicht finden!"
                                ]
                            ]
                        )
                    ]
                ]
            ]
        ];
    }
    
    sendMessage($res);
}

// V: $update['queryResult']['action'] == 'sayPlay')
function do_sayPlaylists($update)
{
    $rgLists = glob(ROOT_MP3FILE . '*', GLOB_ONLYDIR);
    if (count($rgLists) > 0) {
        $sText = 'Folgend Playlists habe ich gefunden: ';
        
        foreach ($rgLists as $p) {
            $sText .= substr($p, strlen(ROOT_MP3FILE)); // get the rid of dicrectory
            $sText .= ",. "; //some kind of speech pause
        }
    } else {
        $sText = "Ich konnte keine Playlists finden.";
    }
    
    $res = [
        'payload' => [
            'google' => [
                'expectUserResponse' => true,
                'richResponse' => [
                    'items' => array(
                        [
                            'simpleResponse' => [
                                'textToSpeech' => $sText
                            ]
                        ]
                    )
                ]
            ]
        ]
    ];
    
    sendMessage($res);
}

function do_sayJump($update, $iPos, bool $bRealative)
{
    $iCnt = 0;
    
    $rgUser = explode(',', $update['originalDetectIntentRequest']['payload']['user']['userStorage'], 2);
    if (count($rgUser) == 2) {
        if ($bRealative) {
            $iPos += $rgUser[0];
        }
        
        $sPlaylist = $rgUser[1];
        list ($sFile, $iCnt) = findFile($sPlaylist, $iPos);
    }
    
    if (isset($sFile)) {
        $res = array(
            'payload' => array(
                'google' => array(
                    'expectUserResponse' => true,
                    'userStorage' => "{$iPos},{$sPlaylist}",
                    'richResponse' => array(
                        'items' => array(
                            0 => array(
                                'simpleResponse' => array(
                                    'textToSpeech' => "Weiter mit $iPos"
                                )
                            ),
                            1 => array(
                                'mediaResponse' => array(
                                    'mediaType' => 'AUDIO',
                                    'mediaObjects' => array(
                                        0 => array(
                                            'name' => "$sFile",
                                            'contentUrl' => ROOT_MP3URL . $sFile
                                        )
                                    )
                                )
                            )
                        )
                        ,'suggestions' => array(array(
                            'title' => 'start over'
                        ))
                    )
                    )
                )
            );
    } else {
        $res = [
            'payload' => [
                'google' => [
                    'expectUserResponse' => true,
                    'richResponse' => [
                        'items' => array(
                            [
                                'simpleResponse' => [
                                    'textToSpeech' => "Playlist $sPlaylist hat nur $iCnt Titel."
                                ]
                            ]
                        )
                    ]
                ]
            ]
        ];
    }
    
    sendMessage($res);
}

// V: $update['queryResult']['queryText'] == 'actions_intent_MEDIA_STATUS'
function processMediaStatus($update)
{
    $rgUser = explode(',', $update['originalDetectIntentRequest']['payload']['user']['userStorage'], 2);
    if (count($rgUser) == 2) {
        $iPos = $rgUser[0];
        $sPlaylist = $rgUser[1];

        //qd do we have to check not only [0], do we have to check [1], [2], ...??
        $bFinish = $update['originalDetectIntentRequest']['payload']['inputs']['0']['arguments']['0']['extension']['status'] == 'FINISHED';
        if ($bFinish) {
            setLastPlayed($sPlaylist, $iPos);
        }
        
        $iPos ++; // next title
        list ($sFile, $iCnt) = findFile($sPlaylist, $iPos);
    }
    
    if (isset($sFile)) {
        $res = array(
            'payload' => array(
                'google' => array(
                    'expectUserResponse' => true,
                    'userStorage' => "{$iPos},{$sPlaylist}",
                    'richResponse' => array(
                        'items' => array(
                            0 => array(
                                'simpleResponse' => array(
                                    'textToSpeech' => "Weiter mit $iPos"
                                )
                            ),
                            1 => array(
                                'mediaResponse' => array(
                                    'mediaType' => 'AUDIO',
                                    'mediaObjects' => array(
                                        0 => array(
                                            'name' => "$sFile",
                                            'contentUrl' => ROOT_MP3URL . $sFile
                                        )
                                    )
                                )
                            )
                        )
                        ,'suggestions' => array(array(
                            'title' => 'start over'
                        ))
                    )
                )
            )
        );
    } else {
        $iPos--; //last try was unsuccesful
        $res = [
            'payload' => [
                'google' => [
                    'expectUserResponse' => true,
                    'richResponse' => [
                        'items' => array(
                            [
                                'simpleResponse' => [
                                    'textToSpeech' => "Playlist $sPlaylist nach $iPos Titeln zu Ende."
                                ]
                            ]
                        )
                    ]
                ]
            ]
        ];
    }
    
    sendMessage($res);
}

function sendMessage($parameters)
{
    $res = json_encode($parameters);
    error_log($res, 3, ROOT_LOG . time() . '_outfile.json');
    echo $res;
}

function findFile($sPlaylist, $iNumber)
{
    if ($iNumber < 1)
        $iNumber = 1;

    try {
        $directory = new RecursiveDirectoryIterator(ROOT_MP3FILE . $sPlaylist . '/');
        $itDir = new RecursiveIteratorIterator($directory);
        foreach ($itDir as $path) {
            if ($path->isDir())
                continue;
            if (substr($path->getFileName(), - 4) != '.mp3')
                continue;
            
            $files[] = substr($path->getPathName(), strlen(ROOT_MP3FILE)); // get the rid of dicrectory
        }
        
        if (count($files) < $iNumber)
            return array(null, count($files)); //number not found
        
    } catch (Exception $e) {
        return array(null, 0); //playlist at all not found
    }
    
    sort($files);
    return array($files[$iNumber - 1], count($files)); //found!
}
            

function getLastPlayed_json() {
    $dat = @file_get_contents(ROOT_MP3FILE . "playlists.json");
    if ($dat)
        return json_decode($dat, true);
    else
        return array();
}

function getLastPlayed($sPlaylist) {
    $dat = getLastPlayed_json();

    if (array_key_exists($sPlaylist, $dat)) {
        $iNum = $dat[$sPlaylist];
        list ($sFile, $iCnt) = findFile($sPlaylist, $iNum);
        
        if (isset($sFile))
            return $iNum; //found in JSON and exists as file
    }
    
    /* not found */
    return 0;
}

function setLastPlayed($sPlaylist, $iNum) {
    $dat = getLastPlayed_json();
    $dat[$sPlaylist] = $iNum;
    
    file_put_contents(ROOT_MP3FILE . "playlists.json", json_encode($dat)); //we assume only one user, so no race condition
}

/* some testcode for command line */
if (0) {
//     phpinfo();
    
//     list ($sFile, $iNum) = findFile('pl1', 0);
//     echo "Das ist ein Test: $sFile $iNum<p>";
//     echo "{$iNum},{$sFile}";
    
//     $rg = explode(',', "1,pl1", 2);
//     echo "0: {$rg[0]}, 1: {$rg[1]}";
    
//     echo "LP1:" . getLastPlayed("pl1");
//     setLastPlayed("pl1", 1);
//     echo "LP2:" . getLastPlayed("pl1");

//     $matches = glob(ROOT_MP3FILE . '*', GLOB_ONLYDIR);
//     foreach ($matches as &$value) { //& - reference
//         $value = substr($value, strlen(ROOT_MP3FILE));
//     }
//     $a = 1;

    
//     $directory = new RecursiveDirectoryIterator(ROOT_MP3FILE . 'pl1/');
//     $itDir = new RecursiveIteratorIterator($directory);
    
//     foreach ( $itDir as $path ) {
//         if ($path->isDir())
//             continue;
//         if (substr($path->getFileName(), -4) != '.mp3')
//             continue;
                
//         $files[] = substr($path->getPathName(), strlen(ROOT_MP3FILE)); // get the rid of dicrectory
//     }
    
//     sort($files);
//     $a = 1;
}
/* Main Program */
else {
    $update_response = file_get_contents("php://input");
    $update = json_decode($update_response, true);
    error_log($update_response, 3, ROOT_LOG . time() . '_infile.json');
    
    if (isset($update['queryResult']['action']) && ($update['queryResult']['action'] == 'sayPlay')) {
        do_sayPlay($update);
    } else if (isset($update['queryResult']['action']) && ($update['queryResult']['action'] == 'sayPlaylists')) {
        do_sayPlaylists($update);
    } else if (isset($update['queryResult']['action']) && ($update['queryResult']['action'] == 'sayJump')) {
        $iPos = $update['queryResult']['parameters']['position'];
        do_sayJump($update, $iPos, false);
    } else if (isset($update['queryResult']['action']) && ($update['queryResult']['action'] == 'sayNext')) {
        $iPos = $update['queryResult']['parameters']['position'];
        do_sayJump($update, +1, true);
    } else if (isset($update['queryResult']['action']) && ($update['queryResult']['action'] == 'sayPrev')) {
        do_sayJump($update, -1, true);
    }
    else if (isset($update['queryResult']['queryText']) && ($update['queryResult']['queryText'] == 'actions_intent_MEDIA_STATUS')) {
        processMediaStatus($update);
    }
}

