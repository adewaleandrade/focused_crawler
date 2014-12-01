<?php

	/**
	 * Web Crawler Focado
	 * 
	 * @see https://github.com/adewaleandrade/focused_crawler
	 * @package    focused_crawler
	 * @author     Adewale Andrade D Alcantara
	 * @license    
	**/
	include_once('libs/simple_html_dom.php');
	include_once('libs/util.php');
	include_once('WeightTable.php');
	include_once('Document.php');
	include_once('Classifier.php');

	set_time_limit(0);

	class FocusedCrawler {
		var $settings;
		var $urls;
		var $visitedUrls;
		var $relevantPages;
		// var $currentPage;
		
		var $weightTable;
		var $classifier;
		// var $possibleKeywords;
	

		/**
		 * Constructor
		 *
		 * @param   array userSettings		 
		 */
		function FocusedCrawler($userSettings){
			$defaultSettings = array(
				'topic' => '',
				'userKeyWords' => array(),
				'userBaseUrls' => array(),
				'relevanceTheshold' => 0.5,
				'subPageLimit' => 10,
				'weightTableTopTermsCount' => 10, 
				'apiUrl' => 'http://ajax.googleapis.com/ajax/services/search/web?',
			);

			if(is_array($userSettings)){
				$this->settings = array_merge($defaultSettings, $userSettings);
			}else{
				$defaultSettings['topic'] = $userSettings;
				$this->settings = $defaultSettings;
			}

			$this->urls = $this->getBaseUrlByTopic();
			$this->visitedUrls = array();
			$this->relevantPages = array();

			$this->weightTable = new WeightTable($this);

			$this->classifier = new Classifier($this);
			
			debugPrint($this->getRelevantPages());
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
		    // $this->urls = $results;

		    return $results;
		}


		function getRelevantPages(){
			while(!empty($this->urls) && (count($this->relevantPages) < 100)){
				
				$currentPage = $this->urls[0];
				unset($this->urls[0]);

				$this->urls = array_values($this->urls);

				$baseUrl = getBaseUrl($currentPage);

				// debugPrint('Relevance Threshold => '. $this->settings['relevanceTheshold']);
				if(!in_array($currentPage, $this->visitedUrls) && ($currentPage != '')){
					
					$page = new Document($currentPage, $this);
					$relevance =  $this->classifier->calculatePageRelevance($page);
									
					if($relevance >= $this->settings['relevanceTheshold']){							
						if(count($this->relevantPages[$baseUrl]) < $this->settings['subPageLimit']){
							$this->relevantPages[$baseUrl][$this->currentPage] = $relevance;	
						}
						
					}

					//Fishing******************************************************************************************
					//Pega os links dentro da página
					foreach ($page->pageUrls as $link) {
						$this->urls[] = $link;
					}
					
					$this->visitedUrls[] = $currentPage;
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
			arsort($this->relevantPages);
			return $this->relevantPages;
		}
	}
?>