<?php

	/**
	 * Web Crawler Focado
	 * 
	 * @see https://github.com/adewaleandrade/focused_crawler
	 * @package    focused_crawler
	 * @author     Adewale Andrade D Alcantara
	 * @license    
	**/

	include_once('FocusedCrawler.php');

	set_time_limit(0);

	class WeightTable {

		private $crawler;
		// public $stemmedDictionary;
		public $termFrequencies;
		public $tDocFrequencies;
		public $documentCount;
		public $keyWords;
		public $extendedKeyWords;
		public $stemmedKeyWords;

		/**
		 * Constructor
		 * 
		 * Initializes the crawler's weight table by surfing
		 * the base set of urls and choosing the top relevant terms.
		 * 
		 * @param   FocusedCrawler crawler		 
		 */
		function WeightTable(FocusedCrawler &$crawler){
			$this->crawler = $crawler;
			$this->termFrequencies = array();
			// $this->stemmedDictionary = array();
			$this->tDocFrequencies = array();
			$this->keyWords = array();
			$this->extendedKeyWords = array();
			$this->stemmedKeyWords = array();
			$this->documentCount = 0;

			$seedDocuments = array();
			foreach ($this->crawler->urls as $url) {
				$seedDocuments[] = new Document($url['url'], $this->crawler, $this,0 , 1);
			}
			unset($seedDocuments);
			
			$this->updateKeyWords();
		}


		function updateKeyWords(){
			$this->keyWords = array();
			// Gets the tf-idf weight for each term extracted from the documents colection
			foreach ($this->termFrequencies as $term => $freq) {
				$this->keyWords[$term] = $freq * ($this->documentCount / $this->tDocFrequencies[$term]['count']);
			}

			arsort($this->keyWords);

			// gets the top N terms relevant to the topic
			$this->keyWords = array_slice($this->keyWords, 0, $this->crawler->settings['weightTableMaxSize'], true);

			// Weight Normalization
			$maxWeight = max($this->keyWords);
			foreach ($this->keyWords as $term => $weight) {
				$this->keyWords[$term] = $this->keyWords[$term]/$maxWeight;
			}
			debugPrint('Tabela original');
			debugPrint($this->keyWords);

			// $this->expandKeyWords();
			// debugPrint('Tabela Extendida:<br>');
			// debugPrint($this->extendedKeyWords);

			$this->updateStemmedKeyWords();
			debugPrint('Tabela de Stemmed KeyWords:<br>');
			debugPrint($this->stemmedKeyWords);
			
		}

		function updateStemmedKeyWords(){
			$this->stemmedKeyWords = array();
			foreach ($this->keyWords as $word => $weight) {
				$this->stemmedKeyWords[stem_portuguese($word)] = $weight;
			}
		}

		function expandKeyWords (){
			$keyWords = array_keys($this->keyWords);
			$extendedTable = array();

			foreach ($keyWords as $w) {
				$word = cleanWord($w);
				$sWord = Inflector::singular($word);
				
				// $searchUrl =  'http://www.dicio.com.br/'.$sWord;
				$searchUrl =  'http://www.sinonimos.com.br/'.$sWord;
				$page = file_get_html2($searchUrl);

				if($page) {
					$sin = $page->find("p.sinonimos a");

					foreach ($sin as $s) {
						$extendedTable[$s->plaintext] = $this->keyWords[$w];
					}
				}
			}
			
			$this->extendedKeyWords = array_merge($this->keyWords, $extendedTable);
			arsort($this->extendedKeyWords);
		}

	}
?>