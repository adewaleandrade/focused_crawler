<?php
	include('libs/simple_html_dom.php');
	include('util.php');
	set_time_limit(0);

	class Crawler {
		var $settings;
		var $urls;
		var $visitedUrls;
		var $keyWords;
		var $weightTable;
		var $relevantPages;
		var $currentPage;
		var $similarityTheshold;
		var $possibleKeywords;
		var $tFrequency;
		var $tDocFrequency;

		function Crawler($userSettings){
			$defaultSettings = array(
				'topic' => '',
				'userKeyWords' => array(),
				'userBaseUrls' => array(),
				'similarityTheshold' => 0.9,
				'apiUrl' => 'http://ajax.googleapis.com/ajax/services/search/web?',
			);

			if(is_array($userSettings)){
				$this->settings = array_merge($defaultSettings, $userSettings);
			}else{
				$defaultSettings['topic'] = $userSettings;
				$this->settings = $defaultSettings;
			}

			$this->keyWords = array();
			$this->visetedUrls = array(); 
			$this->weightTable = array();
			$this->relevantPages = array();
			$this->possibleKeywords = array();
			$this->tFrequency = array();
			$this->tDocFrequency = array();
		}

		function getBaseUrlByTopic(){

			$googleRequestUrl = $this->settings['apiUrl'];   
		    $options = array("v"=>'1.0',"rsz"=>"large", 'start'=>'0', 'q'=>urlencode($this->settings['topic']));
		    $googleRequestUrl .= http_build_query($options,'','&');

		    $jsonResponse = file_get_contents($googleRequestUrl) or die(print_r(error_get_last()));
		    $response = json_decode($jsonResponse);

		    // debugPrint($response->responseData->results);

		    $results = array();
		    foreach ($response->responseData->results as $result) {
		    	$results[]=$result->url;
		    }
		    $this->urls = $results;

		    return $results;
		}

		function getRelevantPages(){
			while(!empty($this->urls)){
				$this->currentPage = $this->urls[0];
				array_pop($this->urls);

				//Parse the html page
				$page = file_get_html($this->currentPage);
				$cleanPage = $this->sanitizePageContents($page);
				$this->getTermFrequencies($cleanPage);
				// $similarity = $this->checkSimilarity($page);

				// if($similarity >= $this->similarityTheshold){
				// 	$this->relevantPages[] = $currentUrl;
				// }else{
				// 	//tratar páginas n similares
				// }

			}

			return $this->relevantPages;
		}

		function initializeWeightTable(){
			foreach ($this->urls as $url) {
				$this->currentPage = $url;
				//Parse the html page
				$page = file_get_html($url);
				$cleanPage = $this->sanitizePageContents($page);
				$this->getTermFrequencies($cleanPage);
			}

			$this->formatDocFrequency();
			$this->buildWeightTable();
		}

		function formatDocFrequency(){
			foreach ($this->tDocFrequency as $term => $docs) {
				$this->tDocFrequency[$term] = count($docs);
			}
		}

		// function setWeightTable($pageCollection){
		// 	foreach ($pageCollection as $page) {
		// 		$sanitizedPage = $this->sanitizePageContents($page);
				
		// 		$this->getPageTermFrequencies($page);
				
		// 		$this->updateWeightTable();

		// 	}
		// }

		function buildWeightTable(){

			foreach ($this->tFrequency as $term => $freq) {
				$this->weightTable[$term] = $freq * $this->tDocFrequency[$term];
			}

			//Normalização dos pesos;
			$maxWeight = max($this->weightTable);
			foreach ($this->weightTable as $term => $weight) {
				$this->weightTable[$term] = $this->weightTable[$term]/$maxWeight;
			}

			arsort($this->weightTable);
			$this->weightTable = array_slice($this->weightTable, 0, 10, true);
			debugPrint("<b>Pesos normalizados - top10:</b>");
			debugPrint($this->weightTable);

		}

		function checkSimilarity($page){



		}


		function sanitizePageContents($page){
			$trash = array(
				'o', 'os', 'a', 'as', 'ao',
				'um', 'uns', 'uma', 'umas',
				'de', 'do', 'da', 'das',
				'e', 'é', '', 'ou',
				'na', 'no',
				'por', 'para', 
				'outro', 'outra', 'outros', 'outras', 'todos', 'todo', 'toda', 'algum', 'alguma', 'alguns', 'algumas', 'cada',
				'seu', 'seus', 'sua', 'suas',
				'sim', 'não',
				'só', 
				'ba', 'nov', 'dez', 
				'como', 'em', 'que', 'com', 'mais', 'dia', 'se',

			);

			$text = $page->plaintext;

			//Remove caracteres especiais e numeros
			$text = preg_replace('/[0-9]h/s', '', $text);
			$text = preg_replace('/[^a-zA-ZãÃáàâõóòôêéèíìúùç ]/s', '', $text);
			
			$text = strtolower($text);
			$words = explode(' ', $text);		

			//Remove palavras desnecessárias
			foreach ($words as $k => $v) {
				if (in_array($v, $trash)) {
					unset($words[$k]);
				}
			}
			
			return $words;
		}

		function getTermFrequencies(array $terms){

			foreach ($terms as $term) {
				$this->tFrequency[$term] = isset($this->tFrequency[$term])?($this->tFrequency[$term] + 1):1;
				if(!isset($this->tDocFrequency[$term][$this->currentPage])){
					$this->tDocFrequency[$term][$this->currentPage] = $this->currentPage;
				}
			}
			
			arsort($this->tFrequency);
		}

		// function wordTable($text){
		// 	print_r($text);
		// 	die();

		// }

		function cosineSimilarity($tokensA, $tokensB)
		{
		    $a = $b = $c = 0;
		    $uniqueTokensA = $uniqueTokensB = array();

		    $uniqueMergedTokens = array_unique(array_merge($tokensA, $tokensB));

		    foreach ($tokensA as $token) $uniqueTokensA[$token] = 0;
		    foreach ($tokensB as $token) $uniqueTokensB[$token] = 0;

		    foreach ($uniqueMergedTokens as $token) {
		        $x = isset($uniqueTokensA[$token]) ? 1 : 0;
		        $y = isset($uniqueTokensB[$token]) ? 1 : 0;
		        $a += $x * $y;
		        $b += $x;
		        $c += $y;
		    }
		    return $b * $c != 0 ? $a / sqrt($b * $c) : 0;
		}
	}
?>