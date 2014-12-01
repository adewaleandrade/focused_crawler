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
		public $stemmedDictionary;
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
			$this->stemmedDictionary = array();
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

				//Update the weight table's dictionary
				$this->weightTable->updateDictionary($this->stemmedDictionary);
			} else return false;
		}

		function sanitizePageContents($page){
			$stopWords = array(
				'de', 'a', 'o', 'que', 'e', 'do', 'da', 'em', 'um', 'para', 'é', 'com','não', 'uma', 'os', 'no',
				'se', 'na', 'por', 'mais', 'as', 'dos', 'como', 'mas', 'foi', 'ao', 'ele', 'das', 'tem', 'à', 'seu',
				'sua', 'ou', 'ser', 'quando', 'muito', 'há', 'nos', 'já', 'está', 'eu', 'também', 'só', 'pelo', 'pela', 'até', 
				'isso', 'ela', 'entre', 'era', 'depois', 'sem', 'mesmo', 'aos', 'ter', 'seus', 'quem', 'nas', 'me', 'esse', 'eles', 'estão',
				'você', 'tinha', 'foram', 'essa', 'num', 'nem', 'suas', 'meu', 'às', 'minha', 'têm', 'numa', 'pelos', 'elas', 'havia', 'seja',
				'qual', 'será', 'nós', 'tenho', 'lhe', 'deles', 'essas', 'esses', 'pelas', 'este', 'fosse', 'dele', 'tu', 'te', 'vocês', 'vos', 'lhes',
				'meus', 'minhas', 'teu', 'tua', 'teus', 'tuas', 'nosso', 'nossa', 'nossos', 'nossas', 'dela', 'delas', 'esta', 'estes', 'estas', 'aquele',
				'aquela', 'aqueles', 'aquelas', 'isto', 'aquilo', 'estou', 'está', 'estamos', 'estão', 'estive', 'esteve', 'estivemos', 'estiveram',
				'estava', 'estávamos', 'estavam', 'estivera', 'estivéramos', 'esteja', 'estejamos', 'estejam', 'estivesse', 'estivéssemos', 'estivessem',
				'estiver', 'estivermos', 'estiverem', 'hei', 'há', 'havemos', 'hão', 'houve', 'houvemos', 'houveram', 'houvera', 'houvéramos', 
				'haja', 'hajamos', 'hajam', 'houvesse', 'houvéssemos', 'houvessem', 'houver', 'houvermos', 'houverem', 'houverei', 'houverá', 'houveremos',
				'houverão', 'houveria', 'houveríamos', 'houveriam', 'sou', 'somos', 'são', 'era', 'éramos', 'eram', 'fui', 'foi', 'fomos',
				'foram', 'fora', 'fôramos', 'seja', 'sejamos', 'sejam', 'fosse', 'fôssemos', 'fossem', 'for', 'formos', 'forem', 'serei',
				'será', 'seremos', 'serão', 'seria', 'seríamos', 'seriam', 'tenho', 'tem', 'temos', 'tém', 'tinha', 'tínhamos', 'tinham', 'tive',
				'teve', 'tivemos', 'tiveram', 'tivera', 'tivéramos', 'tenha', 'tenhamos', 'tenham', 'tivesse', 'tivéssemos', 'tivessem', 'tiver', 
				'tivermos', 'tiverem', 'terei', 'terá', 'teremos', 'terão', 'teria', 'teríamos', 'teriam', 
				'ba', 'nov', 'dez', 'dia',
				'nbsp', 'ã©', 'eacute', 'agraves', 'r'
			);

			$text = $page->plaintext;

			//Remove caracteres especiais e numeros
			$text = preg_replace('/[0-9]h/s', '', $text);
			$text = preg_replace('/[^a-zA-ZãÃáàâõóòôêéèíìúùç ]/s', '', $text);
			
			$text = strtolower($text);
			$words = explode(' ', $text);
			$stemmedWords = array();		

			//Remove palavras desnecessárias
			foreach ($words as $k => $v) {
				if (in_array($v, $stopWords)) {
					unset($words[$k]);
				}else{
					$sWord = stem_portuguese($v);
					$stemmedWords[$sWord][$v] = $v;
				}
			}
			$this->stemmedDictionary = $stemmedWords;

			return $stemmedWords;
		}


		function getTermFrequencies(array $terms){

			foreach ($terms as $stemmedTerm => $originalTerms) {
				$this->termFrequencies[$stemmedTerm] = isset($this->termFrequencies[$stemmedTerm])?($this->termFrequencies[$stemmedTerm] + 1):1;

				if(!isset($this->crawler->weightTable->tDocFrequencies[$stemmedTerm]['urls'][$this->url])){
					$this->crawler->weightTable->tDocFrequencies[$stemmedTerm]['urls'][$this->url] = $this->url;
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