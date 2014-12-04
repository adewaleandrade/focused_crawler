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
		public $weightTable;
		public $classifier;
	

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
				'relevanceThreshold' => 0.3,
				'subPageLimit' => 10,
				'weightTableMaxSize' => 50,
				'expandLimit'  => 1000,
				'maxFishingLevel' => 2,
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

		    $results = array();
		    foreach ($response->responseData->results as $result) {
		    	$results[]= ['url' => $result->url, 'level' => 0];
		    }

		    return $results;
		}


		function getRelevantPages(){
			$csvFile = fopen("topicname.txt", 'w');
		    fputcsv($csvFile, ['visitedPages', 'relevantPages', 'ratio']); //Header
		    
			while(!empty($this->urls) && (count($this->visitedUrls) < $this->settings['expandLimit'])){
				
				$currentPage = $this->urls[0];
				unset($this->urls[0]);
				$this->urls = array_values($this->urls);
	
				//Check if page comes from less than 3 irrelevant pages
				if($currentPage['level'] <= 2){
					$baseUrl = getBaseUrl($currentPage['url']);

					// debugPrint('Relevance Threshold => '. $this->settings['relevanceThreshold']);
					if(!in_array($currentPage['url'], $this->visitedUrls) && ($currentPage['url'] != '')){
						
						$page = new Document($currentPage['url'], $this, $this->weightTable, $currentPage['level']);
						$relevance =  $this->classifier->calculatePageRelevance($page);

						debugPrint("Relevance => ".$relevance);
						$isRelevant = false;
						if($relevance >= $this->settings['relevanceThreshold']){	
							$isRelevant = true;

							$this->relevantPages[$currentPage['url']]['relevance'] = $relevance;
							
							//Pega os links dentro da página
							$this->relevantPages[$currentPage['url']]['links'] = array_keys($page->pageUrls);

							$page->getTermFrequencies();
							$this->weightTable->updateKeyWords();
						}

						if($isRelevant || ($page->irrelevantDrillLevel <= $this->settings['maxFishingLevel']  )){
							// Update the crawler's url pool with ranked urls extracted from the page.
							$fishingLevel = $isRelevant ? 0 : $page->irrelevantDrillLevel +1;
							$this->getPageLinksByRelevance($page, $fishingLevel);
						}

						unset($page);
						
						$this->visitedUrls[] = $currentPage['url'];
						$rP =0;

						// foreach ($this->relevantPages as $subPages) {
						// 	$rP += count($subPages);
						// }

						$dP =  count($this->relevantPages);
						$vP = count($this->visitedUrls);
						$ratio = $vP?$dP/$vP:0;

						if($vP%10 == 0){
							//add to csv file
							fputcsv($csvFile, [$vP, $dP, $ratio]);
						}
						
						debugPrint("Relevant Pages: <b>".$dP." Different Pages: <b> ".$dP."</b> / Visited : <b>".$vP."</b> / Ratio:".$ratio);
						sleep(2);
						// die();
					}
				}
			}
			fclose($csvFile);
			arsort($this->relevantPages);
			return $this->relevantPages;
		}

		function getPageLinksByRelevance(Document $page, $fishingLevel){
			$pageUrls = $page->getPageUrls();

			foreach ($pageUrls as $link => $score) {
				$this->urls[] = ['url' => $link, 'level' => $fishingLevel];
			}
		}
	}
?>