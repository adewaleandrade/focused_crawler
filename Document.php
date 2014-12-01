<?php

	/**
	 * Web Crawler Focado
	 * 
	 * @see https://github.com/adewaleandrade/focused_crawler
	 * @package    focused_crawler
	 * @author     Adewale Andrade D Alcantara
	 * @license    
	**/

	include_once('libs/kohana-pt-inflector/classes/inflector.php');
	include_once('FocusedCrawler.php');


	set_time_limit(0);

	class Document {

		public $termFrequencies;
		public $keyTermsWeights;
		public $url;
		public $pageUrls;
		// private $wieghtTable;
		private $crawler;

		/**
		 * Constructor
		 *
		 * @param   string url	
		 * @param   FocusedCrawler crawler	
		 * @param   int isSeed		 
		 */
		function Document($url, FocusedCrawler &$crawler, $isSeed = 0 ){
			$this->url = $url;
			$this->termFrequencies = array();
			$this->keyTermsWeights = array();
			$this->pageUrls = array();
			// $this->wieghtTable = $weightTable;
			$this->crawler = $crawler;

			//Download and Parse page
			$page = file_get_html2($url);

			if($page){
				if($isSeed){
					$cleanPage = $this->sanitizePageContents($page);
					$this->getTermFrequencies($cleanPage);
				}else{
					$pageTitle = $page->find('title');
					$this->getSectionWeights($pageTitle, 2);

					$pageBody = $page->find('body');
					$this->getSectionWeights($pageBody);

					//Pega os links dentro da página
					$pageLinks = $page->find('a');
					foreach ($pageLinks as $link) {
						$normalizedUrl = normalizeUrl($this->url, $link->href);
						$this->pageUrls[] = $normalizedUrl;
					}
				}
			} else return false;
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
				$this->termFrequencies[$term] = isset($this->termFrequencies[$term])?($this->termFrequencies[$term] + 1):1;

				if(!isset($this->crawler->weightTable->tDocFrequencies[$term]['urls'][$this->url])){
					$this->crawler->weightTable->tDocFrequencies[$term]['urls'][$this->url] = $this->url;
				}
			}
			
			arsort($this->termFrequencies);
		}

		

		/**
		 * 
		 *
		 * @param   string   word to singularize
		 * @param   integer  count of thing
		 * @return  string
		 * @uses    Inflector::uncountable
		 */
		function getSectionWeights($sections, $baseScore = 1){
			foreach ($sections as $section) {
				$cleanSectionTerms = $this->sanitizePageContents($section);

				$keyTerms = array_keys($this->weightTable->keyWords);
				foreach ($cleanSectionTerms as $term) {
					//compute term frequencies
					$this->termFrequencies[$term] = isset($this->termFrequencies[$term])?($this->termFrequencies[$term] + 1):1;

					if(!isset($this->crawler->weightTable->tDocFrequencies[$term]['urls'][$this->url])){
						$this->crawler->weightTable->tDocFrequencies[$term]['urls'][$this->url] = $this->url;
					}

					// Get section weight for key words
					if(in_array($term, $keyTerms)){
						$this->keyTermsWeights[$term] = isset($this->keyTermsWeights[$term])?($this->keyTermsWeights[$term] + $baseScore) : $baseScore;
					}
				}
			}
		}

		

		

	}
?>