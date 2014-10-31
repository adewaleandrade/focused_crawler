<?php
	function debugPrint ($element){
    	echo '<pre>';
    	print_r($element);
    	echo '</pre>';
    }

    function normalizeUrl($reference, $link){
    	if( (strpos($link, 'http://') !== false) || (strpos($link, 'https://') !== false) ){
    		return $link;
    	}

    	$baseUrl = getBaseUrl($reference);

    	if(strpos($link,"/")!==0){
    		$link = '/'.$link;
    	}

    	return 'http://'.$baseUrl.$link;
    }

    function getBaseUrl ($url){
    	$url = str_replace('http://', '', $url);
    	$url = str_replace('https://', '', $url);

    	$baseUrl = explode('/', $url)[0];

    	return $baseUrl;
    }

    function cleanWord($w){
    	$replacements = array(
    		'á' => 'a',
    		'à' => 'a',
    		'ã' => 'a',
    		'ó' => 'o',
    		'ô' => 'o',
    		'õ' => 'o',
    		'é' => 'e',
    		'ê' => 'e',
    		'í' => 'i',
    		'ú' => 'u',
    	);

    	foreach ($replacements as $o => $r) {
    		$w = str_replace($o, $r, $w);
    	}

    	return $w;
    }
?>
