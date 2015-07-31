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
				'expandLimit'  => 500,
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

			//export Urls to CSV
			$csvFile = fopen($this->settings['topic']."_seed_urls_B2.csv", 'w');
		    fputcsv($csvFile, ['urls']); //Header
		    foreach ($this->urls as $url=>$info) {
		    	fputcsv($csvFile, [$info['url']]);
		    }
		    fclose($csvFile);

			$this->visitedUrls = array();
			$this->relevantPages = array();

			$this->weightTable = new WeightTable($this); 
			$this->classifier = new Classifier($this);
			
			// debugPrint($this->getRelevantPages());
			$this->getRelevantPages();
		}

		function getBaseUrlByTopic(){
			$googleResults = file_get_html2('www.google.com.br/search?q='.urlencode($this->settings['topic']));
			$resultLinks = $googleResults->find('h3.r a');

		    $results = array();
		    foreach ($resultLinks as $result) {
		    	$url = str_replace('/url?q=', '', $result->href);
		    	$url = explode('&amp;sa=U', $url);
		    	$url = $url[0];
		    	$url  = htmlentities(urldecode(str_replace('%25','%',$url)));
		    	$results[]= ['url' => $url, 'level' => 0];
		    }
		   
		    return $results;
		}

		 function utf8_urldecode($str) {
		    $str = preg_replace("/%u([0-9a-f]{3,4})/i","&#x\\1;",urldecode($str));
		    return html_entity_decode($str,null,'UTF-8');;
		  }


		function getRelevantPages(){
			$csvFile = fopen($this->settings['topic']."_B2.csv", 'w');
		    fputcsv($csvFile, ['visitedPages', 'relevantPages', 'ratio']); //Header
		    

			while(!empty($this->urls) && (count($this->visitedUrls) < $this->settings['expandLimit'])){
				debugPrint('Visited Urls = '.count($this->visitedUrls));
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

						// debugPrint("Relevance => ".$relevance);
						$isRelevant = false;
						if($relevance >= $this->settings['relevanceThreshold']){	
							$isRelevant = true;

							$this->relevantPages[$currentPage['url']]['relevance'] = $relevance;
							
							//Pega os links dentro da página
							// $this->relevantPages[$currentPage['url']]['links'] = array_keys($page->pageUrls);
							$this->relevantPages[$currentPage['url']]['links'] = [];

							// $page->getTermFrequencies();

							//update the weight table on every 50 relevant pages
							// if(count($this->relevantPages)%50 == 0)
							// 	$this->weightTable->updateKeyWords();
						}

						if($isRelevant || ($page->irrelevantDrillLevel <= $this->settings['maxFishingLevel']  )){
							// Update the crawler's url pool with ranked urls extracted from the page.
							$fishingLevel = $isRelevant ? 0 : $page->irrelevantDrillLevel +1;
							$this->getPageLinksByRelevance($page, $fishingLevel);
						}

						unset($page);
						
						$this->visitedUrls[] = $currentPage['url'];

						$rP =  count($this->relevantPages);
						$vP = count($this->visitedUrls);
						$ratio = $vP?$rP/$vP:0;

						if($vP%10 == 0){
							//add to csv file
							fputcsv($csvFile, [$vP, $rP, $ratio]);
						}
						
						// debugPrint("Relevant Pages: <b>".$rP." Different Pages: <b> ".$rP."</b> / Visited : <b>".$vP."</b> / Ratio:".$ratio);
						sleep(2);
						// die();
					}
				}
			}
			fclose($csvFile);
			arsort($this->relevantPages);

			//export Urls to CSV
			$csvFile = fopen($this->settings['topic']."_urls_B2.csv", 'w');
		    fputcsv($csvFile, ['urls', 'relevancia']); //Header
		    foreach ($this->relevantPages as $url=>$info) {
		    	fputcsv($csvFile, [$url, $info['relevance']]);
		    }
		    fclose($csvFile);

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