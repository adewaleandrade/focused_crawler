<?php

	/**
	 * Web Crawler Focado
	 * 
	 * @see https://github.com/adewaleandrade/focused_crawler
	 * @package    focused_crawler
	 * @author     Adewale Andrade D Alcantara
	 * @license    
	 */
	include('libs/simple_html_dom.php');
	include('libs/util.php');
	include('libs/kohana-pt-inflector/classes/inflector.php');

	set_time_limit(0);

	class Crawler {
		var $settings;
		var $urls;
		var $visitedUrls;
		var $keyWords;
		var $weightTable;
		var $pageWeights;
		var $relevantPages;
		var $currentPage;
		var $possibleKeywords;
		var $tFrequency;
		var $tDocFrequency;

		/**
		 * Constructor
		 *
		 * @param   array userSettings		 
		 */

		function Crawler($userSettings){
			$defaultSettings = array(
				'topic' => '',
				'userKeyWords' => array(),
				'userBaseUrls' => array(),
				'relevanceTheshold' => 0.5,
				'subPageLimit' => 10,
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
			$this->pageWeights = array();
			$this->relevantPages = array();
			$this->possibleKeywords = array();
			$this->tFrequency = array();
			$this->tDocFrequency = array();
		}

		/**
		 * Initializes the crawler's weight table by surfing
		 * the base set of urls and choosing the top relevant terms.
		 */
		function initializeWeightTable(){
			foreach ($this->urls as $url) {
				$this->currentPage = $url;
				//Parse the html page
				$page = file_get_html2($url);
				$cleanPage = $this->sanitizePageContents($page);
				$this->getTermFrequencies($cleanPage);
			}

			$this->formatDocFrequency();
			$this->buildWeightTable();
		}

		/**
		 * Formats the tDocFrequecy (Term Document Frequency)
		 */
		function formatDocFrequency(){
			foreach ($this->tDocFrequency as $term => $docs) {
				$this->tDocFrequency[$term] = count($docs);
			}
		}

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
			$this->expandWeightTable();
			debugPrint("<b>Extended Weight table</b>");
			debugPrint($this->weightTable);
			

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
			while(!empty($this->urls) && (count($this->relevantPages) < 100)){
				$this->currentPage = $this->urls[0];
				unset($this->urls[0]);
				$this->urls = array_values($this->urls);

				$baseUrl = getBaseUrl($this->currentPage);

				// debugPrint('Relevance Threshold => '. $this->settings['relevanceTheshold']);
				if(!in_array($this->currentPage, $this->visetedUrls) && ($this->currentPage != '')){
					//Parse the html page
					$page = file_get_html2($this->currentPage);
					if($page){

						$relevance =  $this->calculatePageRelevance($page);
						// debugPrint($this->currentPage.' => '. $relevance);
						
						if($relevance >= $this->settings['relevanceTheshold']){							
							if(count($this->relevantPages[$baseUrl]) < $this->settings['subPageLimit']){
								$this->relevantPages[$baseUrl][$this->currentPage] = $relevance;	
							}
							
						}
						//Pega os links dentro da página
						$pageLinks = $page->find('a');
						foreach ($pageLinks as $link) {
							$normalizedUrl = normalizeUrl($this->currentPage, $link->href);
							$this->urls[] = $normalizedUrl;
						}
						
						$this->visitedUrls[] = $this->currentPage;
						$rP =0;
						foreach ($this->relevantPages as $subPages) {
							$rP += count($subPages);
						}
						$dP =  count($this->relevantPages);
						$vP = count($this->visitedUrls);
						$ratio = $vP?$rP/$vP:0;
						debugPrint("Relevant Pages: <b>".$rP." Different Pages: <b> ".$dP."</b> / Visited : <b>".$vP."</b> / Ratio:".$ratio);
						sleep(2);
					}
				}
			}
			arsort($this->relevantPages);
			return $this->relevantPages;
		}

		/**
		 * Makes a plural word singular.
		 *
		 *     echo Inflector::singular('gatos'); // "gato"
		 *     echo Inflector::singular('appendix'); // "appendix", uncountable
		 *
		 * You can also provide the count to make inflection more intelligent.
		 * In this case, it will only return the singular value if the count is
		 * greater than one and not zero.
		 *
		 *     echo Inflector::singular('gatos', 2); // "gatos"
		 *
		 * [!!] Special inflections are defined in `config/inflector.php`.
		 *
		 * @param   string   word to singularize
		 * @param   integer  count of thing
		 * @return  string
		 * @uses    Inflector::uncountable
		 */
		function calculatePageRelevance($page){

			if($page){
				$this->pageWeights = array();

				$pageTitle = $page->find('title');
				$this->getSectionWeights($pageTitle, 2);

				$pageBody= $page->find('body');
				$this->getSectionWeights($pageBody);

				// debugPrint($this->pageWeights);

				return $this->cosineSimilarity();

			}else{
				return null;
			}
		}

		/**
		 * Makes a plural word singular.
		 *
		 *     echo Inflector::singular('gatos'); // "gato"
		 *     echo Inflector::singular('appendix'); // "appendix", uncountable
		 *
		 * You can also provide the count to make inflection more intelligent.
		 * In this case, it will only return the singular value if the count is
		 * greater than one and not zero.
		 *
		 *     echo Inflector::singular('gatos', 2); // "gatos"
		 *
		 * [!!] Special inflections are defined in `config/inflector.php`.
		 *
		 * @param   string   word to singularize
		 * @param   integer  count of thing
		 * @return  string
		 * @uses    Inflector::uncountable
		 */
		function getSectionWeights($sections, $baseScore = 1){
			foreach ($sections as $section) {
				$cleanSectionTerms = $this->sanitizePageContents($section);

				$keyTerms = array_keys($this->weightTable);
				foreach ($cleanSectionTerms as $term) {
					if(in_array($term, $keyTerms)){
						$this->pageWeights[$term] = isset($this->pageWeights[$term])?($this->pageWeights[$term] + $baseScore) : $baseScore;
					}
				}
			}
		}

		function sanitizePageContents($page){
			$trash = array(
				'o', 'os', 'a', 'as', 'ao',
				'um', 'uns', 'uma', 'umas',
				'de', 'do', 'da', 'das', 'dos',
				'e', 'é', '', 'ou',
				'na', 'no',
				'por', 'para', 
				'outro', 'outra', 'outros', 'outras', 'todos', 'todo', 'toda', 'algum', 'alguma', 'alguns', 'algumas', 'cada',
				'seu', 'seus', 'sua', 'suas',
				'sim', 'não',
				'só', 
				'ba', 'nov', 'dez', 
				'como', 'em', 'que', 'com', 'mais', 'dia', 'se',
				'nbsp', 'ã©', 'eacute', 'agraves', 'r', 

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

		function cosineSimilarity()
		{
			$a = $b = $c = 0;
			foreach ($this->pageWeights as $term => $w) {
				$a += $w * $this->weightTable[$term];
				$b += $w * $w;
			}
			foreach ($this->weightTable as $w) {
				$c += $w * $w;
			}

		    return $b * $c != 0 ? $a / sqrt($b * $c) : 0;
		}

		function expandWeightTable (){
			$keyWords = array_keys($this->weightTable);
			$extendedTable = array();

			foreach ($keyWords as $w) {
				$word = cleanWord($w);
				$sWord = Inflector::singular($word);
				$searchUrl =  'http://www.dicio.com.br/'.$sWord;
				$page = file_get_html2($searchUrl);

				if($page) {
					// debugPrint($page->plaintext);
					$sin = $page->find("p.sinonimos a");

					foreach ($sin as $s) {
						$extendedTable[$s->plaintext] = $this->weightTable[$w];
					}
				}
			}
			
			$this->weightTable = array_merge($this->weightTable, $extendedTable);
			arsort($this->weightTable);
		}
	}
?>