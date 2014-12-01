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

		public $terms;
		public $crawler;
		public $stemmedDictionary;
		public $generalTermFrequencies;
		public $termFrequencies;
		public $tDocFrequencies;
		public $keyWords;
		public $seedDocuments;

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
			$this->generalTermFrequencies = array();
			$this->stemmedDictionary = array();
			$this->tDocFrequencies = array();
			$this->keyWords = array();
			$this->seedDocuments = array();


			foreach ($this->crawler->urls as $url) {
				$this->seedDocuments[] = new Document($url, $this->crawler, 1);
			}
			
			$this->buildWeightTable();
			// debugPrint($this->tDocFrequencies);die();
		}


		function buildWeightTable(){
			$this->getGeneralTermFrequencies();
			$this->formatDocFrequency();

			// Gets the tf-idf weight for each term on the seed documents pool
			foreach ($this->termFrequencies as $term => $freq) {
				$this->keyWords[$term] = $freq * (count($this->seedDocuments) / $this->tDocFrequencies[$term]['count']);
			}

			// Weight Normalization
			$maxWeight = max($this->keyWords);
			foreach ($this->keyWords as $term => $weight) {
				$this->keyWords[$term] = $this->keyWords[$term]/$maxWeight;
			}

			arsort($this->keyWords);

			// gets the top N terms relevant to the topic
			$this->keyWords = array_slice($this->keyWords, 0, $this->crawler->settings['weightTableTopTermsCount'], true);

			debugPrint("<b>Pesos normalizados - top10:</b>");
			debugPrint($this->keyWords);

			$this->expandWeightTable();
			debugPrint("<b>Extended Weight table</b>");
			debugPrint($this->keyWords);
		}


		function getGeneralTermFrequencies(){
			foreach ($this->seedDocuments as $document) {
				foreach ($document->termFrequencies as $term => $freq) {					
					$this->termFrequencies[$term] = isset($this->termFrequencies[$term]) ? $this->termFrequencies[$term] + $freq : $freq;
					$this->tDocFrequencies[$term]['urls'][$document->url] = $document->url;
				}
			}
		}

		/**
		 * Formats the tDocFrequecy (Term Document Frequency)
		 */
		function formatDocFrequency(){
			foreach ($this->tDocFrequencies as $term => $info) {
				$this->tDocFrequencies[$term]['count'] = count($info['urls']);
			}
		}


		function expandWeightTable (){
			$keyWords = array_keys($this->keyWords);
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
						$extendedTable[$s->plaintext] = $this->keyWords[$w];
					}
				}
			}
			
			$this->keyWords = array_merge($this->keyWords, $extendedTable);
			arsort($this->keyWords);
		}


		function updateDictionary (array $documentDicionary){
			foreach ($documentDicionary as $stemmedWord => $originalWords) {
				if(isset($this->stemmedDictionary[$stemmedWord])){
					$this->stemmedDictionary[$stemmedWord] = $this->stemmedDictionary[$stemmedWord] + $originalWords;
				}else{
					$this->stemmedDictionary[$stemmedWord] = $originalWords;
				}
			}
		}

	}
?>